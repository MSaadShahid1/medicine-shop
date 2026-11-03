<?php
require_once 'config.php';
require_once 'page_overview.php';

$bill_id = isset($_GET['bill_id']) ? intval($_GET['bill_id']) : 0;
$success_message = '';
$error_message = '';

if ($bill_id == 0) {
    header("Location: view_purchase_bills.php");
    exit();
}

// Fetch Bill Info
$sql = "SELECT pb.*, c.company_name FROM purchase_bills pb 
        JOIN companies c ON pb.company_id = c.id 
        WHERE pb.id = $bill_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Bill not found.");
}

$bill = $result->fetch_assoc();

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // SANITIZATION: Strip commas from the amount input
    $amount = floatval(str_replace(',', '', $_POST['amount']));
    $payment_date = $_POST['payment_date'];
    $notes = $conn->real_escape_string($_POST['notes']);
    
    if ($amount <= 0) {
        $error_message = "Payment amount must be greater than zero.";
    } elseif ($amount > ($bill['balance'] + 0.01)) {
        $error_message = "Payment amount cannot exceed the balance due (Rs. " . number_format($bill['balance'], 2) . ")";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Insert Payment
            $sql_pay = "INSERT INTO payments (purchase_bill_id, amount, payment_date, notes) 
                        VALUES ($bill_id, $amount, '$payment_date', '$notes')";
            if (!$conn->query($sql_pay)) throw new Exception("Error recording payment.");
            
            // 2. Update Bill Balance & Paid Amount
            $sql_update = "UPDATE purchase_bills SET 
                           paid_amount = paid_amount + $amount, 
                           balance = balance - $amount 
                           WHERE id = $bill_id";
            if (!$conn->query($sql_update)) throw new Exception("Error updating bill balance.");
            
            $conn->commit();
            header("Location: purchase_bill_detail.php?id=$bill_id&msg=payment_added");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - <?php echo $bill['bill_number']; ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .bp-payment .header-card { margin-bottom: 24px; }
        .bp-payment .glass-container { padding: 28px; }

        .fin-label { font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 5px; display: block; }
        .fin-value { font-size: 1.25rem; font-weight: 800; color: var(--text-dark); }

        .due-banner h2 { font-size: 2.8rem; font-weight: 900; margin: 5px 0 0; letter-spacing: -1px; }

        .custom-input-group { margin-bottom: 25px; }
        .custom-input-group label { font-weight: 700; color: var(--text-dark); margin-bottom: 10px; font-size: 0.9rem; }

        .form-control-lg {
            border-radius: 15px;
            border: 2px solid var(--border-light);
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #fff;
            color: var(--text-dark);
        }
        .form-control-lg:focus {
            border-color: var(--primary-start);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            background: #fff;
            color: var(--text-dark);
        }

        .currency-input-wrapper { position: relative; }
        .currency-input-wrapper::before {
            content: 'Rs.';
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 800;
            color: var(--text-muted);
            z-index: 5;
        }
        .currency-input-wrapper input { padding-left: 55px !important; font-weight: 800; color: var(--text-dark); }

        .btn-premium {
            background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
            border: none;
            border-radius: 18px;
            padding: 18px 40px;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.28);
        }
        .btn-premium:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(59, 130, 246, 0.35); color: #fff; }

        .btn-cancel { border-radius: 18px; padding: 18px 30px; border: 2px solid var(--border-light); }

        @media (max-width: 991px) {
            .bp-payment .header-card { padding: 24px; }
            .due-banner h2 { font-size: 2rem; }
        }
    </style>
</head>
<body class="bp-page bp-payment">
        
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="page-header-left"><i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i><div><h2>Record supplier payment</h2><p>One payment. A clearer supplier balance.</p></div></div>
            <div class="page-header-actions"><a href="purchase_bill_detail.php?id=<?php echo $bill_id; ?>" class="btn btn-light">Back to invoice</a></div>
        </div>
        <?php renderPageOverview([
            'eyebrow' => 'Supplier settlement', 'title' => $bill['company_name'],
            'description' => 'Payment against ' . $bill['bill_number'] . ' · ' . formatDate($bill['bill_date']) . '. The amount recorded here reduces this invoice’s remaining balance.',
            'tone' => 'yellow',
            'metrics' => [
                ['label' => 'Outstanding balance', 'value' => formatCurrency($bill['balance']), 'note' => 'Maximum payment for this invoice'],
                ['label' => 'Original bill', 'value' => formatCurrency($bill['total_amount'])],
                ['label' => 'Already paid', 'value' => formatCurrency($bill['paid_amount'])]
            ],
            'links' => [['label' => 'Supplier account', 'href' => 'company_details.php?id=' . (int)$bill['company_id'], 'icon' => 'building-2']]
        ]); ?>
        <div class="bp-payment-layout">
                    <div class="content-card bp-payment-card">
                        <div class="bp-section-head"><div><span class="po-eyebrow">Payment details</span><h3>Make the record complete.</h3><p>Choose the payment date and add a reference you can recognise later.</p></div></div>
                        
                        <?php if ($error_message): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-4 px-4 py-3 mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="paymentForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="custom-input-group">
                                        <label for="paymentDate">PAYMENT DATE</label>
                                        <div class="input-group">
                                            <input type="date" class="form-control form-control-lg" id="paymentDate" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="custom-input-group">
                                        <label for="amountInput">AMOUNT TO PAY (RS.)</label>
                                        <div class="currency-input-wrapper">
                                            <!-- Changed to type="text" for comma formatting -->
                                            <input type="text" class="form-control form-control-lg" name="amount" id="amountInput" 
                                                   placeholder="0.00" required oninput="formatCurrencyInput(this)">
                                        </div>
                                        <p class="text-muted small mt-2 ms-1 fw-bold">
                                            <i class="fas fa-info-circle me-1"></i> 
                                            Maximum allowable: Rs. <?php echo number_format($bill['balance'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="custom-input-group">
                                <label for="paymentNotes">TRANSACTION NOTES / REFERENCE</label>
                                <textarea class="form-control form-control-lg" id="paymentNotes" name="notes" rows="3" placeholder="Enter bank reference, check number, or cash notes..."></textarea>
                            </div>

                            <hr class="my-5 border-light">

                            <div class="row align-items-center g-3">
                                <div class="col-md-6 text-center text-md-start mb-4 mb-md-0">
                                    <a href="purchase_bill_detail.php?id=<?php echo $bill_id; ?>" class="text-decoration-none text-muted fw-bold">
                                        <i class="fas fa-chevron-left me-2"></i> Return to Invoice Details
                                    </a>
                                </div>
                                <div class="col-md-6 text-md-end d-flex gap-3 justify-content-center justify-content-md-end">
                                    <a href="view_purchase_bills.php" class="btn btn-cancel">Discard</a>
                                    <button type="submit" class="btn btn-primary">
                                        Confirm Payment <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

            <?php renderPageGuide([
                'eyebrow' => 'Keep a clear trail', 'title' => 'A small step toward settled.',
                'description' => 'This form records a payment against this supplier invoice only. It does not add stock or change the original bill total.',
                'steps' => [
                    ['title' => 'Confirm the invoice', 'text' => 'Check the supplier name and outstanding amount in the yellow summary above.'],
                    ['title' => 'Record what was paid', 'text' => 'Use the actual transaction date. The payment must be greater than zero and cannot exceed the balance due.'],
                    ['title' => 'Leave a useful reference', 'text' => 'Add a bank reference or cash note. After saving, the invoice shows the payment in its history.']
                ],
                'links' => [['label' => 'View invoice & payment history', 'href' => 'purchase_bill_detail.php?id=' . $bill_id, 'icon' => 'receipt'], ['label' => 'All purchases', 'href' => 'view_purchase_bills.php', 'icon' => 'history']]
            ]); ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        /**
         * Cool Real-time Currency Formatter
         */
        function formatCurrencyInput(input) {
            // Remove everything except digits and dot
            let value = input.value.replace(/[^\d.]/g, '');
            
            // Split into parts
            let parts = value.split('.');
            
            // Format whole number with commas
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            
            // Limit to 2 decimal places
            if (parts.length > 1) {
                parts[1] = parts[1].substring(0, 2);
                input.value = parts[0] + '.' + parts[1];
            } else {
                input.value = parts[0];
            }
        }
    </script>
</body>
</html>