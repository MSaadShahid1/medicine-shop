<?php
require_once 'config.php';
require_once 'page_overview.php';

// Fetch all products
$sql = "SELECT p.*, c.company_name 
        FROM products p 
        LEFT JOIN companies c ON p.company_id = c.id 
        ORDER BY p.product_name ASC";
$result = $conn->query($sql);

// Initialize Totals
$total_products = 0;
$low_stock_count = 0;
$total_purchase_value = 0;
$total_sales_value = 0;
$products_data = [];

// Process data for calculations first
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $total_products++;
        if ($row['current_stock'] < 20) {
            $low_stock_count++;
        }
        $total_purchase_value += ($row['purchase_rate'] * $row['current_stock']);
        $total_sales_value += ($row['sale_rate'] * $row['current_stock']);
        $products_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        @media print {
            .sidebar, .btn-print, .no-print, .mobile-toggle, .page-header-actions { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .content-card, .page-header { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bp-page bp-inventory">
        
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"></i>
                <div>
                    <h2><i class="fas fa-clipboard-list me-2"></i>Inventory Report</h2>
                    <p>Stock Valuation and Status as of <?php echo date('d M Y'); ?></p>
                </div>
            </div>
            <div class="page-header-actions">
                <button onclick="openPDFPage()" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold btn-print">
                    <i class="fas fa-print me-2"></i> Print Report
                </button>
            </div>
        </div>

        <script>
        function openPDFPage() {
            var searchTerm = document.getElementById('reportSearch') ? document.getElementById('reportSearch').value : '';
            var url = "inventory_report_pdf.php";
            if(searchTerm) url += "?search=" + encodeURIComponent(searchTerm);
            window.open(url, "_blank");
        }
        </script>

        <!-- Report Summary -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-details">
                        <h6>Total Products</h6>
                        <h3><?php echo $total_products; ?></h3>
                    </div>
                    <div class="stat-icon bg-blue-light"><i class="fas fa-box"></i></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-details">
                        <h6>Low Stock Items</h6>
                        <h3 class="text-danger"><?php echo $low_stock_count; ?></h3>
                    </div>
                    <div class="stat-icon bg-red-light"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-details">
                        <h6>Cost Value</h6>
                        <h3 class="text-success"><?php echo formatCurrency($total_purchase_value); ?></h3>
                    </div>
                    <div class="stat-icon bg-green-light"><i class="fas fa-wallet"></i></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-details">
                        <h6>Sales Value</h6>
                        <h3 class="text-primary"><?php echo formatCurrency($total_sales_value); ?></h3>
                    </div>
                    <div class="stat-icon bg-purple-light"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>

        <section class="bp-valuation no-print" data-print-exclude aria-label="How to read inventory valuation">
            <div class="bp-valuation-story"><span class="po-eyebrow">Read your stock, not just the numbers</span><h3>A snapshot of what is on the shelf.</h3><p>These totals cover the full inventory. Filtering narrows the table; Print Report carries that search into the standalone print view.</p><a href="create_purchase_bill.php" class="po-link">Replenish stock ↗</a></div>
            <div class="bp-valuation-note"><span class="po-eyebrow">Valuation, explained</span><h4>Cost is not revenue.</h4><p><strong>Cost value</strong> = current stock × purchase rate. <strong>Sales value</strong> = current stock × sale rate: a potential selling value, not earned revenue or profit.</p><p class="mb-0">Products with fewer than <strong>20 units</strong> are flagged low, including out-of-stock items.</p></div>
        </section>

        <!-- Inventory Table -->
        <div class="content-card p-0 overflow-hidden">
            <div class="p-4 d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom border-light bg-white">
                <div><span class="po-eyebrow">Inventory register</span><h5 class="mb-1 fw-bold text-secondary">Detailed Stock List</h5><p class="bp-caption mb-0"><span id="stockRecordCount" role="status"><?php echo number_format(count($products_data)); ?> products</span> · Search product, composition or supplier.</p></div>
                <div class="no-print" style="min-width: 250px;">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="reportSearch" class="form-control" aria-label="Filter inventory by product, composition or supplier" placeholder="Filter report..." onkeyup="filterReport()">
                    </div>
                </div>
            </div>
            <div class="card-body-custom p-4 pt-0">
                <div class="table-responsive">
                    <table class="table table-modern" id="reportTable">
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <th>Company / Supplier</th>
                                <th class="text-center">Stock Level</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Value</th>
                                <th class="text-end">Sale Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products_data) > 0): ?>
                                <?php foreach($products_data as $row): 
                                    $stock_value = $row['current_stock'] * $row['purchase_rate'];
                                    $is_low = $row['current_stock'] < 20;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo $row['product_name']; ?></div>
                                        <small class="text-muted"><?php echo $row['composition']; ?></small>
                                    </td>
                                    <td class="fw-bold text-secondary"><?php echo $row['company_name']; ?></td>
                                    <td class="text-center">
                                        <?php if($is_low): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><?php echo $row['current_stock']; ?> Units</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2 shadow-sm"><?php echo $row['current_stock']; ?> Units</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-muted"><?php echo formatCurrency($row['purchase_rate']); ?></td>
                                    <td class="text-end fw-bold text-dark"><?php echo formatCurrency($stock_value); ?></td>
                                    <td class="text-end fw-bold text-primary"><?php echo formatCurrency($row['sale_rate']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3"></i>
                                            <p>No inventory data found.</p>
                                            <a href="add_product.php" class="btn btn-primary">Add your first product</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterReport() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("reportSearch");
            filter = input.value.toUpperCase();
            table = document.getElementById("reportTable");
            if (!table) return;
            tr = table.getElementsByTagName("tr");
            var visible = 0;

            for (i = 1; i < tr.length; i++) {
                // Search in Product Details (index 0) and Company (index 1)
                var tdName = tr[i].getElementsByTagName("td")[0];
                var tdCompany = tr[i].getElementsByTagName("td")[1];
                
                if (tdName && tdCompany) {
                    var txtName = tdName.textContent || tdName.innerText;
                    var txtComp = tdCompany.textContent || tdCompany.innerText;
                    
                    if (txtName.toUpperCase().indexOf(filter) > -1 || txtComp.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        visible++;
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
            document.getElementById('stockRecordCount').textContent = visible + ' matching products';
        }
    </script>
</body>
</html>