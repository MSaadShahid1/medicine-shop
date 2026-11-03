<?php
require_once 'config.php';
require_once 'page_overview.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: view_companies.php");
    exit();
}

// 1. Fetch Company Info
$sql_company = "SELECT * FROM companies WHERE id = $id";
$result_company = $conn->query($sql_company);
if ($result_company->num_rows == 0) die("Company not found");
$company = $result_company->fetch_assoc();

// 2. Fetch Purchase Ledger Stats (Wallet)
$sql_ledger = "SELECT 
    COUNT(id) as total_bills,
    COALESCE(SUM(total_amount), 0) as total_bought,
    COALESCE(SUM(paid_amount), 0) as total_paid,
    COALESCE(SUM(balance), 0) as current_due
    FROM purchase_bills WHERE company_id = $id";
$ledger = $conn->query($sql_ledger)->fetch_assoc();

// 3. Find the Oldest Unpaid Bill for "Pay Now" Shortcut
$sql_unpaid = "SELECT id FROM purchase_bills WHERE company_id = $id AND balance > 0.1 ORDER BY bill_date ASC LIMIT 1";
$res_unpaid = $conn->query($sql_unpaid);
$pay_now_link = "#purchase"; // Default fallback: scroll to history
$pay_now_target = "";
$pay_js = "onclick=\"var tab = new bootstrap.Tab(document.querySelector('#purchase-tab')); tab.show(); document.getElementById('purchase').scrollIntoView({behavior: 'smooth'});\"";

if ($res_unpaid->num_rows > 0) {
    $unpaid_id = $res_unpaid->fetch_assoc()['id'];
    $pay_now_link = "add_payment.php?bill_id=" . $unpaid_id;
    $pay_js = ""; // No JS needed if direct link
}

// 4. Fetch Inventory & Sales Performance Stats
// A. Stock Valuation
$sql_stock_stats = "SELECT 
    COALESCE(SUM(current_stock * purchase_rate), 0) as stock_cost_value,
    COALESCE(SUM(current_stock * sale_rate), 0) as stock_sales_value
    FROM products WHERE company_id = $id";
$stock_stats = $conn->query($sql_stock_stats)->fetch_assoc();

// B. Total Revenue Generated
$sql_sales_stats = "SELECT 
    COALESCE(SUM(sbi.total), 0) as total_revenue
    FROM sales_bill_items sbi
    JOIN products p ON sbi.product_id = p.id
    WHERE p.company_id = $id";
$sales_stats = $conn->query($sql_sales_stats)->fetch_assoc();

// 5. Fetch Products List
$sql_products = "SELECT * FROM products WHERE company_id = $id ORDER BY product_name ASC";
$result_products = $conn->query($sql_products);

// 6. Fetch Purchase History
$sql_purchases = "SELECT * FROM purchase_bills WHERE company_id = $id ORDER BY bill_date DESC";
$result_purchases = $conn->query($sql_purchases);

// 7. Sales Analysis
$sql_sales_analysis = "SELECT 
                        sb.bill_number, sb.customer_name, sb.bill_date,
                        p.product_name, sbi.quantity, sbi.total
                        FROM sales_bill_items sbi
                        JOIN products p ON sbi.product_id = p.id
                        JOIN sales_bills sb ON sbi.sales_bill_id = sb.id
                        WHERE p.company_id = $id
                        ORDER BY sb.bill_date DESC LIMIT 50";
$result_sales = $conn->query($sql_sales_analysis);

// Helper for Initials
$initials = strtoupper(substr($company['company_name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company['company_name']; ?> - Profile</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .balance-card { margin: 25px 20px; border-radius: 12px; }
        .stock-track { width: 100px; margin-top: 8px; }
    </style>
</head>
<body class="bp-page bp-supplier">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Prominent Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"></i>
                <a href="view_companies.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                    <i class="fas fa-arrow-left me-2"></i> Back to Suppliers
                </a>
                <div><h2>Supplier account</h2><p>Stock, purchases and payments. Connected.</p></div>
            </div>
            <div class="page-header-actions">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary no-print" data-print-exclude><i class="fas fa-print me-1"></i> Print page</button>
                <a href="edit_company.php?id=<?php echo $id; ?>" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">
                    <i class="fas fa-pen text-primary me-1"></i> Edit Profile
                </a>
                <a href="create_purchase_bill.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-plus me-1"></i> New Purchase
                </a>
            </div>
        </div>

        <div class="row">
            <!-- LEFT SIDEBAR: Highly Engaging Profile Identity -->
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="profile-sidebar bp-profile">
                    <div class="profile-banner"></div>
                    <div class="company-avatar"><?php echo poEscape($initials); ?></div>
                    <h3 class="h4 fw-bolder mb-1 text-dark"><?php echo poEscape($company['company_name']); ?></h3>
                    <p class="text-muted small fw-bold">SUPPLIER ID: #<?php echo str_pad($company['id'], 4, '0', STR_PAD_LEFT); ?></p>

                    <!-- Eye-Catching Outstanding Balance -->
                    <div class="balance-card">
                        <div class="balance-label">Total Outstanding</div>
                        <div class="balance-amount"><small>Rs. </small><?php echo number_format($ledger['current_due'], 2); ?></div>
                        <?php if($ledger['current_due'] > 0): ?>
                        <div class="mt-2">
                            <a href="<?php echo $pay_now_link; ?>" <?php echo $pay_js; ?> class="btn btn-pay-now w-100 shadow-sm py-2">
                                <i class="fas fa-wallet me-1"></i> Settle Payment
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="mt-2 px-3 py-2 bg-white text-success rounded-pill fw-bold shadow-sm d-inline-block">
                            <i class="fas fa-check-circle me-1"></i> All Dues Cleared
                        </div>
                        <?php endif; ?>
                    </div>

                    <ul class="info-list">
                        <li>
                            <div class="info-icon"><i class="fas fa-user"></i></div>
                            <div>
                                <div class="small text-muted text-uppercase fw-bold">Key Contact</div>
                                <strong><?php echo $company['contact_person'] ?: 'N/A'; ?></strong>
                            </div>
                        </li>
                        <li>
                            <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                            <div>
                                <div class="small text-muted text-uppercase fw-bold">Phone</div>
                                <strong><?php echo $company['phone'] ?: 'N/A'; ?></strong>
                            </div>
                        </li>
                        <li>
                            <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="small text-muted text-uppercase fw-bold">Location</div>
                                <strong><?php echo $company['address'] ?: 'No address provided'; ?></strong>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- RIGHT CONTENT: Modern Stats & Clean Data Tabs -->
            <div class="col-lg-9 col-md-8">
                <div class="bp-section-head bp-profile-intro"><div><span class="po-eyebrow">Supplier relationship · All time</span><h3>The account behind your inventory.</h3><p>Purchases include opening balances. Stock cost uses current quantities and purchase rates; revenue reflects recorded sales of this supplier’s products.</p></div></div>
                <!-- Section 1: Financial & Inventory Performance -->
                <div class="row">
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-details">
                                <h6>Lifetime Purchases</h6>
                                <h4><?php echo formatCurrency($ledger['total_bought']); ?></h4>
                            </div>
                            <div class="stat-icon bg-blue-light"><i class="fas fa-shopping-bag"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-details">
                                <h6>Total Paid to Supplier</h6>
                                <h4 class="text-success"><?php echo formatCurrency($ledger['total_paid']); ?></h4>
                            </div>
                            <div class="stat-icon bg-green-light"><i class="fas fa-money-check-alt"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-details">
                                <h6>Current Stock Cost</h6>
                                <h4><?php echo formatCurrency($stock_stats['stock_cost_value']); ?></h4>
                            </div>
                            <div class="stat-icon bg-purple-light"><i class="fas fa-boxes"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-details">
                                <h6>Revenue Generated</h6>
                                <h4 class="text-primary"><?php echo formatCurrency($sales_stats['total_revenue']); ?></h4>
                            </div>
                            <div class="stat-icon bg-orange-light"><i class="fas fa-chart-line"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Data Tabs Wrapper -->
                <div class="profile-content bp-tab-frame">
                    <ul class="nav nav-pills" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active shadow-sm" id="products-tab" data-bs-toggle="pill" data-bs-target="#products" type="button">
                                <i class="fas fa-box me-1"></i> Product Catalog
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link shadow-sm" id="purchase-tab" data-bs-toggle="pill" data-bs-target="#purchase" type="button">
                                <i class="fas fa-history me-1"></i> Purchase History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link shadow-sm" id="sales-tab" data-bs-toggle="pill" data-bs-target="#sales" type="button">
                                <i class="fas fa-chart-bar me-1"></i> Sales Analysis
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-4">
                        <!-- PRODUCTS TAB -->
                        <div class="tab-pane fade show active" id="products">
                            <div class="bp-section-head"><div><span class="po-eyebrow">Current inventory</span><h3>Product catalog <span class="bp-count"><?php echo number_format($result_products->num_rows); ?></span></h3><p>Current stock and rates. Fewer than 20 units is marked low.</p></div><a href="add_product.php" class="po-link no-print" data-print-exclude>Add a product ↗</a></div>
                            <?php if ($result_products->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Product Details</th>
                                            <th>Stock Status</th>
                                            <th>Purchase Cost</th>
                                            <th>Selling Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($prod = $result_products->fetch_assoc()): 
                                            $stock_percent = min(100, ($prod['current_stock'] / 50) * 100); 
                                            $bar_color = ($prod['current_stock'] < 20) ? 'stock-low' : 'stock-ok';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold fs-6"><?php echo $prod['product_name']; ?></div>
                                                <small class="text-muted"><?php echo $prod['composition']; ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column justify-content-center">
                                                    <span class="fw-bolder"><?php echo $prod['current_stock']; ?> Units</span>
                                                    <div class="stock-track"><div class="stock-fill <?php echo $bar_color; ?>" style="width: <?php echo $stock_percent; ?>%"></div></div>
                                                </div>
                                            </td>
                                            <td class="fw-bold text-muted"><?php echo formatCurrency($prod['purchase_rate']); ?></td>
                                            <td class="fw-bold text-dark"><?php echo formatCurrency($prod['sale_rate']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <div class="po-empty text-center p-5 text-muted">
                                    <i class="fas fa-box-open fa-3x mb-3 text-light"></i>
                                    <h5>No products found</h5>
                                    <p>Add products to link them to this supplier.</p>
                                    <a href="add_product.php" class="btn btn-primary">Add a product</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- PURCHASE HISTORY TAB -->
                        <div class="tab-pane fade" id="purchase">
                            <div class="bp-section-head"><div><span class="po-eyebrow">Supplier ledger</span><h3>Purchase history <span class="bp-count"><?php echo number_format($result_purchases->num_rows); ?></span></h3><p>Original invoices and opening balances. Open a bill to review payments or settle its balance.</p></div></div>
                            <?php if ($result_purchases->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice #</th>
                                            <th class="text-end">Total Amount</th>
                                            <th class="text-end">Balance Due</th>
                                            <th class="text-center no-print" data-print-exclude>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($pur = $result_purchases->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-muted fw-bold"><?php echo date('d M Y', strtotime($pur['bill_date'])); ?></td>
                                            <td><span class="badge bg-light text-dark border px-2 py-1 fs-6"><?php echo $pur['bill_number']; ?></span></td>
                                            <td class="text-end fw-bold"><?php echo formatCurrency($pur['total_amount']); ?></td>
                                            <td class="text-end">
                                                <?php if($pur['balance'] > 0): ?>
                                                    <span class="text-danger fw-bolder"><?php echo formatCurrency($pur['balance']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check"></i> Paid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center no-print" data-print-exclude>
                                                <a href="purchase_bill_detail.php?id=<?php echo $pur['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-circle" title="View"><i class="fas fa-eye"></i></a>
                                                <?php if ($pur['balance'] > 0): ?>
                                                    <a href="add_payment.php?bill_id=<?php echo $pur['id']; ?>" class="btn btn-sm btn-success rounded-circle ms-1" title="Pay"><i class="fas fa-wallet"></i></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <div class="text-center p-5 text-muted">
                                    <i class="fas fa-file-invoice-dollar fa-3x mb-3 text-light"></i>
                                    <h5>No purchase history</h5>
                                    <p>Start recording purchases to view ledger.</p>
                                    <a href="create_purchase_bill.php" class="btn btn-primary">Record a purchase</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- SALES ANALYSIS TAB -->
                        <div class="tab-pane fade" id="sales">
                            <div class="bp-section-head"><div><span class="po-eyebrow">Product performance</span><h3>Recent sales lines</h3><p><?php echo number_format($result_sales->num_rows); ?> lines shown · Up to the latest 50 item sales, not the full invoice total.</p></div><a href="view_sales_bills.php" class="po-link no-print" data-print-exclude>Sales history ↗</a></div>
                            <?php if ($result_sales->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Customer Details</th>
                                            <th>Item Sold</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($sale = $result_sales->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-muted fw-bold"><?php echo date('d M Y', strtotime($sale['bill_date'])); ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo $sale['customer_name']; ?></div>
                                                <small class="text-muted">INV: <?php echo $sale['bill_number']; ?></small>
                                            </td>
                                            <td><span class="text-primary fw-bold"><?php echo $sale['product_name']; ?></span></td>
                                            <td class="text-center fw-bold"><?php echo $sale['quantity']; ?></td>
                                            <td class="text-end fw-bolder text-success"><?php echo formatCurrency($sale['total']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <div class="text-center p-5 text-muted">
                                    <i class="fas fa-chart-pie fa-3x mb-3 text-light"></i>
                                    <h5>No sales data</h5>
                                    <p>Products from this supplier haven't been sold yet.</p>
                                    <a href="create_sales_bill.php" class="btn btn-primary">Create a sales bill</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>