<?php
require_once 'config.php';
require_once 'page_overview.php';

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Start Transaction to ensure data consistency
    $conn->begin_transaction();
    try {
        // 1. Delete items associated with this bill first
        $conn->query("DELETE FROM sales_bill_items WHERE sales_bill_id = $id");
        
        // 2. Delete the bill header record
        $conn->query("DELETE FROM sales_bills WHERE id = $id");
        
        $conn->commit();
        header("Location: view_sales_bills.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error deleting bill: " . $e->getMessage();
        exit();
    }
}

// Fetch Bills with Customer Name from the linked customers table
// We use a LEFT JOIN to ensure bills show up even if a customer record was accidentally removed
$sql = "SELECT sb.*, c.customer_name as linked_name 
        FROM sales_bills sb 
        LEFT JOIN customers c ON sb.customer_id = c.id 
        ORDER BY sb.bill_date DESC, sb.id DESC";
$result = $conn->query($sql);
$overview = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS total_sales,
    COALESCE(AVG(total_amount),0) AS average_bill, MAX(bill_date) AS latest_bill
    FROM sales_bills")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="bp-page bp-history">
        
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-receipt me-2"></i>Sales History</h2>
                    <p>View all customer invoices and transaction records</p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="create_sales_bill.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-plus me-1"></i> New Sales Bill
                </a>
            </div>
        </div>

        <?php renderPageOverview([
            'eyebrow' => 'Sales · All time', 'title' => 'Every sale, in one place.',
            'description' => 'Review customer invoices, open an itemised bill, or revisit a print-ready receipt. Sales are paid in full when created.',
            'tone' => 'yellow',
            'metrics' => [
                ['label' => 'Invoices', 'value' => number_format($result ? $result->num_rows : 0), 'note' => 'All recorded sales'],
                ['label' => 'Sales value', 'value' => formatCurrency($overview['total_sales']), 'note' => 'Stored invoice totals'],
                ['label' => 'Average invoice', 'value' => formatCurrency($overview['average_bill'])],
                ['label' => 'Latest sale', 'value' => $overview['latest_bill'] ? formatDate($overview['latest_bill']) : 'No sales yet']
            ],
            'links' => [['label' => 'Customer directory', 'href' => 'view_customers.php', 'icon' => 'users'], ['label' => 'New sales bill', 'href' => 'create_sales_bill.php', 'icon' => 'plus']]
        ]); ?>

        <!-- Success Messages -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4 mb-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Sales record deleted successfully!
        </div>
        <?php endif; ?>

        <!-- Search and Filter Bar -->
        <div class="content-card mb-4 p-3 pb-4 no-print" data-print-exclude>
            <div class="bp-section-head"><div><span class="po-eyebrow">Find a sale</span><h3>From invoice to customer.</h3><p>Search bill numbers or customer names. Overview totals always cover all invoices.</p></div><span class="bp-count" id="billRecordCount" role="status"><?php echo $result ? $result->num_rows : 0; ?> invoices</span></div>
            <div class="row">
                <div class="col-lg-6 col-md-8">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" aria-label="Search sales by bill number or customer" placeholder="Search by Bill # or Customer Name..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table Card -->
        <div class="content-card p-0 overflow-hidden">
            <div class="bp-section-head bp-table-head"><div><span class="po-eyebrow">Invoice archive</span><h3>Sales ledger</h3><p>Newest invoices first. Open a row for items and the original bill total.</p></div><button type="button" class="btn btn-outline-secondary no-print" data-print-exclude onclick="window.print()"><i class="fas fa-print me-1"></i> Print page</button></div>
            <div class="card-body-custom p-4">
                <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern" id="billsTable">
                        <thead>
                            <tr>
                                <th>Bill Number</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Billing Date</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-center no-print" data-print-exclude>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): 
                                // Priority to name in customers table, fallback to name stored in bill
                                $display_name = !empty($row['linked_name']) ? $row['linked_name'] : $row['customer_name'];
                            ?>
                            <tr style="cursor: pointer;" onclick="window.location='sales_bill_detail.php?id=<?php echo $row['id']; ?>'">
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal px-3 py-2">
                                        <?php echo $row['bill_number']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo $display_name; ?></div>
                                    <?php if(!empty($row['customer_id'])): ?>
                                        <small class="text-primary"><i class="fas fa-id-badge me-1"></i> Registered</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <i class="fas fa-phone-alt me-1"></i> <?php echo !empty($row['customer_phone']) ? $row['customer_phone'] : '-'; ?>
                                </td>
                                <td class="text-secondary">
                                    <?php echo date('d M Y', strtotime($row['bill_date'])); ?>
                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($row['bill_date'])); ?></small>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rs. <?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td class="text-center no-print" data-print-exclude onclick="event.stopPropagation();">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="sales_bill_detail.php?id=<?php echo $row['id']; ?>" class="btn-action-circle view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="sales_bill_pdf.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-action-circle print" title="Print PDF">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn-action-circle delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="po-empty text-center p-5 text-muted">
                    <i class="fas fa-file-invoice fa-4x mb-3" style="color: #CBD5E1;"></i>
                    <h4 class="fw-bold text-dark">No sales history found</h4>
                    <p class="mb-4">It looks like you haven't generated any invoices yet.</p>
                    <a href="create_sales_bill.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> Create Your First Bill
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        /**
         * Real-time search filter for the table
         */
        function filterTable() {
            var input = document.getElementById("searchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("billsTable");
            if (!table) return;
            var tr = table.getElementsByTagName("tr");
            var visible = 0;

            for (var i = 1; i < tr.length; i++) {
                var tdBill = tr[i].getElementsByTagName("td")[0];
                var tdCust = tr[i].getElementsByTagName("td")[1];
                
                if (tdBill || tdCust) {
                    var txtBill = tdBill.textContent || tdBill.innerText;
                    var txtCust = tdCust.textContent || tdCust.innerText;
                    
                    if (txtBill.toUpperCase().indexOf(filter) > -1 || 
                        txtCust.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        visible++;
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
            document.getElementById('billRecordCount').textContent = visible + ' matching invoices';
        }

        /**
         * Confirm deletion of sales record
         */
        function confirmDelete(id) {
            if(confirm("DANGER: Are you sure you want to delete this bill? \n\nDeleting this bill will remove all item details and cannot be reversed. Stock levels will not be adjusted automatically.")) {
                window.location.href = "view_sales_bills.php?delete_id=" + id;
            }
        }

        /**
         * Alert auto-hide logic
         */
        window.onload = function() {
            var alertMessage = document.getElementById('alertMessage');
            if(alertMessage) {
                setTimeout(function() {
                    alertMessage.style.transition = "opacity 0.5s ease";
                    alertMessage.style.opacity = "0";
                    setTimeout(function() { alertMessage.style.display = "none"; }, 500); 
                }, 4000); 
            }
        }
    </script>
</body>
</html>