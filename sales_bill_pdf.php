<?php
require_once __DIR__ . '/print_controls.php';
$id = documentBillId('view_sales_bills.php', 'Back to sales bills');
require_once __DIR__ . '/config.php';

$result_bill = $conn->query("SELECT * FROM sales_bills WHERE id = $id");
$bill = $result_bill->fetch_assoc();
if (!$bill) {
    documentError(404, 'Sales bill not found.', 'view_sales_bills.php', 'Back to sales bills');
}
$result_items = $conn->query("SELECT sbi.*, p.product_name, p.composition
    FROM sales_bill_items sbi LEFT JOIN products p ON sbi.product_id = p.id
    WHERE sbi.sales_bill_id = $id ORDER BY sbi.id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales invoice — <?php echo documentEscape($bill['bill_number']); ?> — Medicine Shop</title>
    <?php include __DIR__ . '/pdf_style.php'; ?>
</head>
<body>
    <?php documentToolbar('sales_bill_detail.php?id=' . $id, 'Back to bill'); ?>
    <main class="document">
        <header class="document-header">
            <?php documentBrand(); ?>
            <div class="document-heading">
                <p class="eyebrow">Sales document</p>
                <h1>Invoice</h1>
                <dl class="document-meta">
                    <div><dt>Reference</dt><dd><?php echo documentEscape($bill['bill_number']); ?></dd></div>
                    <div><dt>Bill date</dt><dd><?php echo documentEscape(documentDate($bill['bill_date'])); ?></dd></div>
                </dl>
            </div>
        </header>
        <section class="party-grid" aria-label="Invoice details">
            <div class="party-card">
                <h2>Bill to</h2>
                <p class="party-name"><?php echo documentEscape($bill['customer_name']); ?></p>
                <?php if (!empty($bill['customer_phone'])): ?><p>Phone: <?php echo documentEscape($bill['customer_phone']); ?></p><?php endif; ?>
                <?php if (!empty($bill['customer_address'])): ?><p><?php echo nl2br(documentEscape($bill['customer_address'])); ?></p><?php endif; ?>
            </div>
            <div class="party-card record-card">
                <h2>Invoice amount</h2>
                <p class="party-name"><?php echo documentEscape(formatCurrency($bill['total_amount'])); ?></p>
                <p>All rates and amounts in Rs.</p>
            </div>
        </section>
        <h2 class="section-heading">Invoice items <small> / <?php echo $result_items->num_rows; ?> recorded</small></h2>
        <div class="table-scroll" role="region" aria-label="Invoice items" tabindex="0">
            <table class="items-table" aria-label="Sales invoice items">
                <colgroup><col class="col-index"><col class="col-description"><col class="col-quantity"><col class="col-rate"><col class="col-total"></colgroup>
                <thead><tr><th scope="col">#</th><th scope="col">Description</th><th scope="col" class="quantity">Qty</th><th scope="col" class="numeric">Unit rate</th><th scope="col" class="numeric">Amount</th></tr></thead>
                <tbody>
                    <?php $count = 1; while ($item = $result_items->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><span class="item-name"><?php echo documentEscape($item['product_name'] ?? 'Product no longer available'); ?></span>
                            <?php if (!empty($item['composition'])): ?><span class="item-detail"><?php echo documentEscape($item['composition']); ?></span><?php endif; ?></td>
                        <td class="quantity"><?php echo documentEscape($item['quantity']); ?></td>
                        <td class="numeric"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="numeric"><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($result_items->num_rows === 0): ?><tr><td colspan="5" class="empty-state">No line items recorded for this bill.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <section class="totals-block" aria-label="Invoice total">
            <table class="totals-table"><tbody><tr class="grand-total"><th scope="row">Total amount</th><td class="numeric"><?php echo documentEscape(formatCurrency($bill['total_amount'])); ?></td></tr></tbody></table>
        </section>
        <p class="document-note">Thank you for your business.</p>
        <?php documentFooter('Sales invoice'); ?>
    </main>
</body>
</html>
