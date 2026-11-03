<?php
require_once 'config.php';

// Read-only totals. Sales are paid at creation; paid here refers to suppliers.
$total_products = (int) $conn->query("SELECT COUNT(*) AS count FROM products")->fetch_assoc()['count'];
$total_companies = (int) $conn->query("SELECT COUNT(*) AS count FROM companies")->fetch_assoc()['count'];
$total_customers = (int) $conn->query("SELECT COUNT(*) AS count FROM customers")->fetch_assoc()['count'];
$total_sales = (float) $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales_bills")->fetch_assoc()['total'];
$total_purchases = (float) $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM purchase_bills")->fetch_assoc()['total'];
$total_paid = (float) $conn->query("SELECT COALESCE(SUM(paid_amount), 0) AS total FROM purchase_bills")->fetch_assoc()['total'];
$total_balance = $total_purchases - $total_paid;
$today_sales = (float) $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales_bills WHERE bill_date = CURDATE()")->fetch_assoc()['total'];
$low_stock_count = (int) $conn->query("SELECT COUNT(*) AS count FROM products WHERE current_stock < 20")->fetch_assoc()['count'];
$healthy_stock_count = (int) $conn->query("SELECT COUNT(*) AS count FROM products WHERE current_stock >= 20")->fetch_assoc()['count'];
$critical_stock_count = (int) $conn->query("SELECT COUNT(*) AS count FROM products WHERE current_stock < 10")->fetch_assoc()['count'];
$stock_health = $total_products > 0 ? round($healthy_stock_count / $total_products * 100) : null;
$inventory_snapshot = $conn->query("SELECT COALESCE(SUM(current_stock * purchase_rate), 0) AS cost_value, COALESCE(SUM(current_stock * sale_rate), 0) AS retail_value, SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) AS unavailable FROM products")->fetch_assoc();
$unpaid_bill_count = (int) $conn->query("SELECT COUNT(*) AS count FROM purchase_bills WHERE balance > 0.01")->fetch_assoc()['count'];
$sales_bill_count = (int) $conn->query("SELECT COUNT(*) AS count FROM sales_bills")->fetch_assoc()['count'];
$today_bill_count = (int) $conn->query("SELECT COUNT(*) AS count FROM sales_bills WHERE bill_date = CURDATE()")->fetch_assoc()['count'];
$has_demo_records = (bool) $conn->query("SELECT EXISTS(SELECT 1 FROM products WHERE product_name LIKE '[DEMO]%') AS present")->fetch_assoc()['present'];
$snapshot_timestamp = date(DATE_ATOM);
$low_stock = $conn->query("SELECT id, product_name, composition, current_stock FROM products WHERE current_stock < 20 ORDER BY current_stock ASC, id ASC LIMIT 5");

$top_suppliers = [];
$top_suppliers_res = $conn->query("SELECT c.id, c.company_name, COALESCE(SUM(pb.total_amount), 0) AS amt
    FROM companies c LEFT JOIN purchase_bills pb ON pb.company_id = c.id
    GROUP BY c.id, c.company_name HAVING amt > 0 ORDER BY amt DESC LIMIT 4");
while ($row = $top_suppliers_res->fetch_assoc()) {
    $top_suppliers[] = $row;
}

// Anchor to the first of the month so month-end dates cannot skip a month.
$months = [];
$sales_by_month = [];
$purchases_by_month = [];
$month_start = strtotime(date('Y-m-01'));
for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime("-$i months", $month_start);
    $key = date('Y-m', $timestamp);
    $months[$key] = date('M Y', $timestamp);
    $sales_by_month[$key] = 0;
    $purchases_by_month[$key] = 0;
}
$sales_trend = $conn->query("SELECT DATE_FORMAT(bill_date, '%Y-%m') AS ym, SUM(total_amount) AS total
    FROM sales_bills WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym");
while ($row = $sales_trend->fetch_assoc()) {
    if (isset($sales_by_month[$row['ym']])) $sales_by_month[$row['ym']] = (float) $row['total'];
}
$purchases_trend = $conn->query("SELECT DATE_FORMAT(bill_date, '%Y-%m') AS ym, SUM(total_amount) AS total
    FROM purchase_bills WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym");
while ($row = $purchases_trend->fetch_assoc()) {
    if (isset($purchases_by_month[$row['ym']])) $purchases_by_month[$row['ym']] = (float) $row['total'];
}
$current_month_sales = (float) ($sales_by_month[date('Y-m')] ?? 0);
$previous_month_key = date('Y-m', strtotime('-1 month', $month_start));
$previous_month_sales = (float) ($sales_by_month[$previous_month_key] ?? 0);
$paid_percentage = $total_purchases > 0 ? round($total_paid / $total_purchases * 100, 1) : 0;
$dashboard_data = [
    'trend' => ['labels' => array_values($months), 'sales' => array_values($sales_by_month), 'purchases' => array_values($purchases_by_month)],
    'snapshotAt' => $snapshot_timestamp,
];

$recent_txns = $conn->query(
    "(SELECT sb.bill_date AS d, sb.bill_number AS ref, sb.customer_name AS party, sb.total_amount AS amount,
        'Sale' AS type, 'Paid' AS status_label, sb.id AS ref_id FROM sales_bills sb)
     UNION ALL
     (SELECT pb.bill_date AS d, pb.bill_number AS ref, c.company_name AS party, pb.total_amount AS amount,
        'Purchase' AS type, IF(pb.balance > 0.01, 'Due', 'Paid') AS status_label, pb.id AS ref_id
        FROM purchase_bills pb JOIN companies c ON pb.company_id = c.id)
     ORDER BY d DESC, ref_id DESC, type ASC LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <link rel="stylesheet" href="css/lisso-dashboard.css?v=<?php echo filemtime(__DIR__ . '/css/lisso-dashboard.css'); ?>">
    <script defer src="js/vendor/apexcharts-3.45.2.min.js"></script>
    <script>
        // Motion guard: hide the dashboard body until js/dashboard-motion.js has set its first frame.
        (function () {
            var root = document.documentElement;
            try { if (matchMedia('(prefers-reduced-motion: reduce)').matches || localStorage.getItem('farms.motion') === 'paused') return; } catch (e) {}
            root.classList.add('cx-motion');
            setTimeout(function () { if (!root.classList.contains('cx-motion-ready')) root.classList.remove('cx-motion'); }, 3000);
        })();
    </script>
    <style>.cx-motion:not(.cx-motion-ready) .ld-main { opacity: 0; }</style>
</head>
<body class="lisso-dashboard cx-dashboard">
    <?php include 'sidebar.php'; ?>
    <link rel="stylesheet" href="css/dashboard-motion.css?v=<?php echo filemtime(__DIR__ . '/css/dashboard-motion.css'); ?>">

    <main class="main-content ld-main">
        <header class="ld-greeting">
            <div class="ld-hello"><span class="ld-avatar" aria-hidden="true">MS</span><h1>Your day, in focus.</h1></div>
            <p>Your business today</p>
            <div class="ld-date"><time datetime="<?php echo date('Y-m-d'); ?>"><?php echo date('D, d M Y'); ?></time><span class="cx-last-updated">Snapshot · <?php echo date('H:i'); ?></span></div>
        </header>

        <section class="cx-hero" aria-labelledby="dashboardStudioTitle">
            <span class="cx-hero-index" aria-hidden="true">01 / THE DAILY PICTURE</span>
            <div class="cx-hero-copy">
                <span class="cx-kicker">A little clarity. A better business.</span>
                <h2 class="cx-hero-title" id="dashboardStudioTitle"><span>Clarity</span><br><span class="cx-gradient-text">in every detail.</span></h2>
                <p class="cx-hero-description">Your stock, your people, your next move. One thoughtful view of everything that keeps Medicine Shop moving.</p>
                <div class="cx-hero-actions">
                    <a href="create_sales_bill.php" class="cx-primary-action"><i data-lucide="plus" aria-hidden="true"></i> Create a sale <span aria-hidden="true">↗</span></a>
                    <a href="inventory_report.php" class="cx-secondary-action">Explore inventory <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                    <button type="button" class="cx-command-link" data-wx-open>Find a page <kbd data-wx-shortcut>Ctrl K</kbd></button>
                </div>
            </div>
            <div class="cx-orbital-scene" aria-label="Inventory health snapshot">
                <span class="cx-orbit-ring ring-one" aria-hidden="true"></span><span class="cx-orbit-ring ring-two" aria-hidden="true"></span><span class="cx-orbit-ring ring-three" aria-hidden="true"></span><span class="cx-orbit-star4" aria-hidden="true"></span>
                <div class="cx-orbit-core">
                    <svg class="cx-health-ring" viewBox="0 0 160 160" aria-hidden="true"><circle cx="80" cy="80" r="68"></circle><circle cx="80" cy="80" r="68" style="stroke-dashoffset: <?php echo number_format(427.26 * (1 - ($stock_health ?? 0) / 100), 2, '.', ''); ?>"></circle></svg>
                    <div class="cx-orbit-center"><small>STOCK HEALTH</small><strong><?php echo $stock_health === null ? '—' : $stock_health . '%'; ?></strong><span>products at 20+ units</span></div>
                </div>
                <div class="cx-orbit-label label-sales"><span>Sales today</span><strong class="cx-floating-value"><?php echo formatCurrency($today_sales); ?></strong></div>
                <div class="cx-orbit-label label-stock"><span>Need a little attention</span><strong class="cx-floating-value"><?php echo $low_stock_count; ?> low-stock products</strong></div>
                <span class="cx-orbit-caption"><?php echo $healthy_stock_count; ?> of <?php echo $total_products; ?> products have 20+ units · At page load</span>
            </div>
        </section>

        <section class="cx-kpi-grid" aria-label="Your business at a glance">
            <article class="cx-kpi-card"><span class="cx-kpi-icon"><i data-lucide="receipt" aria-hidden="true"></i></span><span class="cx-kpi-label">Sales today</span><strong class="cx-kpi-value"><?php echo formatCurrency($today_sales); ?></strong><span class="cx-kpi-foot"><?php echo $today_bill_count; ?> recorded invoices · <?php echo date('d M'); ?></span></article>
            <article class="cx-kpi-card is-lilac"><span class="cx-kpi-icon"><i data-lucide="trending-up" aria-hidden="true"></i></span><span class="cx-kpi-label"><?php echo date('F'); ?> sales</span><strong class="cx-kpi-value"><?php echo formatCurrency($current_month_sales); ?></strong><span class="cx-kpi-foot">Month to date · Recorded sales</span></article>
            <article class="cx-kpi-card is-yellow"><span class="cx-kpi-icon"><i data-lucide="boxes" aria-hidden="true"></i></span><span class="cx-kpi-label">Inventory at cost</span><strong class="cx-kpi-value"><?php echo formatCurrency($inventory_snapshot['cost_value']); ?></strong><span class="cx-kpi-foot">Current quantities × purchase rates</span></article>
            <article class="cx-kpi-card is-mint"><span class="cx-kpi-icon"><i data-lucide="wallet" aria-hidden="true"></i></span><span class="cx-kpi-label">Open supplier bills</span><strong class="cx-kpi-value"><?php echo $unpaid_bill_count; ?></strong><span class="cx-kpi-foot">Bills with a remaining balance</span></article>
        </section>

        <!-- Kinetic strip of figures already shown on this page; decorative, so hidden from assistive tech. -->
        <section class="cx-marquee" aria-hidden="true">
            <span class="cx-mq-badge"><span class="cx-mq-live"></span>Snapshot · <?php echo date('H:i'); ?></span>
            <div class="cx-marquee-row"><div class="cx-marquee-track"><div class="cx-marquee-group">
                <span class="cx-cap is-mint"><span class="cx-cap-tag"><i data-lucide="receipt"></i>Sold today</span><span class="cx-cap-value"><?php echo formatCurrency($today_sales); ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-lilac"><span class="cx-cap-tag"><i data-lucide="trending-up"></i><?php echo date('F'); ?> sales</span><span class="cx-cap-value"><?php echo formatCurrency($current_month_sales); ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-rose"><span class="cx-cap-tag"><i data-lucide="heart-pulse"></i>Stock health</span><span class="cx-cap-value"><?php echo $stock_health === null ? '—' : $stock_health . '%'; ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-yellow"><span class="cx-cap-tag"><i data-lucide="package"></i>To restock</span><span class="cx-cap-value"><?php echo $low_stock_count; ?> <?php echo $low_stock_count === 1 ? 'product' : 'products'; ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-coral"><span class="cx-cap-tag"><i data-lucide="file-clock"></i>Open bills</span><span class="cx-cap-value"><?php echo $unpaid_bill_count; ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-periwinkle"><span class="cx-cap-tag"><i data-lucide="wallet"></i>Supplier balance</span><span class="cx-cap-value"><?php echo formatCurrency($total_balance); ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-mint"><span class="cx-cap-tag"><i data-lucide="pill"></i>Products</span><span class="cx-cap-value"><?php echo number_format($total_products); ?></span></span><span class="cx-tab"></span>
                <span class="cx-cap is-lilac"><span class="cx-cap-tag"><i data-lucide="users"></i>Customers</span><span class="cx-cap-value"><?php echo number_format($total_customers); ?></span></span><span class="cx-tab"></span>
            </div></div></div>
        </section>

        <div class="ld-grid">
            <p class="cx-print-summary">Stock health: <?php echo $stock_health === null ? 'Not available' : $stock_health . '%'; ?> · <?php echo $low_stock_count; ?> products below 20 units · <?php echo $critical_stock_count; ?> below 10 units.<?php echo $has_demo_records ? ' Demo records are included.' : ''; ?></p>
            <section class="ld-column ld-left" aria-label="Inventory health and business activity">
                <div class="ld-health cx-insight-panel">
                    <span class="cx-section-kicker">02 / The small things</span>
                    <h2>A clear view.<br>A confident next step.</h2>
                    <ul class="cx-insight-list">
                        <li><span><strong class="cx-pulse-number"><?php echo $critical_stock_count; ?></strong> products are below 10 units.</span></li>
                        <li><span><strong class="cx-pulse-number"><?php echo (int) $inventory_snapshot['unavailable']; ?></strong> products have no available stock.</span></li>
                        <li><span><strong class="cx-pulse-number"><?php echo $healthy_stock_count; ?></strong> products are stocked at 20+ units.</span></li>
                    </ul>
                    <div class="po-links"><a href="view_products.php" class="cx-secondary-action">Review your stock <span aria-hidden="true">↗</span></a></div>
                </div>

                <article class="ld-card ld-activity">
                    <div class="ld-card-heading"><h2>Business activity</h2><span class="ld-pill ld-pill-violet">All time</span></div>
                    <p class="ld-card-intro">The people &amp; products behind your shop.</p>
                    <dl class="ld-counts">
                        <div><dt>Products</dt><dd><a href="view_products.php"><?php echo $total_products; ?></a></dd></div>
                        <div><dt>Suppliers</dt><dd><a href="view_companies.php"><?php echo $total_companies; ?></a></dd></div>
                        <div><dt>Customers</dt><dd><a href="view_customers.php"><?php echo $total_customers; ?></a></dd></div>
                    </dl>
                    <div class="ld-card-footer"><span>Explore your inventory</span><a href="inventory_report.php" class="ld-round-link" aria-label="Open inventory report">↗</a></div>
                </article>
                <div class="ld-today"><span class="ld-today-dot" aria-hidden="true"></span><div><span>Sales today</span><strong><?php echo formatCurrency($today_sales); ?></strong><small><?php echo date('d F Y'); ?> · Sales bills recorded today</small></div></div>
            </section>

            <section class="ld-column ld-center" aria-label="Business snapshot and trends">
                <article class="ld-card ld-snapshot">
                    <div class="ld-card-heading"><h2>Business snapshot</h2><span class="ld-pill ld-pill-mint">Overview</span></div>
                    <p class="ld-eyebrow">Outstanding supplier balance</p>
                    <div class="ld-balance"><?php echo formatCurrency($total_balance); ?></div>
                    <dl class="ld-facts">
                        <div><dt>All-time sales</dt><dd><?php echo formatCurrency($total_sales); ?></dd></div>
                        <div><dt>Purchases</dt><dd><?php echo formatCurrency($total_purchases); ?></dd></div>
                        <div><dt>Paid to suppliers</dt><dd><?php echo formatCurrency($total_paid); ?></dd></div>
                    </dl>
                    <div class="ld-card-footer"><a class="ld-subtle-link" href="view_companies.php">Your suppliers <span aria-hidden="true">↗</span></a><a href="create_sales_bill.php" class="ld-black-link">Create a sale <span aria-hidden="true">↗</span></a></div>
                </article>

                <article class="ld-card ld-stock-card">
                    <div class="ld-card-heading"><h2>Stock check</h2><span class="ld-pill <?php echo $low_stock_count ? 'ld-pill-yellow' : 'ld-pill-mint'; ?>"><?php echo $low_stock_count ? 'Restock review' : ($total_products ? 'In good shape' : 'Getting started'); ?></span></div>
                    <div class="ld-stock-summary"><strong><?php echo $low_stock_count; ?></strong><p>products below<br>20 units in stock</p><span class="ld-pill <?php echo $critical_stock_count ? 'ld-pill-coral' : 'ld-pill-mint'; ?>"><?php echo $critical_stock_count; ?> critical</span></div>
                    <div class="ld-card-footer"><span class="ld-note">Critical = fewer than 10 units</span><a href="<?php echo $low_stock_count ? 'create_purchase_bill.php' : 'inventory_report.php'; ?>" class="ld-text-link"><?php echo $low_stock_count ? 'Add purchase' : 'View inventory'; ?> <span aria-hidden="true">↗</span></a></div>
                </article>

                <article class="ld-card ld-chart-card">
                    <div class="ld-card-heading"><h2>Business rhythm</h2><div class="cx-chart-controls" role="group" aria-label="Chart period" hidden><button type="button" data-chart-months="3" aria-pressed="false">3M</button><button type="button" data-chart-months="6" aria-pressed="true">6M</button></div></div>
                    <p class="ld-chart-caption">Sales &amp; purchases · Pakistani rupees</p>
                    <div class="cx-chart-summary"><div><span id="cxTrendSalesLabel">Sales · 6 months</span><strong id="cxTrendSales"><?php echo formatCurrency(array_sum($sales_by_month)); ?></strong></div><div><span id="cxTrendPurchasesLabel">Purchases · 6 months</span><strong id="cxTrendPurchases"><?php echo formatCurrency(array_sum($purchases_by_month)); ?></strong></div></div>
                    <div id="trendChart" class="ld-chart" role="img" aria-label="Monthly sales and purchases; exact amounts are in the table below." hidden></div>
                    <details id="trendFallback" class="ld-trend-data" open>
                        <summary>Monthly sales &amp; purchases data</summary>
                        <div class="ld-table-scroll"><table class="ld-data-table"><caption class="ld-sr-only">Actual totals for the last six calendar months, including this month</caption><thead><tr><th scope="col">Month</th><th scope="col">Sales</th><th scope="col">Purchases</th></tr></thead><tbody>
                        <?php foreach ($months as $key => $label): ?>
                            <tr data-trend-row><th scope="row"><?php echo $label; ?></th><td><?php echo formatCurrency($sales_by_month[$key]); ?></td><td><?php echo formatCurrency($purchases_by_month[$key]); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    </details>
                </article>
            </section>

            <aside class="ld-column ld-right" aria-label="Quick actions and suppliers">
                <section class="ld-quick-actions">
                    <div class="ld-actions-heading"><h2>Quick actions</h2><span class="ld-flower" aria-hidden="true"></span></div>
                    <a href="create_sales_bill.php" class="ld-action-row"><span class="ld-action-icon ld-icon-mint"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><span>Create sale<small>Make a new sales bill</small></span><span class="ld-action-arrow" aria-hidden="true">↗</span></a>
                    <a href="create_purchase_bill.php" class="ld-action-row"><span class="ld-action-icon ld-icon-yellow"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></span><span>Add purchase<small>Bring new stock in</small></span><span class="ld-action-arrow" aria-hidden="true">↗</span></a>
                    <a href="add_customer.php" class="ld-action-row"><span class="ld-action-icon ld-icon-coral"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span><span>Add customer<small>Grow your customer book</small></span><span class="ld-action-arrow" aria-hidden="true">↗</span></a>
                    <a href="inventory_report.php" class="ld-action-row"><span class="ld-action-icon ld-icon-violet"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span><span>Inventory report<small>See every product &amp; quantity</small></span><span class="ld-action-arrow" aria-hidden="true">↗</span></a>
                </section>

                <article class="ld-card ld-supplier-balance">
                    <div class="ld-card-heading"><h2>Supplier balance</h2><span class="ld-mini-orbit" aria-hidden="true">✳</span></div>
                    <p>Keep your next payment in view.</p>
                    <strong class="ld-supplier-amount"><?php echo formatCurrency($total_balance); ?></strong>
                    <div class="ld-abstract" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                    <a href="view_purchase_bills.php" class="ld-lavender-link">View purchase bills <span aria-hidden="true">↗</span></a>
                </article>

                <section class="ld-suppliers">
                    <div class="ld-card-heading"><h2>Top suppliers</h2><a href="view_companies.php" class="ld-subtle-link">View all ↗</a></div>
                    <p class="ld-note">By all-time purchase value</p>
                    <?php if ($top_suppliers): foreach ($top_suppliers as $supplier): ?>
                    <a class="ld-supplier-row" href="company_details.php?id=<?php echo (int) $supplier['id']; ?>"><span><?php echo htmlspecialchars($supplier['company_name'], ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo formatCurrency($supplier['amt']); ?></strong></a>
                    <?php endforeach; else: ?>
                    <p class="ld-note">No supplier purchases recorded yet.</p>
                    <?php endif; ?>
                </section>
            </aside>
        </div>

        <section class="cx-insight-strip" aria-label="A little more perspective">
            <article class="cx-insight-tile"><span class="cx-insight-label">Supplier purchases paid</span><strong><?php echo number_format($paid_percentage, 1); ?>%</strong><p>Paid amounts ÷ recorded supplier purchases. Not a profit measure.</p></article>
            <article class="cx-insight-tile"><span class="cx-insight-label"><?php echo date('F', strtotime('-1 month', $month_start)); ?> sales</span><strong><?php echo formatCurrency($previous_month_sales); ?></strong><p>Full previous calendar month. This month’s KPI is month to date.</p></article>
            <article class="cx-insight-tile"><span class="cx-insight-label">Recorded customer invoices</span><strong><?php echo number_format($sales_bill_count); ?></strong><p>All-time sales invoices across your customer book.</p></article>
        </section>

        <section class="ld-card ld-transactions" aria-labelledby="transactionsHeading">
            <div class="ld-transactions-heading"><div><h2 id="transactionsHeading">Recent transactions</h2><p class="ld-note">Your latest eight sales &amp; purchases, together.</p></div><div class="ld-filter-pills" role="group" aria-label="Filter recent transactions" hidden><button type="button" class="filter-pill active" data-filter="all" aria-pressed="true">All</button><button type="button" class="filter-pill" data-filter="Sale" aria-pressed="false">Sales</button><button type="button" class="filter-pill" data-filter="Purchase" aria-pressed="false">Purchases</button></div></div>
            <div class="cx-table-toolbar" hidden>
                <div class="cx-search-field"><i data-lucide="search" aria-hidden="true"></i><label class="ld-sr-only" for="cxTxnSearch">Search the latest eight transactions</label><input type="search" id="cxTxnSearch" placeholder="Find a reference, customer or supplier…" autocomplete="off"></div>
                <div class="cx-sort-field"><label for="cxTxnSort">Sort</label><select id="cxTxnSort"><option value="newest">Newest first</option><option value="amount-desc">Amount: high to low</option><option value="amount-asc">Amount: low to high</option></select></div>
            </div>
            <div class="ld-table-scroll">
                <table class="ld-data-table ld-transaction-table" id="txnTable">
                    <caption class="ld-sr-only">Recent transactions with links to bill details</caption>
                    <thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col">Customer / supplier</th><th scope="col">Type</th><th scope="col" class="ld-align-right">Amount</th><th scope="col">Status</th><th scope="col"><span class="ld-sr-only">View bill</span></th></tr></thead>
                    <tbody>
                    <?php if ($recent_txns->num_rows > 0): while ($txn = $recent_txns->fetch_assoc()):
                        $detail = $txn['type'] === 'Sale' ? 'sales_bill_detail.php' : 'purchase_bill_detail.php';
                        $detail_url = $detail . '?id=' . (int) $txn['ref_id'];
                    ?>
                        <tr data-type="<?php echo $txn['type']; ?>" data-amount="<?php echo number_format((float) $txn['amount'], 2, '.', ''); ?>"><td><?php echo formatDate($txn['d']); ?></td><td><a href="<?php echo $detail_url; ?>"><?php echo htmlspecialchars($txn['ref'], ENT_QUOTES, 'UTF-8'); ?></a></td><td><?php echo htmlspecialchars($txn['party'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td><td><span class="ld-pill <?php echo $txn['type'] === 'Sale' ? 'ld-pill-mint' : 'ld-pill-yellow'; ?>"><?php echo $txn['type']; ?></span></td><td class="ld-align-right"><?php echo formatCurrency($txn['amount']); ?></td><td><span class="ld-pill <?php echo $txn['status_label'] === 'Paid' ? 'ld-pill-mint' : 'ld-pill-coral'; ?>"><?php echo $txn['status_label']; ?></span></td><td><a href="<?php echo $detail_url; ?>" class="ld-bill-link" aria-label="View <?php echo htmlspecialchars($txn['ref'], ENT_QUOTES, 'UTF-8'); ?>">↗</a></td></tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="ld-empty">No transactions yet. <a href="create_sales_bill.php">Create your first sale ↗</a></td></tr>
                    <?php endif; ?>
                        <tr id="txnFilterEmpty" hidden><td colspan="7" class="ld-empty">No matching transactions in the latest eight records.</td></tr>
                    </tbody>
                </table>
            </div>
            <p id="txnFilterStatus" class="ld-sr-only" aria-live="polite"></p>
            <div class="ld-transaction-links"><a href="view_sales_bills.php" class="ld-text-link">All sales ↗</a><a href="view_purchase_bills.php" class="ld-text-link">All purchases ↗</a></div>
        </section>

        <?php if ($low_stock_count > 0): ?>
        <section class="ld-card ld-low-stock" aria-labelledby="lowStockHeading">
            <div class="ld-card-heading"><div><h2 id="lowStockHeading">A little stock attention</h2><p class="ld-note">Lowest stock first · Showing <?php echo $low_stock->num_rows; ?> of <?php echo $low_stock_count; ?> products below 20 units</p></div><a href="view_products.php" class="ld-text-link">All products ↗</a></div>
            <?php while ($product = $low_stock->fetch_assoc()): ?>
            <div class="ld-low-stock-row"><div><a href="edit_product.php?id=<?php echo (int) $product['id']; ?>"><?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?></a><small><?php echo htmlspecialchars($product['composition'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div><span class="ld-pill <?php echo $product['current_stock'] < 10 ? 'ld-pill-coral' : 'ld-pill-yellow'; ?>"><?php echo (int) $product['current_stock']; ?> units left</span><a href="create_purchase_bill.php" class="ld-text-link">Restock ↗</a></div>
            <?php endwhile; ?>
        </section>
        <?php endif; ?>
        <footer class="cx-dashboard-footer"><p>Built around your business, not the noise. Figures reflect the database when this page loaded.<?php echo $has_demo_records ? ' Demo records are included in these totals.' : ''; ?></p><a href="inventory_report_pdf.php" class="cx-secondary-action" target="_blank" rel="noopener">Open inventory print preview <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></footer>
    </main>

    <!-- Motion runs before the CDN script so a slow CDN cannot hold the page behind the motion guard. -->
    <script defer src="js/vendor/gsap-3.15.0.min.js"></script>
    <script defer src="js/vendor/gsap-scrolltrigger-3.15.0.min.js"></script>
    <script defer src="js/vendor/gsap-splittext-3.15.0.min.js"></script>
    <script defer src="js/vendor/gsap-scrambletext-3.15.0.min.js"></script>
    <script defer src="js/vendor/lenis-1.3.27.min.js"></script>
    <script defer src="js/dashboard-motion.js?v=<?php echo filemtime(__DIR__ . '/js/dashboard-motion.js'); ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="application/json" id="cxDashboardData"><?php echo json_encode($dashboard_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script defer src="js/dashboard-studio.js?v=<?php echo filemtime(__DIR__ . '/js/dashboard-studio.js'); ?>"></script>
    <script type="module" src="js/dashboard-pills.js?v=<?php echo filemtime(__DIR__ . '/js/dashboard-pills.js'); ?>"></script>
</body>
</html>
