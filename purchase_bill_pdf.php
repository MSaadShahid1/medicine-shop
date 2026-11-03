<?php
require_once __DIR__ . '/print_controls.php';
$id = documentBillId('view_purchase_bills.php', 'Back to purchase bills');
require_once __DIR__ . '/config.php';

$result_bill = $conn->query("SELECT pb.*, c.company_name, c.contact_person, c.phone, c.address
    FROM purchase_bills pb LEFT JOIN companies c ON pb.company_id = c.id WHERE pb.id = $id");
$bill = $result_bill->fetch_assoc();
if (!$bill) {
    documentError(404, 'Purchase bill not found.', 'view_purchase_bills.php', 'Back to purchase bills');
}
$result_items = $conn->query("SELECT pbi.*, p.product_name, p.composition
    FROM purchase_bill_items pbi LEFT JOIN products p ON pbi.product_id = p.id
    WHERE pbi.purchase_bill_id = $id ORDER BY pbi.id ASC");
$result_payments = $conn->query("SELECT * FROM payments WHERE purchase_bill_id = $id ORDER BY payment_date ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase bill — <?php echo documentEscape($bill['bill_number']); ?> — Medicine Shop</title>
    <?php include __DIR__ . '/pdf_style.php'; ?>
</head>
<body>
    <?php documentToolbar('purchase_bill_detail.php?id=' . $id, 'Back to bill'); ?>
    <main class="document">
        <header class="document-header">
            <?php documentBrand(); ?>
            <div class="document-heading">
                <p class="eyebrow">Supplier record</p>
                <h1>Purchase bill</h1>
                <dl class="document-meta">
                    <div><dt>Reference</dt><dd><?php echo documentEscape($bill['bill_number']); ?></dd></div>
                    <div><dt>Bill date</dt><dd><?php echo documentEscape(documentDate($bill['bill_date'])); ?></dd></div>
                </dl>
            </div>
        </header>
        <section class="party-grid" aria-label="Purchase details">
            <div class="party-card">
                <h2>Supplier</h2>
                <p class="party-name"><?php echo documentEscape($bill['company_name'] ?? 'Supplier no longer available'); ?></p>
                <?php if (!empty($bill['contact_person'])): ?><p>Contact: <?php echo documentEscape($bill['contact_person']); ?></p><?php endif; ?>
                <?php if (!empty($bill['phone'])): ?><p>Phone: <?php echo documentEscape($bill['phone']); ?></p><?php endif; ?>
                <?php if (!empty($bill['address'])): ?><p><?php echo nl2br(documentEscape($bill['address'])); ?></p><?php endif; ?>
            </div>
            <div class="party-card record-card">
                <h2>Purchase amount</h2>
                <p class="party-name"><?php echo documentEscape(formatCurrency($bill['total_amount'])); ?></p>
                <p>All rates and amounts in Rs.</p>
            </div>
        </section>
        <h2 class="section-heading">Purchased items <small> / <?php echo $result_items->num_rows; ?> recorded</small></h2>
        <div class="table-scroll" role="region" aria-label="Purchased items" tabindex="0">
            <table class="items-table" aria-label="Purchase bill items">
                <colgroup><col class="col-index"><col class="col-description"><col class="col-quantity"><col class="col-rate"><col class="col-total"></colgroup>
                <thead><tr><th scope="col">#</th><th scope="col">Description</th><th scope="col" class="quantity">Qty</th><th scope="col" class="numeric">Unit cost</th><th scope="col" class="numeric">Amount</th></tr></thead>
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
        <section class="totals-block" aria-label="Purchase totals and balance">
            <table class="totals-table"><tbody>
                <tr><th scope="row">Total amount</th><td class="numeric"><?php echo documentEscape(formatCurrency($bill['total_amount'])); ?></td></tr>
                <tr><th scope="row">Paid amount</th><td class="numeric"><?php echo documentEscape(formatCurrency($bill['paid_amount'])); ?></td></tr>
                <tr class="grand-total"><th scope="row">Balance due</th><td class="numeric"><?php echo documentEscape(formatCurrency($bill['balance'])); ?></td></tr>
            </tbody></table>
        </section>
        <h2 class="section-heading">Payment history <small> / <?php echo $result_payments->num_rows; ?> recorded</small></h2>
        <?php if ($result_payments->num_rows > 0): ?>
        <div class="table-scroll" role="region" aria-label="Payment history" tabindex="0">
            <table class="payment-table" aria-label="Recorded payments">
                <colgroup><col class="col-date"><col class="col-notes"><col class="col-amount"></colgroup>
                <thead><tr><th scope="col">Date</th><th scope="col">Notes</th><th scope="col" class="numeric">Amount</th></tr></thead>
                <tbody>
                    <?php while ($payment = $result_payments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo documentEscape(documentDate($payment['payment_date'])); ?></td>
                        <td><?php echo !empty($payment['notes']) ? nl2br(documentEscape($payment['notes'])) : '—'; ?></td>
                        <td class="numeric"><?php echo number_format($payment['amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><p class="document-note">No payment history recorded for this bill.</p><?php endif; ?>
        <?php documentFooter('Purchase record'); ?>
    </main>
</body>
</html>
