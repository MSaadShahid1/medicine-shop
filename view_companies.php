<?php
require_once 'config.php';
require_once 'page_overview.php';

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    // Optional: Check for dependencies before deleting
    $conn->query("DELETE FROM companies WHERE id = $id");
    header("Location: view_companies.php?msg=deleted");
    exit();
}

// Fetch Companies with basic stats (Balance Due)
// We join with purchase_bills to calculate total balance owed to each company
$sql = "SELECT c.*, 
        (SELECT COALESCE(SUM(balance), 0) FROM purchase_bills WHERE company_id = c.id) as total_due 
        FROM companies c 
        ORDER BY c.company_name ASC";
$result = $conn->query($sql);
$overview = $conn->query("SELECT COALESCE(SUM(pb.balance), 0) AS balance_due,
                         COUNT(CASE WHEN pb.balance > 0.01 THEN 1 END) AS open_bills,
                         (SELECT COUNT(*) FROM products p JOIN companies c ON c.id = p.company_id) AS products
                         FROM purchase_bills pb JOIN companies c ON c.id = pb.company_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body>
       
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-building me-2"></i>Suppliers & Companies</h2>
                    <p>Manage your business partners and view ledgers</p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="add_company.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold d-none d-sm-inline-block">
                    <i class="fas fa-plus me-1"></i> Add New Supplier
                </a>
                <a href="add_company.php" class="btn btn-light rounded-circle shadow-sm p-3 d-inline-block d-sm-none"><i class="fas fa-plus"></i></a>
            </div>
        </div>

        <?php renderPageOverview([
            'eyebrow' => 'Supplier relationships', 'title' => 'Good partners. Clear accounts.',
            'description' => 'Your supplier directory connects product sourcing, purchase history and the balances your shop owes.',
            'tone' => 'lavender',
            'metrics' => [
                ['label' => 'Registered suppliers', 'value' => $result->num_rows, 'note' => 'Companies in your directory'],
                ['label' => 'Products supplied', 'value' => $overview['products'], 'note' => 'Linked to registered suppliers'],
                ['label' => 'Unpaid purchase bills', 'value' => $overview['open_bills'], 'note' => 'Remaining balance above Rs. 0.01'],
                ['label' => 'Supplier balance', 'value' => formatCurrency($overview['balance_due']), 'note' => 'Net balance across supplier bills'],
            ],
            'links' => [
                ['label' => 'Purchase history', 'href' => 'view_purchase_bills.php', 'icon' => 'receipt'],
                ['label' => 'Opening balance', 'href' => 'add_opening_balance.php', 'icon' => 'file-input'],
            ],
        ]); ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Company profile updated successfully!
        </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4" id="alertMessage">
            <i class="fas fa-check-circle me-2"></i> Company removed successfully!
        </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="content-card mb-4 po-search-card">
            <div class="row po-search-row">
                <div class="col-lg-4"><label for="searchInput" class="po-eyebrow">Find a supplier</label><p class="po-search-caption">Search names, contact people or phone numbers.</p></div>
                <div class="col-lg-8">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by company name, contact person, or phone number..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Companies Table -->
        <div class="content-card p-0 overflow-hidden po-directory">
            <div class="card-body-custom p-4">
            <div class="po-section-head"><div><span class="po-eyebrow">Your business network</span><h3>Supplier directory</h3><p>Open a ledger for bill history and payment details.</p></div><span class="po-count"><?php echo $result->num_rows; ?> suppliers</span></div>
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern" id="companiesTable">
                        <thead>
                            <tr>
                                <th width="60">Logo</th>
                                <th>Company Details</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Balance Due</th>
                                <th class="text-center">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): 
                                $initial = strtoupper(substr($row['company_name'], 0, 1));
                            ?>
                            <tr style="cursor: pointer;" onclick="window.location='company_details.php?id=<?php echo $row['id']; ?>'">
                                <td>
                                    <div class="avatar-circle"><?php echo poEscape($initial); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold fs-6 text-dark"><?php echo poEscape($row['company_name']); ?></div>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo poEscape($row['address'] ?: 'No address provided'); ?></small>
                                </td>
                                <td class="fw-bold text-secondary"><?php echo poEscape($row['contact_person'] ?: '-'); ?></td>
                                <td><span class="text-muted"><i class="fas fa-phone-alt me-1" style="font-size: 0.8rem;"></i> <?php echo poEscape($row['phone'] ?: '-'); ?></span></td>
                                <td>
                                    <?php if($row['total_due'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><?php echo formatCurrency($row['total_due']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-check me-1"></i> Cleared</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" onclick="event.stopPropagation();">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="company_details.php?id=<?php echo $row['id']; ?>" class="btn-action-circle view" title="Open Ledger">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                        <a href="edit_company.php?id=<?php echo $row['id']; ?>" class="btn-action-circle edit" title="Edit Profile">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="view_companies.php?delete_id=<?php echo $row['id']; ?>" class="btn-action-circle delete" title="Delete" onclick="return confirm('Are you sure you want to delete this company?');">
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
                    <i class="fas fa-building fa-4x mb-3" style="color: #CBD5E1;"></i>
                    <h4 class="fw-bold text-dark">No suppliers found</h4>
                    <p class="mb-4">It looks like you haven't added any companies yet.</p>
                    <a href="add_company.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> Add Your First Supplier
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
        
        function filterTable() {
            var input = document.getElementById("searchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("companiesTable");
            if (!table) return;
            var tr = table.getElementsByTagName("tr");
            for (var i = 1; i < tr.length; i++) {
                var tdName = tr[i].getElementsByTagName("td")[1];
                var tdContact = tr[i].getElementsByTagName("td")[2];
                var tdPhone = tr[i].getElementsByTagName("td")[3];
                if (tdName || tdContact || tdPhone) {
                    var txtName = tdName.textContent || tdName.innerText;
                    var txtContact = tdContact.textContent || tdContact.innerText;
                    var txtPhone = tdPhone.textContent || tdPhone.innerText;
                    if (txtName.toUpperCase().indexOf(filter) > -1 || txtContact.toUpperCase().indexOf(filter) > -1 || txtPhone.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
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