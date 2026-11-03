<?php
require_once 'config.php';
require_once 'page_overview.php';

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Safety Check: Check if the customer has existing bills before allowing deletion
    $check_bills = $conn->query("SELECT id FROM sales_bills WHERE customer_id = $id");
    
    if ($check_bills->num_rows > 0) {
        $error_message = "Cannot delete customer. They have existing sales records in the system.";
    } else {
        $conn->query("DELETE FROM customers WHERE id = $id");
        header("Location: view_customers.php?msg=deleted");
        exit();
    }
}

// Fetch Customers with their Total Sales aggregated
// This query sums up the total_amount from sales_bills linked to each customer ID
$sql = "SELECT c.*, 
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales_bills WHERE customer_id = c.id) as lifetime_sales,
        (SELECT COUNT(id) FROM sales_bills WHERE customer_id = c.id) as total_invoices
        FROM customers c 
        ORDER BY c.customer_name ASC";
$result = $conn->query($sql);
$overview = $conn->query("SELECT COUNT(*) AS invoices, COALESCE(SUM(sb.total_amount), 0) AS sales,
                         COUNT(DISTINCT sb.customer_id) AS buying_customers
                         FROM sales_bills sb JOIN customers c ON c.id = sb.customer_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Directory - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .table-modern tbody tr { cursor: pointer; }
    </style>
</head>
<body>
        
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-users me-2"></i>Customers List</h2>
                    <p>Manage registered customers and view their cumulative ledger</p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="add_customer.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-user-plus me-1"></i> Add New Customer
                </a>
            </div>
        </div>

        <?php renderPageOverview([
            'eyebrow' => 'Customer relationships', 'title' => 'Every customer has a story.',
            'description' => 'Keep buyer details close and follow their purchase history. Sales are paid in full when each bill is created.',
            'tone' => 'yellow',
            'metrics' => [
                ['label' => 'Registered customers', 'value' => $result->num_rows, 'note' => 'Profiles in your directory'],
                ['label' => 'Customers with purchases', 'value' => $overview['buying_customers'], 'note' => 'At least one recorded sales bill'],
                ['label' => 'Customer invoices', 'value' => $overview['invoices'], 'note' => 'Linked to registered customers'],
                ['label' => 'Lifetime customer sales', 'value' => formatCurrency($overview['sales']), 'note' => 'Recorded bill totals, not customer debt'],
            ],
            'links' => [
                ['label' => 'Create a sale', 'href' => 'create_sales_bill.php', 'icon' => 'shopping-cart'],
                ['label' => 'Sales history', 'href' => 'view_sales_bills.php', 'icon' => 'receipt'],
            ],
        ]); ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-warning shadow-sm border-0 rounded-pill px-4 mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4 mb-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Customer record removed successfully!
        </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4 mb-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Customer updated successfully!
        </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="content-card mb-4 po-search-card">
            <div class="row po-search-row">
                <div class="col-lg-4"><label for="searchInput" class="po-eyebrow">Find a customer</label><p class="po-search-caption">A name or phone number takes you to their history.</p></div>
                <div class="col-lg-8">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name or phone..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Customers Table Card -->
        <div class="content-card p-0 overflow-hidden po-directory">
            <div class="card-body-custom p-4">
            <div class="po-section-head"><div><span class="po-eyebrow">People who buy from you</span><h3>Customer directory</h3><p>Open a customer to explore their invoices and purchase history.</p></div><span class="po-count"><?php echo $result->num_rows; ?> customers</span></div>
                <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern" id="customersTable">
                        <thead>
                            <tr>
                                <th width="60">#</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th class="text-center">Total Invoices</th>
                                <th class="text-end">Lifetime Purchases</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): 
                                $initial = strtoupper(substr($row['customer_name'], 0, 1));
                            ?>
                            <tr onclick="window.location='customer_ledger.php?id=<?php echo $row['id']; ?>'">
                                <td>
                                    <div class="avatar-circle"><?php echo poEscape($initial); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?php echo poEscape($row['customer_name']); ?></div>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo poEscape($row['address'] ?: 'No address'); ?></small>
                                </td>
                                <td class="text-secondary fw-bold">
                                    <i class="fas fa-phone-alt me-1 small opacity-50"></i> <?php echo poEscape($row['customer_phone'] ?: '-'); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-primary border rounded-pill px-3 py-2">
                                        <?php echo $row['total_invoices']; ?> Bills
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rs. <?php echo number_format($row['lifetime_sales'], 2); ?>
                                </td>
                                <td class="text-center" onclick="event.stopPropagation();">
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- View Ledger -->
                                        <a href="customer_ledger.php?id=<?php echo $row['id']; ?>" class="btn-action-circle view" title="View Ledger">
                                            <i class="fas fa-list-ul"></i>
                                        </a>
                                        <!-- Edit Profile - Redirects to edit_customer.php -->
                                        <a href="edit_customer.php?id=<?php echo $row['id']; ?>" class="btn-action-circle edit" title="Edit Profile">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <!-- Delete Customer -->
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
                <div class="text-center p-5 text-muted po-empty">
                    <i class="fas fa-users fa-4x mb-3" style="color: #CBD5E1;"></i>
                    <h4 class="fw-bold text-dark">No customers registered</h4>
                    <p class="mb-4">Register your customers to start tracking their purchase history.</p>
                    <a href="add_customer.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-user-plus me-1"></i> Add Your First Customer
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
         * Live filtering for the customer table
         */
        function filterTable() {
            var input = document.getElementById("searchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("customersTable");
            if (!table) return;
            var tr = table.getElementsByTagName("tr");

            for (var i = 1; i < tr.length; i++) {
                var tdName = tr[i].getElementsByTagName("td")[1];
                var tdPhone = tr[i].getElementsByTagName("td")[2];
                if (tdName || tdPhone) {
                    var txtName = tdName.textContent || tdName.innerText;
                    var txtPhone = tdPhone.textContent || tdPhone.innerText;
                    if (txtName.toUpperCase().indexOf(filter) > -1 || txtPhone.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }

        /**
         * Delete confirmation
         */
        function confirmDelete(id) {
            if(confirm("Are you sure you want to remove this customer? This will only work if the customer has NO bills recorded.")) {
                window.location.href = "view_customers.php?delete_id=" + id;
            }
        }

        /**
         * Auto-hide notification messages
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