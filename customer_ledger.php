<?php
require_once 'config.php';
require_once 'page_overview.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: view_customers.php");
    exit();
}

// 1. Fetch Customer Info
$sql_customer = "SELECT * FROM customers WHERE id = $id";
$result_customer = $conn->query($sql_customer);
if ($result_customer->num_rows == 0) die("Customer not found");
$customer = $result_customer->fetch_assoc();

// 2. Fetch Aggregated Stats
$sql_stats = "SELECT 
    COUNT(id) as total_bills,
    COALESCE(SUM(total_amount), 0) as total_spent,
    COALESCE(AVG(total_amount), 0) as average_invoice,
    MAX(bill_date) as last_purchase
    FROM sales_bills WHERE customer_id = $id";
$stats = $conn->query($sql_stats)->fetch_assoc();

// 3. Fetch Transaction History (Invoices)
$sql_history = "SELECT * FROM sales_bills WHERE customer_id = $id ORDER BY bill_date DESC";
$result_history = $conn->query($sql_history);

$initials = strtoupper(substr($customer['customer_name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $customer['customer_name']; ?> - Ledger</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .stat-box .stat-icon { width: 45px; height: 45px; border-radius: 10px; font-size: 1.2rem; }
    </style>
</head>
<body class="bp-page bp-customer">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <a href="view_customers.php" class="btn btn-outline-light-soft rounded-pill px-3 fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <div><h2 class="mb-0">Customer ledger</h2><p>Every invoice. One clear customer story.</p></div>
            </div>
            <div class="page-header-actions">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary no-print" data-print-exclude><i class="fas fa-print me-1"></i> Print page</button>
                <a href="edit_customer.php?id=<?php echo $id; ?>" class="btn btn-light px-4 rounded-pill fw-bold">
                    <i class="fas fa-pen me-1"></i> Edit Profile
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Customer Info -->
            <div class="col-lg-3 mb-4">
                <div class="profile-card bp-profile pb-4">
                    <div class="profile-header"></div>
                    <div class="customer-avatar"><?php echo poEscape($initials); ?></div>
                    <div class="px-3 text-center">
                        <span class="ledger-tag">Customer profile</span>
                        <h4 class="fw-bold text-dark mb-1"><?php echo poEscape($customer['customer_name']); ?></h4>
                        <p class="text-muted small mb-3">ID: #<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></p>
                        
                        <div class="text-start mt-4 px-2">
                            <div class="mb-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Phone</small>
                                <span class="fw-bold text-dark"><i class="fas fa-phone-alt me-2 text-muted"></i><?php echo poEscape($customer['customer_phone'] ?: 'N/A'); ?></span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Address</small>
                                <span class="fw-bold text-dark"><i class="fas fa-map-marker-alt me-2 text-muted"></i><?php echo poEscape($customer['address'] ?: 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Ledger -->
            <div class="col-lg-9">
                <section class="bp-account-ribbon bp-yellow" aria-label="Customer account history">
                    <div><span class="po-eyebrow">Customer story · All time</span><h3>A history of paid-in-full sales.</h3><p>Sales are settled when created. This ledger tracks purchases, not customer debt.</p></div>
                    <dl class="bp-mini-metrics"><div><dt>Last purchase</dt><dd><?php echo $stats['last_purchase'] ? poEscape(formatDate($stats['last_purchase'])) : 'No purchases yet'; ?></dd></div><div><dt>Average invoice</dt><dd><?php echo poEscape(formatCurrency($stats['average_invoice'])); ?></dd></div></dl>
                </section>
                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="stat-box">
                            <div class="stat-icon bg-purple"><i class="fas fa-file-invoice"></i></div>
                            <div>
                                <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Total Invoices</small>
                                <h4 class="mb-0 fw-bold"><?php echo $stats['total_bills']; ?></h4>
                                <div class="ledger-stat-note">Invoices across all time</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-box">
                            <div class="stat-icon bg-green"><i class="fas fa-coins"></i></div>
                            <div>
                                <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Lifetime Spent</small>
                                <h4 class="mb-0 fw-bold text-success">Rs. <?php echo number_format($stats['total_spent'], 2); ?></h4>
                                <div class="ledger-stat-note">Total value of recorded sales</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="content-card p-0 overflow-hidden">
                    <div class="bp-section-head bp-table-head"><div><span class="po-eyebrow">Invoice timeline</span><h3>Transaction history</h3><p><?php echo number_format($result_history->num_rows); ?> invoices · Newest first, with original recorded totals.</p></div><a href="create_sales_bill.php" class="btn btn-primary no-print" data-print-exclude><i class="fas fa-plus me-1"></i> New sale</a></div>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Bill #</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center no-print" data-print-exclude>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_history->num_rows > 0): ?>
                                    <?php while($row = $result_history->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-secondary fw-bold"><?php echo date('d M Y', strtotime($row['bill_date'])); ?></td>
                                        <td><span class="badge bg-light text-primary border"><?php echo $row['bill_number']; ?></span></td>
                                        <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td class="text-center no-print" data-print-exclude>
                                            <a href="sales_bill_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light border rounded-pill px-3">View Bill</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="po-empty text-center p-5 text-muted"><h6>A fresh customer story.</h6><p>No invoices have been linked to this customer yet. Use their saved name on the next sale.</p><a href="create_sales_bill.php" class="btn btn-primary">Create a sales bill</a></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }</script>
</body>
</html>