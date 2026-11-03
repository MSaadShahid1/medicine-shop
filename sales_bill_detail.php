<?php
require_once 'config.php';
require_once 'page_overview.php';

// Set Timezone to Pakistan
date_default_timezone_set('Asia/Karachi');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: view_sales_bills.php");
    exit();
}

// Fetch Bill Header
$sql_bill = "SELECT * FROM sales_bills WHERE id = $id";
$result_bill = $conn->query($sql_bill);

if ($result_bill->num_rows == 0) {
    echo "Bill not found!";
    exit();
}

$bill = $result_bill->fetch_assoc();

// Fetch Bill Items
$sql_items = "SELECT sbi.*, p.product_name, p.composition 
              FROM sales_bill_items sbi 
              JOIN products p ON sbi.product_id = p.id 
              WHERE sbi.sales_bill_id = $id";
$result_items = $conn->query($sql_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Details - <?php echo $bill['bill_number']; ?></title>
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
                    <h2><i class="fas fa-file-invoice me-2"></i>Bill Details</h2>
                    <p>Invoice #<?php echo $bill['bill_number']; ?></p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="create_sales_bill.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                    <i class="fas fa-plus me-1"></i> New Bill
                </a>
                <a href="sales_bill_pdf.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-print me-1"></i> Print / PDF
                </a>
            </div>
        </div>

        <?php
        $salesLinks = [['label' => 'Sales history', 'href' => 'view_sales_bills.php', 'icon' => 'history']];
        if (!empty($bill['customer_id'])) {
            $salesLinks[] = ['label' => 'Customer ledger', 'href' => 'customer_ledger.php?id=' . (int)$bill['customer_id'], 'icon' => 'user'];
        }
        renderPageOverview([
            'eyebrow' => 'Sales invoice · ' . $bill['bill_number'], 'title' => 'The sale, clearly itemised.',
            'description' => 'An original invoice for ' . $bill['customer_name'] . '. Sales are paid in full at creation; the amount below is the stored bill total.',
            'tone' => 'yellow',
            'metrics' => [
                ['label' => 'Invoice total', 'value' => formatCurrency($bill['total_amount']), 'note' => 'Original recorded value'],
                ['label' => 'Product lines', 'value' => number_format($result_items->num_rows)],
                ['label' => 'Invoice date', 'value' => formatDate($bill['bill_date'])]
            ], 'links' => $salesLinks
        ]); ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'created'): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> Bill created successfully! Stock has been updated.
        </div>
        <?php endif; ?>

        <!-- Bill Information -->
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="content-card bp-info-card bp-lavender">
                    <div class="section-header">
                        <i class="fas fa-user-circle"></i>
                        <h5>Customer Info</h5>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Name</span>
                        <div class="info-value"><?php echo poEscape($bill['customer_name']); ?></div>
                    </div>
                    <div>
                        <span class="info-label">Contact</span>
                        <div class="info-value">
                            <?php if($bill['customer_phone']): ?>
                                <i class="fas fa-phone-alt text-muted me-1" style="font-size: 0.9rem;"></i> <?php echo $bill['customer_phone']; ?>
                            <?php else: ?>
                                <span class="text-muted fw-normal">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
    <div class="content-card bp-info-card bp-mint">
        <div class="section-header">
            <i class="fas fa-info-circle"></i>
            <h5>Invoice Summary</h5>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3 mb-md-0">
                <span class="info-label">Bill Number</span>
                <div class="info-value text-primary">
                    <?php echo $bill['bill_number']; ?>
                </div>
            </div>

            <div class="col-md-4 mb-3 mb-md-0">
                <span class="info-label">Bill Date</span>
                <div class="info-value">
                    <?php
                        $dateObj = new DateTime($bill['bill_date']);

                        // Check if time exists in DB value
                        if (strpos($bill['bill_date'], ':') !== false) {
                            echo $dateObj->format('d M Y, h:i A');
                        } else {
                            echo $dateObj->format('d M Y');
                        }
                    ?>
                </div>
            </div>

            <div class="col-md-4">
                <span class="info-label">Total Amount</span>
                <div class="info-value text-success">
                    <?php echo formatCurrency($bill['total_amount']); ?>
                </div>
            </div>
        </div>
    </div>
</div>
    </div><!-- /Bill information row -->

        <!-- Items Table -->
        <div class="content-card mt-4 p-0 overflow-hidden">
            <div class="p-4 border-bottom border-light">
                <span class="po-eyebrow">Invoice contents</span>
                <h5 class="fw-bold text-secondary mb-1"><i class="fas fa-box-open me-2"></i> Items sold</h5>
                <p class="bp-caption mb-0"><?php echo number_format($result_items->num_rows); ?> product lines · Quantities and rates as recorded on this invoice.</p>
            </div>
            <div class="card-body-custom p-4">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Product Details</th>
                                <th class="text-center">Rate</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_items->num_rows === 0): ?>
                            <tr><td colspan="5" class="po-empty text-center p-4">No linked item details are available for this invoice. The original stored bill total is shown below.</td></tr>
                            <?php endif; ?>
                            <?php 
                            $count = 1;
                            while($item = $result_items->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="text-muted ps-4"><?php echo $count++; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo $item['product_name']; ?></div>
                                    <small class="text-muted"><?php echo $item['composition']; ?></small>
                                </td>
                                <td class="text-center text-secondary"><?php echo number_format($item['rate'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-3"><?php echo $item['quantity']; ?></span>
                                </td>
                                <td class="text-end fw-bold pe-4"><?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            
                            <!-- Grand Total Row -->
                            <tr class="bg-light">
                                <td colspan="4" class="text-end py-3 pe-4 text-secondary text-uppercase fw-bold" style="letter-spacing: 1px;">Grand Total</td>
                                <td class="text-end py-3 pe-4"><strong class="text-primary fs-5"><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>