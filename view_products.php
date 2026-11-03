<?php
require_once 'config.php';
require_once 'page_overview.php';

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    // Check if product has sales/purchase history before deleting
    // For now, simple delete
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: view_products.php?msg=deleted");
    exit();
}

// Fetch Products with Company Name
$sql = "SELECT p.*, c.company_name 
        FROM products p 
        LEFT JOIN companies c ON p.company_id = c.id 
        ORDER BY p.id DESC";
$result = $conn->query($sql);
$overview = $conn->query("SELECT COALESCE(SUM(current_stock), 0) AS units,
                         COUNT(CASE WHEN current_stock < 20 THEN 1 END) AS low_stock,
                         COALESCE(SUM(current_stock * purchase_rate), 0) AS stock_cost FROM products")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Products - Medicine Shop</title>
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
                    <h2><i class="fas fa-box me-2"></i>Products List</h2>
                    <p>Manage inventory, prices, and stock levels</p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="add_product.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold d-none d-sm-inline-block">
                    <i class="fas fa-plus me-1"></i> Add New Product
                </a>
                <a href="add_product.php" class="btn btn-light rounded-circle shadow-sm p-3 d-inline-block d-sm-none"><i class="fas fa-plus"></i></a>
            </div>
        </div>

        <?php renderPageOverview([
            'eyebrow' => 'Inventory essentials', 'title' => 'Know what is on your shelves.',
            'description' => 'A live view of medicines, supplier links and rates. Review low stock before recording your next purchase.',
            'tone' => 'mint',
            'metrics' => [
                ['label' => 'Product records', 'value' => $result->num_rows, 'note' => 'Medicines in your catalogue'],
                ['label' => 'Units on hand', 'value' => number_format($overview['units']), 'note' => 'Current stock across all products'],
                ['label' => 'Below 20 units', 'value' => $overview['low_stock'], 'note' => 'Includes zero-stock products'],
                ['label' => 'Stock at purchase cost', 'value' => formatCurrency($overview['stock_cost']), 'note' => 'Current units × current purchase rate'],
            ],
            'links' => [
                ['label' => 'Inventory report', 'href' => 'inventory_report.php', 'icon' => 'warehouse'],
                ['label' => 'Record a purchase', 'href' => 'create_purchase_bill.php', 'icon' => 'truck'],
            ],
        ]); ?>

        <!-- Messages Handling -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-success shadow-sm border-0 rounded-pill px-4" id="alertMessage">
                <i class="fas fa-check-circle me-2"></i> Product deleted successfully!
            </div>
            <?php elseif ($_GET['msg'] == 'updated'): ?>
            <div class="alert alert-success shadow-sm border-0 rounded-pill px-4" id="alertMessage">
                <i class="fas fa-check-circle me-2"></i> Product details updated successfully!
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="content-card mb-4 po-search-card">
            <div class="row po-search-row">
                <div class="col-lg-4"><label for="searchInput" class="po-eyebrow">Find a medicine</label><p class="po-search-caption">Search product names, compositions or suppliers.</p></div>
                <div class="col-lg-8">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by product name, composition or company..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="content-card p-0 overflow-hidden po-directory">
            <div class="card-body-custom p-4">
            <div class="po-section-head"><div><span class="po-eyebrow">The stockroom, organised</span><h3>Product catalogue</h3><p>Current quantities and per-unit rates. Stock below 20 units is highlighted.</p></div><span class="po-count"><?php echo $result->num_rows; ?> products</span></div>
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern" id="productsTable">
                        <thead>
                            <tr>
                                <th width="60">Icon</th>
                                <th>Product Details</th>
                                <th>Company / Supplier</th>
                                <th class="text-center">Current Stock</th>
                                <th class="text-end">Cost Price</th>
                                <th class="text-end">Selling Price</th>
                                <th class="text-center">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="product-icon-box">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold fs-6 text-dark"><?php echo poEscape($row['product_name']); ?></div>
                                    <small class="text-muted"><i class="fas fa-flask me-1"></i> <?php echo poEscape($row['composition'] ?: 'N/A'); ?></small>
                                </td>
                                <td class="fw-bold text-secondary">
                                    <i class="fas fa-building me-1 text-muted" style="font-size: 0.8rem;"></i> <?php echo poEscape($row['company_name'] ?: 'Unknown'); ?>
                                </td>
                                <td class="text-center">
                                    <?php if($row['current_stock'] < 20): ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><?php echo $row['current_stock']; ?> Units</span>
                                    <?php else: ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2 shadow-sm"><?php echo $row['current_stock']; ?> Units</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted fw-bold"><?php echo formatCurrency($row['purchase_rate']); ?></td>
                                <td class="text-end text-dark fw-bolder"><?php echo formatCurrency($row['sale_rate']); ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="btn-action-circle edit" title="Edit Product">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn-action-circle delete" title="Delete Product">
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
                    <i class="fas fa-box-open fa-4x mb-3" style="color: #CBD5E1;"></i>
                    <h4 class="fw-bold text-dark">No products found</h4>
                    <p class="mb-4">It looks like your inventory is currently empty.</p>
                    <a href="add_product.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> Add Your First Product
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        // Advanced search filter
        function filterTable() {
            var input, filter, table, tr, tdDetails, tdCompany, i, txtValueDetails, txtValueCompany;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("productsTable");
            if (!table) return;
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                // Combine Product Name & Composition into tdDetails (Index 1)
                tdDetails = tr[i].getElementsByTagName("td")[1];
                tdCompany = tr[i].getElementsByTagName("td")[2];
                
                if (tdDetails || tdCompany) {
                    txtValueDetails = tdDetails.textContent || tdDetails.innerText;
                    txtValueCompany = tdCompany.textContent || tdCompany.innerText;
                    
                    if (txtValueDetails.toUpperCase().indexOf(filter) > -1 || 
                        txtValueCompany.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }

        // Delete Confirmation
        function confirmDelete(id) {
            if(confirm("Are you sure you want to delete this product? This action cannot be undone.")) {
                window.location.href = "view_products.php?delete_id=" + id;
            }
        }

        // Auto-hide alert after 4 seconds
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