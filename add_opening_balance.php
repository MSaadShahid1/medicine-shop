<?php
require_once 'config.php';
require_once 'page_overview.php';

$success_message = '';
$error_message = '';

// Form Variables
$edit_mode = false;
$edit_id = 0;
$company_id_val = '';
$bill_ref_val = '';
$bill_date_val = date('Y-m-d');
$total_val = '';
$paid_val = '';
$desc_val = '';

// 1. Handle Edit Request
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $res = $conn->query("SELECT * FROM purchase_bills WHERE id = $edit_id");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $edit_mode = true;
        $company_id_val = $row['company_id'];
        $bill_ref_val = $row['bill_number'];
        $bill_date_val = $row['bill_date'];
        $total_val = $row['total_amount'];
        $paid_val = $row['paid_amount'];
        
        // Get description from payment if exists
        $pay_res = $conn->query("SELECT notes FROM payments WHERE purchase_bill_id = $edit_id LIMIT 1");
        if($pay_res && $pay_res->num_rows > 0) {
            $notes = $pay_res->fetch_assoc()['notes'];
            $desc_val = str_replace("Opening Balance - ", "", $notes);
        }
    }
}

// 2. Handle Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_id = intval($_POST['company_id']);
    $bill_number = !empty($_POST['bill_number']) ? $conn->real_escape_string($_POST['bill_number']) : 'OB-' . date('YmdHis');
    $bill_date = $_POST['bill_date'];
    $description = $conn->real_escape_string($_POST['description']);
    
    // SANITIZATION: Strip commas from the inputs before converting to floats
    $total_amount = floatval(str_replace(',', '', $_POST['total_amount']));
    $paid_amount = floatval(str_replace(',', '', $_POST['paid_amount']));
    $balance_due = $total_amount - $paid_amount;

    // Validation
    if ($company_id == 0) $error_message = "Please select a company.";
    elseif ($total_amount <= 0) $error_message = "Total amount must be greater than 0.";
    elseif ($paid_amount < 0) $error_message = "Paid amount cannot be negative.";
    elseif ($paid_amount > ($total_amount + 0.01)) $error_message = "Paid amount cannot exceed the Total amount.";
    else {
        $conn->begin_transaction();
        try {
            $target_id = 0;
            
            if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
                // UPDATE Logic
                $target_id = intval($_POST['edit_id']);
                $sql = "UPDATE purchase_bills SET 
                        company_id = $company_id, bill_number = '$bill_number', bill_date = '$bill_date',
                        total_amount = $total_amount, paid_amount = $paid_amount, balance = $balance_due
                        WHERE id = $target_id";
                if (!$conn->query($sql)) throw new Exception("Update failed: " . $conn->error);
                
                // Reset payments for this bill to ensure accuracy
                $conn->query("DELETE FROM payments WHERE purchase_bill_id = $target_id");
            } else {
                // INSERT Logic
                $sql = "INSERT INTO purchase_bills (bill_number, company_id, total_amount, paid_amount, balance, bill_date, created_at)
                        VALUES ('$bill_number', $company_id, $total_amount, $paid_amount, $balance_due, '$bill_date', NOW())";
                if (!$conn->query($sql)) throw new Exception("Insert failed: " . $conn->error);
                $target_id = $conn->insert_id;
            }

            // Insert Single Consolidated Payment Entry if paid > 0
            if ($paid_amount > 0) {
                $notes = "Opening Balance - " . ($description ?: "Imported Record");
                $conn->query("INSERT INTO payments (purchase_bill_id, amount, payment_date, notes) VALUES ($target_id, $paid_amount, '$bill_date', '$notes')");
            }

            $conn->commit();
            header("Location: view_purchase_bills.php?msg=" . ($edit_mode ? 'updated' : 'created'));
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");

/**
 * RECENT LIST LOGIC: 
 * We show all bills with a balance to ensure you see everything pending across all suppliers.
 */
$recent = $conn->query("SELECT pb.*, c.company_name 
                       FROM purchase_bills pb 
                       JOIN companies c ON pb.company_id = c.id 
                       WHERE pb.balance > 0.01 
                       ORDER BY pb.created_at DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Opening Balance - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .list-group-item {
            border: none; border-bottom: 1px solid var(--border-light); padding: 15px; transition: all 0.2s;
            background: transparent; color: var(--text-dark);
        }
        .list-group-item:last-child { border-bottom: none; }
        .list-group-item:hover { background-color: rgba(59, 130, 246, 0.06); transform: translateX(5px); }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-file-import me-2"></i>Add Opening Balance</h2>
                    <p>Record a previous/unpaid balance for a supplier</p>
                </div>
            </div>

            <?php if($edit_mode): ?>
            <div class="page-header-actions">
                <a href="add_opening_balance.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                    <i class="fas fa-times me-2"></i> Cancel Editing
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4 mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="row po-form-row">
            <!-- Left: Input Form -->
            <div class="col-lg-8">
                <div class="content-card po-form-card">
                    <div class="po-section-head">
                        <div><span class="po-eyebrow">Supplier history / one record at a time</span><h3>
                            <?php echo $edit_mode ? '<i class="fas fa-edit me-2"></i> Edit Record' : '<i class="fas fa-plus-circle me-2"></i> New Balance Entry'; ?>
                        </h3><p>Carry a previous supplier bill into your ledger without adding inventory.</p></div>
                    </div>

                    <form method="POST" action="">
                        <?php if($edit_mode): ?><input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>"><?php endif; ?>

                        <?php if ($companies->num_rows === 0): ?>
                        <div class="po-empty"><h4>A supplier is needed first</h4><p>Create a company profile before recording a historical balance.</p><a href="add_company.php" class="btn btn-primary">Add a supplier</a></div>
                        <?php endif; ?>
                        <h4 class="po-field-heading">01 / Supplier & reference</h4>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold text-secondary">Supplier Company <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                    <select class="form-select" name="company_id" required>
                                        <option value="">Select Company...</option>
                                        <?php 
                                        if ($companies->num_rows > 0) {
                                            $companies->data_seek(0);
                                            while($c = $companies->fetch_assoc()) {
                                                $selected = ($c['id'] == $company_id_val) ? 'selected' : '';
                                                echo "<option value='{$c['id']}' $selected>{$c['company_name']}</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold text-secondary">Bill / Ref Number</label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                    <input type="text" class="form-control" name="bill_number" value="<?php echo $bill_ref_val; ?>" placeholder="e.g. OLD-2023-001">
                                </div>
                            </div>
                        </div>

                        <h4 class="po-field-heading">02 / Date & supporting note</h4>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold text-secondary">Date of Entry</label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" name="bill_date" required value="<?php echo $bill_date_val; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold text-secondary">Note / Description</label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                                    <input type="text" class="form-control" name="description" value="<?php echo $desc_val; ?>" placeholder="e.g. Previous Year Ledger Balance">
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted my-4">

                        <!-- Financials -->
                        <h4 class="po-field-heading">03 / Original amount & amount already paid</h4>
                        <div class="p-4 bg-light rounded-3 border po-financials">
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-bold text-dark">Total Bill Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text fw-bold">Rs.</span>
                                        <input type="text" class="form-control highlight-input" name="total_amount" id="total" required value="<?php echo $total_val; ?>" oninput="formatCurrencyInput(this); calcBalance()" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-success">Paid Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text fw-bold text-success">Rs.</span>
                                        <input type="text" class="form-control highlight-input text-success" name="paid_amount" id="paid" required value="<?php echo $paid_val ? $paid_val : '0'; ?>" oninput="formatCurrencyInput(this); calcBalance()" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between align-items-center border-top pt-3">
                                <span class="text-muted fw-bold">Remaining Balance Due:</span>
                                <span class="h4 mb-0 text-danger fw-bolder" id="balanceDisplay">Rs. 0.00</span>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill shadow fw-bold">
                                <i class="fas fa-save me-2"></i> <?php echo $edit_mode ? 'Update Record' : 'Save Record'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Unpaid Imports List -->
            <div class="col-lg-4">
                <?php renderPageGuide([
                    'eyebrow' => 'Opening balance / 3 checks', 'title' => $edit_mode ? 'Review before replacing.' : 'Bring the past into focus.',
                    'description' => $edit_mode ? 'Updating this record replaces its payment history with one consolidated payment if the paid amount is positive.' : 'A manual entry for a previous supplier bill. This is not a bulk import and does not change product stock.',
                    'steps' => [
                        ['title' => 'Match the supplier & reference', 'text' => 'Choose the company you owe. Leave the reference blank to generate an OB- reference automatically.'],
                        ['title' => 'Reconcile total and paid', 'text' => 'Enter a positive original total and the amount already paid. Paid cannot be negative or exceed the total. The remainder becomes supplier balance due.'],
                        ['title' => 'Keep a useful record', 'text' => 'Use the historical date. When an amount is paid, the note is stored with its consolidated payment entry. Review the supplier ledger after saving.'],
                    ],
                    'links' => [
                        ['label' => 'View purchase bills', 'href' => 'view_purchase_bills.php', 'icon' => 'receipt'],
                        ['label' => 'Back to supplier directory', 'href' => 'view_companies.php', 'icon' => 'building-2'],
                    ],
                ]); ?>
                <div class="content-card p-0 overflow-hidden po-pending">
                    <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-secondary mb-0">Outstanding Balances</h6>
                        <span class="po-count"><?php echo $recent ? $recent->num_rows : 0; ?> shown</span>
                    </div>
                    <p class="po-pending-note pt-3 mb-0">Up to 15 latest unpaid supplier bills, including regular purchases. Not a list of opening balances only.</p>
                    <div class="list-group list-group-flush">
                        <?php if($recent && $recent->num_rows > 0): ?>
                            <?php while($r = $recent->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark"><?php echo $r['company_name']; ?></span>
                                    <small class="text-muted"><?php echo date('d M', strtotime($r['bill_date'])); ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small text-muted">Bill: <?php echo $r['bill_number']; ?></div>
                                        <div class="small fw-bold text-danger">Due: Rs. <?php echo number_format($r['balance'], 2); ?></div>
                                    </div>
                                    <a href="add_opening_balance.php?edit_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-light border rounded-circle" title="Edit Entry">
                                        <i class="fas fa-pen text-secondary" style="font-size: 0.8rem;"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center p-5 text-muted po-empty">
                                <i class="fas fa-check-circle fa-2x mb-3 opacity-25 text-success"></i>
                                <p class="small">No outstanding bills found.</p>
                                <a href="view_purchase_bills.php" class="po-link">View purchase history <span aria-hidden="true">↗</span></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        /**
         * Real-time Currency Formatter
         */
        function formatCurrencyInput(input) {
            let value = input.value.replace(/[^\d.]/g, '');
            let parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (parts.length > 1) {
                parts[1] = parts[1].substring(0, 2);
                input.value = parts[0] + '.' + parts[1];
            } else {
                input.value = parts[0];
            }
        }

        function formatValueWithCommas(val) {
            let num = parseFloat(val) || 0;
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function calcBalance() {
            // Get values while stripping commas
            const totalStr = document.getElementById('total').value.replace(/,/g, '');
            const paidStr = document.getElementById('paid').value.replace(/,/g, '');
            
            const total = parseFloat(totalStr) || 0;
            const paid = parseFloat(paidStr) || 0;
            const balance = total - paid;
            
            const display = document.getElementById('balanceDisplay');
            
            if (balance < -0.01) {
                display.innerText = "Error: Overpaid!";
                display.classList.remove('text-danger');
                display.classList.add('text-warning');
            } else {
                display.innerText = "Rs. " + formatValueWithCommas(balance);
                display.classList.remove('text-warning');
                display.classList.add('text-danger');
            }
        }

        // Run formatting and balance calculation on load
        window.onload = function() {
            const t = document.getElementById('total');
            const p = document.getElementById('paid');
            if(t.value) formatCurrencyInput(t);
            if(p.value) formatCurrencyInput(p);
            calcBalance();
        };
    </script>
</body>
</html>