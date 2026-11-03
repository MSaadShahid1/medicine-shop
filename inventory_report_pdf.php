<?php
require_once __DIR__ . '/print_controls.php';
require_once __DIR__ . '/config.php';

// The report screen sends its product/composition/company search through GET.
// Filter before calculating totals; a plain preview includes all products.
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$result = $conn->query("SELECT p.*, c.company_name FROM products p
    LEFT JOIN companies c ON p.company_id = c.id ORDER BY p.product_name ASC");
$total_products = 0;
$low_stock_count = 0;
$total_purchase_value = 0;
$total_sales_value = 0;
$products_data = [];
while ($row = $result->fetch_assoc()) {
    $productText = ($row['product_name'] ?? '') . ' ' . ($row['composition'] ?? '');
    if ($search !== '' && mb_stripos($productText, $search, 0, 'UTF-8') === false
        && mb_stripos($row['company_name'] ?? '', $search, 0, 'UTF-8') === false) {
        continue;
    }
    $total_products++;
    if ($row['current_stock'] < 20) {
        $low_stock_count++;
    }
    $total_purchase_value += $row['purchase_rate'] * $row['current_stock'];
    $total_sales_value += $row['sale_rate'] * $row['current_stock'];
    $products_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory report — <?php echo date('d M Y'); ?> — Medicine Shop</title>
    <?php include __DIR__ . '/pdf_style.php'; ?>
</head>
<body class="document-landscape">
    <?php documentToolbar('inventory_report.php', 'Back to report', true); ?>
    <main class="document">
        <header class="document-header">
            <?php documentBrand(); ?>
            <div class="document-heading">
                <p class="eyebrow">Stock &amp; valuation</p>
                <h1>Inventory report</h1>
                <dl class="document-meta">
                    <div><dt>Report date</dt><dd><?php echo date('d M Y'); ?></dd></div>
                    <div><dt>Generated</dt><dd><?php echo date('h:i A'); ?></dd></div>
                </dl>
            </div>
        </header>
        <p class="scope-note"><strong>Report scope:</strong> <?php echo $search !== '' ? 'Search: ' . documentEscape($search) : 'All inventory'; ?>. Values reflect current recorded stock.</p>
        <section class="report-summary" aria-label="Inventory summary">
            <div class="summary-card"><h2>Total products</h2><p><?php echo $total_products; ?></p></div>
            <div class="summary-card"><h2>Low stock · below 20</h2><p><?php echo $low_stock_count; ?></p></div>
            <div class="summary-card"><h2>Cost value</h2><p><?php echo documentEscape(formatCurrency($total_purchase_value)); ?></p></div>
            <div class="summary-card"><h2>Sales value</h2><p><?php echo documentEscape(formatCurrency($total_sales_value)); ?></p></div>
        </section>
        <h2 class="section-heading">Detailed stock list <small> / Amounts in Rs.</small></h2>
        <div class="table-scroll" role="region" aria-label="Inventory table" tabindex="0">
            <table class="inventory-table" aria-label="Inventory stock and cost valuation">
                <colgroup><col class="col-index"><col class="col-product"><col class="col-composition"><col class="col-company"><col class="col-stock"><col class="col-cost"><col class="col-value"></colgroup>
                <thead><tr><th scope="col">#</th><th scope="col">Product</th><th scope="col">Composition</th><th scope="col">Company / supplier</th><th scope="col" class="quantity">Stock</th><th scope="col" class="numeric">Unit cost</th><th scope="col" class="numeric">Cost value</th></tr></thead>
                <tbody>
                    <?php $count = 1; foreach ($products_data as $row): $is_low = $row['current_stock'] < 20; ?>
                    <tr<?php echo $is_low ? ' class="low-stock-row"' : ''; ?>>
                        <td><?php echo $count++; ?></td>
                        <td class="item-name"><?php echo documentEscape($row['product_name']); ?></td>
                        <td><?php echo documentEscape($row['composition']); ?></td>
                        <td><?php echo documentEscape($row['company_name']); ?></td>
                        <td class="quantity"><?php echo documentEscape($row['current_stock']); ?><?php if ($is_low): ?><span class="stock-label">Low stock</span><?php endif; ?></td>
                        <td class="numeric"><?php echo number_format($row['purchase_rate'], 2); ?></td>
                        <td class="numeric"><?php echo number_format($row['current_stock'] * $row['purchase_rate'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$products_data): ?><tr><td colspan="7" class="empty-state">No inventory data found<?php echo $search !== '' ? ' for this search' : ''; ?>.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="document-note">Low stock: fewer than 20 units. Cost value = current stock × purchase rate. Sales value = current stock × sale rate.</p>
        <section class="totals-block" aria-label="Inventory totals">
            <table class="totals-table"><tbody>
                <tr><th scope="row">Total products</th><td class="numeric"><?php echo $total_products; ?></td></tr>
                <tr><th scope="row">Low stock products</th><td class="numeric"><?php echo $low_stock_count; ?></td></tr>
                <tr><th scope="row">Total sales value</th><td class="numeric"><?php echo documentEscape(formatCurrency($total_sales_value)); ?></td></tr>
                <tr class="grand-total"><th scope="row">Total cost value</th><td class="numeric"><?php echo documentEscape(formatCurrency($total_purchase_value)); ?></td></tr>
            </tbody></table>
        </section>
        <?php documentFooter('Inventory report'); ?>
    </main>
</body>
</html>
