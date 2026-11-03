<?php
require_once 'config.php';
require_once 'page_overview.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: view_purchase_bills.php");
    exit();
}

// Fetch Bill Header with Company Info
$sql_bill = "SELECT pb.*, c.company_name, c.contact_person, c.phone as company_phone 
             FROM purchase_bills pb 
             JOIN companies c ON pb.company_id = c.id 
             WHERE pb.id = $id";
$result_bill = $conn->query($sql_bill);

if ($result_bill->num_rows == 0) {
    echo "Bill not found!";
    exit();
}

$bill = $result_bill->fetch_assoc();

// Fetch Bill Items
$sql_items = "SELECT pbi.*, p.product_name, p.composition 
              FROM purchase_bill_items pbi 
              JOIN products p ON pbi.product_id = p.id 
              WHERE pbi.purchase_bill_id = $id";
$result_items = $conn->query($sql_items);

// Fetch Payments
$sql_payments = "SELECT * FROM payments WHERE purchase_bill_id = $id ORDER BY payment_date DESC";
$result_payments = $conn->query($sql_payments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Details - <?php echo $bill['bill_number']; ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="bp-page bp-detail">
        
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"></i>
                <div>
                    <h2><i class="fas fa-file-invoice-dollar me-2"></i>Purchase Details</h2>
                    <p>Bill #<?php echo $bill['bill_number']; ?></p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="view_purchase_bills.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>

                <?php if ($bill['balance'] > 0): ?>
                <a href="add_payment.php?bill_id=<?php echo $id; ?>" class="btn btn-success px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-wallet me-1"></i> Add Payment
                </a>
                <?php endif; ?>

                <a href="purchase_bill_pdf.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-print me-1"></i> Print / PDF
                </a>
            </div>
        </div>

        <?php renderPageOverview([
            'eyebrow' => 'Supplier invoice · ' . $bill['bill_number'], 'title' => $bill['balance'] > 0 ? 'A clear path to settled.' : 'This invoice is settled.',
            'description' => 'Purchase from ' . $bill['company_name'] . ' on ' . formatDate($bill['bill_date']) . '. Original bill values and payments remain separate, so every balance has a trail.',
            'tone' => 'lavender',
            'metrics' => [
                ['label' => 'Original bill', 'value' => formatCurrency($bill['total_amount'])],
                ['label' => 'Paid to date', 'value' => formatCurrency($bill['paid_amount'])],
                ['label' => 'Remaining balance', 'value' => formatCurrency($bill['balance']), 'note' => $bill['balance'] > 0 ? 'Available for payment' : 'No payment due']
            ],
            'links' => [['label' => 'Supplier account', 'href' => 'company_details.php?id=' . (int)$bill['company_id'], 'icon' => 'building-2'], ['label' => 'Purchase history', 'href' => 'view_purchase_bills.php', 'icon' => 'history']]
        ]); ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'payment_added'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> Payment recorded successfully!
        </div>
        <?php endif; ?>

        <!-- Bill Information -->
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="content-card bp-info-card bp-lavender">
                    <div class="section-header">
                        <i class="fas fa-building"></i>
                        <h5>Supplier Info</h5>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Company</span>
                        <div class="info-value"><?php echo poEscape($bill['company_name']); ?></div>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Contact Person</span>
                        <div class="info-value text-secondary">
                            <i class="fas fa-user me-1 text-muted small"></i> 
                            <?php echo $bill['contact_person'] ? $bill['contact_person'] : 'N/A'; ?>
                        </div>
                    </div>
                    <div>
                        <span class="info-label">Phone</span>
                        <div class="info-value text-secondary">
                            <i class="fas fa-phone-alt me-1 text-muted small"></i> 
                            <?php echo $bill['company_phone'] ? $bill['company_phone'] : 'N/A'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="content-card bp-info-card bp-yellow">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h5>Bill Summary</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <span class="info-label">Bill Number</span>
                            <div class="info-value text-primary"><?php echo $bill['bill_number']; ?></div>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <span class="info-label">Purchase Date</span>
                            <div class="info-value"><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></div>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <span class="info-label">Total Amount</span>
                            <div class="info-value text-success"><?php echo formatCurrency($bill['total_amount']); ?></div>
                        </div>
                        <div class="col-md-3">
                            <span class="info-label">Status</span>
                            <div class="info-value">
                                <?php if ($bill['balance'] <= 0): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2 shadow-sm fw-bold" style="font-size: 0.8rem;">Fully Paid</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm fw-bold" style="font-size: 0.8rem;">Due: <?php echo formatCurrency($bill['balance']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="content-card mt-4 p-0 overflow-hidden">
            <div class="p-4 border-bottom border-light">
                <span class="po-eyebrow">Incoming stock</span>
                <h5 class="fw-bold text-secondary mb-1"><i class="fas fa-box-open me-2"></i> Purchased Items</h5>
                <p class="bp-caption mb-0"><?php echo number_format($result_items->num_rows); ?> product lines · Recorded purchase quantities and unit costs.</p>
            </div>
            <div class="card-body-custom p-4">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Product Details</th>
                                <th>Composition</th>
                                <th class="text-center">Rate</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_items->num_rows === 0): ?>
                            <tr><td colspan="6" class="po-empty text-center p-4">No product lines are linked to this record. Opening balances can carry an amount without item details; the original bill total remains below.</td></tr>
                            <?php endif; ?>
                            <?php 
                            $count = 1;
                            while($item = $result_items->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="text-muted ps-4"><?php echo $count++; ?></td>
                                <td><div class="fw-bold text-dark"><?php echo $item['product_name']; ?></div></td>
                                <td class="text-muted small"><?php echo $item['composition']; ?></td>
                                <td class="text-center text-secondary"><?php echo number_format($item['rate'], 2); ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border px-3"><?php echo $item['quantity']; ?></span></td>
                                <td class="text-end fw-bold pe-4"><?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            
                            <tr class="bg-light">
                                <td colspan="5" class="text-end py-3 pe-4 text-secondary text-uppercase fw-bold" style="letter-spacing: 1px;">Total Bill Amount</td>
                                <td class="text-end py-3 pe-4"><strong class="text-primary fs-5"><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="content-card mt-4 p-0 overflow-hidden">
            <div class="p-4 border-bottom border-light">
                <span class="po-eyebrow">Settlement trail</span>
                <h5 class="fw-bold text-secondary mb-1"><i class="fas fa-history me-2"></i> Payment History</h5>
                <p class="bp-caption mb-0"><?php echo number_format($result_payments->num_rows); ?> recorded payments · Includes any initial payment logged at purchase.</p>
            </div>
            <div class="card-body-custom p-4">
                <?php if ($result_payments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Notes</th>
                                <th class="text-end">Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($pay = $result_payments->fetch_assoc()): ?>
                            <tr>
                                <td class="text-secondary"><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                                <td class="text-muted small">
                                    <?php echo $pay['notes']; ?>
                                    <span class="badge bg-light text-secondary ms-2 border">Ref: #<?php echo $pay['id']; ?></span>
                                </td>
                                <td class="text-end fw-bold text-success pe-4"><?php echo formatCurrency($pay['amount']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="bg-light">
                                <td colspan="2" class="text-end py-3 pe-4 text-secondary fw-bold">Total Paid</td>
                                <td class="text-end py-3 pe-4"><strong class="text-success"><?php echo formatCurrency($bill['paid_amount']); ?></strong></td>
                            </tr>
                            <tr class="bg-light border-top">
                                <td colspan="2" class="text-end py-3 pe-4 text-secondary fw-bold">Balance Due</td>
                                <td class="text-end py-3 pe-4"><strong class="text-danger"><?php echo formatCurrency($bill['balance']); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="po-empty text-center p-5 text-muted">
                        <i class="fas fa-wallet fa-3x mb-3 text-secondary" style="opacity: 0.3;"></i>
                        <h6>No payments recorded yet</h6>
                        <p class="bp-caption">This history shows individual payment entries. The invoice summary above retains its stored paid and balance amounts.</p>
                        <?php if ($bill['balance'] > 0): ?>
                            <a href="add_payment.php?bill_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-success mt-2 rounded-pill px-3">Record First Payment</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>