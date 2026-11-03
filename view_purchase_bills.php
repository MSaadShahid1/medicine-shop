<?php
require_once 'config.php';
require_once 'page_overview.php';

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Start Transaction
    $conn->begin_transaction();
    try {
        // 1. Delete items associated with this bill
        $conn->query("DELETE FROM purchase_bill_items WHERE purchase_bill_id = $id");
        
        // 2. Delete payments associated with this bill
        $conn->query("DELETE FROM payments WHERE purchase_bill_id = $id");
        
        // 3. Delete the bill header
        $conn->query("DELETE FROM purchase_bills WHERE id = $id");
        
        $conn->commit();
        header("Location: view_purchase_bills.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error deleting bill: " . $e->getMessage();
        exit();
    }
}

// Fetch Bills with Company Name
$sql = "SELECT pb.*, c.company_name 
        FROM purchase_bills pb 
        JOIN companies c ON pb.company_id = c.id 
        ORDER BY pb.bill_date DESC, pb.id DESC";
$result = $conn->query($sql);
$overview = $conn->query("SELECT COALESCE(SUM(pb.total_amount),0) AS total_purchases,
    COALESCE(SUM(pb.paid_amount),0) AS total_paid, COALESCE(SUM(pb.balance),0) AS total_due,
    COALESCE(SUM(CASE WHEN pb.balance > 0 THEN 1 ELSE 0 END),0) AS unpaid
    FROM purchase_bills pb JOIN companies c ON pb.company_id = c.id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Purchase Bills - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="bp-page bp-history">
        
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-file-invoice-dollar me-2"></i>Purchase History</h2>
                    <p>Track payments and stock purchases</p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="add_opening_balance.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold d-none d-sm-inline-block">
                    <i class="fas fa-file-import me-1"></i> Add Opening Balance
                </a>
                <a href="create_purchase_bill.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold d-none d-sm-inline-block">
                    <i class="fas fa-plus me-1"></i> New Purchase
                </a>

                <a href="add_opening_balance.php" class="btn btn-outline-light-soft rounded-circle p-3 d-inline-block d-sm-none"><i class="fas fa-file-import"></i></a>
                <a href="create_purchase_bill.php" class="btn btn-light rounded-circle shadow-sm p-3 d-inline-block d-sm-none"><i class="fas fa-plus"></i></a>
            </div>
        </div>

        <?php renderPageOverview([
            'eyebrow' => 'Purchases · All time', 'title' => 'Stock in. Payments in view.',
            'description' => 'Follow supplier invoices and opening balances from purchase through settlement. Open an outstanding bill to record a payment.',
            'tone' => 'lavender',
            'metrics' => [
                ['label' => 'Purchase records', 'value' => number_format($result->num_rows), 'note' => 'Includes opening balances'],
                ['label' => 'Total purchased', 'value' => formatCurrency($overview['total_purchases'])],
                ['label' => 'Paid to suppliers', 'value' => formatCurrency($overview['total_paid'])],
                ['label' => 'Outstanding', 'value' => formatCurrency($overview['total_due']), 'note' => $overview['unpaid'] . ' records with a balance']
            ],
            'links' => [['label' => 'Supplier directory', 'href' => 'view_companies.php', 'icon' => 'building-2'], ['label' => 'Record a purchase', 'href' => 'create_purchase_bill.php', 'icon' => 'plus']]
        ]); ?>

        <!-- Messages Handling -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Bill deleted successfully!
        </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'created'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Purchase bill recorded successfully!
        </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="content-card mb-4 p-3 pb-4 no-print" data-print-exclude>
            <div class="bp-section-head"><div><span class="po-eyebrow">Find a purchase</span><h3>A clearer supplier trail.</h3><p>Search supplier, bill number or displayed date. Overview totals cover all records.</p></div><span class="bp-count" id="billRecordCount" role="status"><?php echo $result->num_rows; ?> records</span></div>
            <div class="row">
                <div class="col-lg-6 col-md-8">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" aria-label="Search purchases by supplier, bill number or date" placeholder="Search by Company, Bill # or Date..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="content-card p-0 overflow-hidden">
            <div class="bp-section-head bp-table-head"><div><span class="po-eyebrow">Supplier accounts</span><h3>Purchase ledger</h3><p>Original totals, paid amounts and remaining balances, newest first.</p></div><button type="button" class="btn btn-outline-secondary no-print" data-print-exclude onclick="window.print()"><i class="fas fa-print me-1"></i> Print page</button></div>
            <div class="card-body-custom p-4">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern" id="billsTable">
                        <thead>
                            <tr>
                                <th>Bill Number</th>
                                <th>Supplier Company</th>
                                <th>Bill Date</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center no-print" data-print-exclude>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr style="cursor: pointer;" onclick="window.location='purchase_bill_detail.php?id=<?php echo $row['id']; ?>'">
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal px-3 py-2">
                                        <?php echo $row['bill_number']; ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark"><?php echo $row['company_name']; ?></td>
                                <td class="text-secondary"><?php echo date('d M Y', strtotime($row['bill_date'])); ?></td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($row['total_amount']); ?></td>
                                <td class="text-end text-success"><?php echo formatCurrency($row['paid_amount']); ?></td>
                                <td class="text-end">
                                    <?php if ($row['balance'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill px-3"><?php echo formatCurrency($row['balance']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success rounded-pill px-3">Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center no-print" data-print-exclude onclick="event.stopPropagation();">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="purchase_bill_detail.php?id=<?php echo $row['id']; ?>" class="btn-action-circle view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($row['balance'] > 0): ?>
                                        <a href="add_payment.php?bill_id=<?php echo $row['id']; ?>" class="btn-action-circle pay" title="Add Payment">
                                            <i class="fas fa-wallet"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <a href="purchase_bill_pdf.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-action-circle print" title="Print PDF">
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
                    <i class="fas fa-truck-loading fa-4x mb-3" style="color: #CBD5E1;"></i>
                    <h4 class="fw-bold text-dark">No purchase records found</h4>
                    <p class="mb-4">Start by recording your first purchase or adding an opening balance.</p>
                    <a href="create_purchase_bill.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> New Purchase
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        // Search filter
        function filterTable() {
            var input, filter, table, tr, i;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("billsTable");
            if (!table) return;
            tr = table.getElementsByTagName("tr");
            var visible = 0;

            for (i = 1; i < tr.length; i++) {
                var tdBill = tr[i].getElementsByTagName("td")[0];
                var tdComp = tr[i].getElementsByTagName("td")[1];
                var tdDate = tr[i].getElementsByTagName("td")[2];
                
                if (tdBill || tdComp || tdDate) {
                    var txtBill = tdBill.textContent || tdBill.innerText;
                    var txtComp = tdComp.textContent || tdComp.innerText;
                    var txtDate = tdDate.textContent || tdDate.innerText;
                    
                    if (txtBill.toUpperCase().indexOf(filter) > -1 || 
                        txtComp.toUpperCase().indexOf(filter) > -1 || 
                        txtDate.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        visible++;
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
            document.getElementById('billRecordCount').textContent = visible + ' matching records';
        }

        // Delete Confirmation
        function confirmDelete(id) {
            if(confirm("Are you sure you want to delete this purchase bill? \n\nNote: This will delete the bill record and payment history, but stock levels will NOT be reversed.")) {
                window.location.href = "view_purchase_bills.php?delete_id=" + id;
            }
        }

        // Auto-hide alert
        window.onload = function() {
            var alertMessage = document.getElementById('alertMessage');
            if(alertMessage) {
                setTimeout(function() {
                    alertMessage.style.transition = "opacity 0.5s ease";
                    alertMessage.style.opacity = "0";
                    setTimeout(function() {
                        alertMessage.style.display = "none";
                    }, 500); 
                }, 4000); 
            }
        }
    </script>
</body>
</html>