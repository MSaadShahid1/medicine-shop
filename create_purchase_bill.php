<?php
require_once 'config.php';
require_once 'page_overview.php';

$success_message = '';
$error_message = '';

// Fetch companies for dropdown
$companies = $conn->query("SELECT * FROM companies ORDER BY company_name ASC");

// Fetch products for both JS array (autocomplete) and Modal List
$products_res = $conn->query("SELECT p.*, c.company_name FROM products p LEFT JOIN companies c ON p.company_id = c.id ORDER BY p.product_name ASC");
$products_arr = [];
while($r = $products_res->fetch_assoc()) {
    $products_arr[] = $r;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_id = intval($_POST['company_id']);
    $bill_number = $conn->real_escape_string($_POST['bill_number']); 
    $bill_date = $_POST['bill_date'];
    
    // SANITIZATION: Strip commas from all numeric inputs before processing
    $total_amount = floatval(str_replace(',', '', $_POST['total_amount_hidden'])); 
    $paid_amount = floatval(str_replace(',', '', $_POST['paid_amount']));
    $balance = $total_amount - $paid_amount;
    
    $product_ids = isset($_POST['product_id']) ? $_POST['product_id'] : [];
    
    // Arrays for dynamic items - also need comma stripping
    $quantities = isset($_POST['quantity']) ? array_map(function($val) { return intval(str_replace(',', '', $val)); }, $_POST['quantity']) : [];
    $rates = isset($_POST['rate']) ? array_map(function($val) { return floatval(str_replace(',', '', $val)); }, $_POST['rate']) : [];
    $totals = isset($_POST['total']) ? array_map(function($val) { return floatval(str_replace(',', '', $val)); }, $_POST['total']) : [];
    
    // Server-side validation
    if (empty($product_ids)) {
        $error_message = "Please add at least one item to the purchase bill.";
    } elseif ($company_id == 0) {
        $error_message = "Please select a supplier company.";
    } elseif ($paid_amount < 0) {
        $error_message = "Paid amount cannot be negative.";
    } elseif ($paid_amount > ($total_amount + 0.01)) {
        $error_message = "Paid amount cannot exceed the total bill amount.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Create Purchase Bill Header
            $sql_bill = "INSERT INTO purchase_bills (bill_number, company_id, total_amount, paid_amount, balance, bill_date, created_at) 
                         VALUES ('$bill_number', $company_id, $total_amount, $paid_amount, $balance, '$bill_date', NOW())";
            
            if (!$conn->query($sql_bill)) throw new Exception("Error creating bill: " . $conn->error);
            $purchase_bill_id = $conn->insert_id;
            
            // 2. Add Items & Update Stock
            $stmt_item = $conn->prepare("INSERT INTO purchase_bill_items (purchase_bill_id, product_id, quantity, rate, total) VALUES (?, ?, ?, ?, ?)");
            $stmt_stock = $conn->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
            
            for ($i = 0; $i < count($product_ids); $i++) {
                $pid = $product_ids[$i];
                $qty = $quantities[$i];
                $rate = $rates[$i]; 
                $row_total = $totals[$i];
                
                // Insert Item
                $stmt_item->bind_param("iiidd", $purchase_bill_id, $pid, $qty, $rate, $row_total);
                if (!$stmt_item->execute()) throw new Exception("Item insert failed for product ID: " . $pid);
                
                // Update Stock Only
                $stmt_stock->bind_param("ii", $qty, $pid);
                if (!$stmt_stock->execute()) throw new Exception("Stock update failed for product ID: " . $pid);
            }
            
            // 3. Record Payment if any
            if ($paid_amount > 0) {
                $sql_pay = "INSERT INTO payments (purchase_bill_id, amount, payment_date, notes) 
                            VALUES ($purchase_bill_id, $paid_amount, '$bill_date', 'Initial Payment for Bill #$bill_number')";
                if (!$conn->query($sql_pay)) throw new Exception("Error recording initial payment.");
            }
            
            $conn->commit();
            header("Location: view_purchase_bills.php?msg=created");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Transaction Failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Bill - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .search-list li { padding: 12px 20px; cursor: pointer; border-bottom: 1px solid var(--border-light); transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .search-list li:hover { background-color: rgba(59, 130, 246, 0.08); color: var(--primary-start); }
        #purchaseTable .form-control { border: 1px solid var(--border-light); padding: 8px 12px; font-size: 0.95rem; }
    </style>
</head>
<body class="bp-page bp-bill-form">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-truck-loading me-2"></i>New Purchase Bill</h2>
                    <p>Stock rates are locked. Select a company to begin adding products.</p>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4 mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <section class="bp-workflow no-print" data-print-exclude aria-label="Purchase bill workflow">
            <div><span class="po-eyebrow">Purchase workspace</span><h3>Bring stock in. Keep the balance clear.</h3><p>Start with the supplier, add incoming products and record any initial payment.</p></div>
            <ol class="bp-stepper"><li><span>01</span> Supplier &amp; reference</li><li><span>02</span> Incoming stock</li><li><span>03</span> Payment &amp; review</li></ol>
        </section>

        <form method="POST" action="" id="purchaseForm">
            <div class="bp-form-intro">
            <div class="content-card bp-entry-card">
                <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fas fa-building me-2"></i> Supplier Details</h5>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold text-secondary">Select Company <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                            <select class="form-select" name="company_id" id="companySelect" required onchange="handleCompanyChange()">
                                <option value="">-- Choose Company --</option>
                                <?php while($c = $companies->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['company_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Bill / Invoice Number</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                            <input type="text" class="form-control" name="bill_number" value="<?php echo generateBillNumber('PUR-'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Purchase Date</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" name="bill_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <?php renderPageGuide([
                'eyebrow' => 'Receiving stock', 'title' => 'A clean record from delivery to payment.',
                'description' => number_format($companies->num_rows) . ' suppliers available. Products are limited to the selected supplier, with purchase rates locked to inventory.',
                'steps' => [
                    ['title' => 'Match the paperwork', 'text' => 'Choose the supplier, confirm the invoice reference and enter the delivery date.'],
                    ['title' => 'Count what arrived', 'text' => 'Add products and quantities. Saving increases stock; changing supplier can clear the current selection.'],
                    ['title' => 'Separate paid from due', 'text' => 'Enter only the amount paid now. Any remainder stays on the supplier bill for later payments.']
                ],
                'links' => [['label' => 'Add a supplier', 'href' => 'add_company.php', 'icon' => 'building-2'], ['label' => 'Purchase history', 'href' => 'view_purchase_bills.php', 'icon' => 'history']]
            ]); ?>
            </div>

            <div class="content-card bp-items-card">
                <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fas fa-boxes me-2"></i> Purchase Items</h5>
                </div>
                
                <div class="mb-4 search-container">
                    <label class="form-label fw-bold text-secondary">Add Products</label>
                    <div class="d-flex gap-2">
                        <div class="input-group shadow-sm rounded flex-grow-1">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="productSearch" class="form-control" placeholder="Select company first..." autocomplete="off" disabled>
                        </div>
                        <button type="button" id="browseBtn" class="btn btn-primary px-4 rounded-pill shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#productModal" disabled>
                            <i class="fas fa-list me-2"></i> Browse All
                        </button>
                    </div>
                    <ul id="searchResults" class="search-list shadow border-0 rounded-bottom"></ul>
                </div>

                <div class="bp-cart-empty bp-purchase-empty po-empty"><span class="bp-empty-symbol" aria-hidden="true">＋</span><h6>Give this delivery its first item.</h6><p>Select a supplier above to unlock search and Browse All. Then add the products received and check each quantity.</p><a class="po-link" href="add_product.php">Missing a supplier product? Add a product ↗</a></div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle" id="purchaseTable">
                        <thead>
                            <tr>
                                <th width="40%" class="ps-3">Product Description</th>
                                <th width="15%">Unit Cost (Rs)</th>
                                <th width="15%">Quantity</th>
                                <th width="20%">Total (Rs)</th>
                                <th width="10%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseItems"></tbody>
                        <tfoot>
                            <tr class="bg-light">
                                <td colspan="3" class="text-end fw-bold py-3 text-secondary text-uppercase">Grand Total</td>
                                <td class="py-3">
                                    <!-- Changed to text for comma support -->
                                    <input type="text" class="form-control fw-bold text-dark bg-readonly currency-input" id="grandTotalDisplay" readonly value="0.00">
                                    <input type="hidden" name="total_amount_hidden" id="grandTotalInput" value="0">
                                </td>
                                <td></td>
                            </tr>
                            <tr class="bg-light border-top">
                                <td colspan="3" class="text-end fw-bold py-3 text-secondary text-uppercase">Amount Paid Now</td>
                                <td class="py-3">
                                    <div class="input-group input-group-sm shadow-sm rounded">
                                        <span class="input-group-text bg-white fw-bold text-success">Rs.</span>
                                        <!-- Changed to text for comma support -->
                                        <input type="text" class="form-control fw-bold text-success currency-input" name="paid_amount" id="paidAmount" value="0" oninput="formatCurrencyInput(this); calculateBalance()">
                                    </div>
                                    <div id="paymentErrorMsg" class="text-danger small mt-1 fw-bold" style="display:none;">
                                        Error: Paid amount exceeds total!
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr class="bg-light border-top">
                                <td colspan="3" class="text-end fw-bold py-3 text-secondary text-uppercase text-danger">Remaining Balance Due</td>
                                <td class="py-3">
                                    <!-- Changed to text for comma support -->
                                    <input type="text" class="form-control text-danger fw-bold bg-readonly currency-input" id="balanceDisplay" readonly value="0.00">
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="bp-submit-row mt-4"><p class="mb-0">Review quantities, the amount paid now and the remaining balance before saving.</p>
                    <button type="submit" id="submitBtn" class="btn btn-success px-5 py-3 rounded-pill shadow fw-bold fs-5">
                        <i class="fas fa-save me-2"></i> Save Purchase
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- PRODUCT MODAL -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light border-bottom-0">
                    <h5 class="modal-title fw-bold text-dark" id="productModalLabel"><i class="fas fa-box text-primary me-2"></i> Supplier Inventory List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="input-group mb-4 shadow-sm rounded">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-filter text-muted"></i></span>
                        <input type="text" id="modalSearch" class="form-control border-start-0 ps-0" placeholder="Filter list...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr><th>Product</th><th>Stock</th><th>Cost</th><th>Action</th></tr>
                            </thead>
                            <tbody id="modalProductList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var allProducts = <?php echo json_encode($products_arr); ?>;
        
        /**
         * Real-time Currency Formatter Logic
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

        function handleCompanyChange() {
            const companyId = $('#companySelect').val();
            const searchInput = $('#productSearch');
            const browseBtn = $('#browseBtn');
            const itemsTable = $('#purchaseItems');
            
            if (companyId) {
                searchInput.prop('disabled', false).attr('placeholder', 'Search medicine name...');
                browseBtn.prop('disabled', false);
                if(itemsTable.children().length > 0) {
                    if(confirm("Changing the company will clear your current selection. Proceed?")) {
                        itemsTable.empty();
                        calculateTotal();
                    }
                }
                updateModalList(companyId);
            } else {
                searchInput.prop('disabled', true).attr('placeholder', 'Select company first...');
                browseBtn.prop('disabled', true);
                itemsTable.empty();
                calculateTotal();
            }
        }

        function updateModalList(companyId) {
            const list = $('#modalProductList');
            list.empty();
            const filtered = allProducts.filter(p => p.company_id == companyId);
            filtered.forEach(p => {
                let formattedRate = formatValueWithCommas(p.purchase_rate);
                list.append(`<tr><td><div class="fw-bold">${p.product_name}</div><small>${p.composition}</small></td>
                    <td>${p.current_stock}</td><td>Rs. ${formattedRate}</td>
                    <td><button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="addItem(${p.id}, '${p.product_name.replace(/'/g, "\\'")}', ${p.purchase_rate})">Add</button></td></tr>`);
            });
        }

        $(document).ready(function() {
            $('#productSearch').on('keyup', function() {
                var term = $(this).val().toLowerCase();
                var cid = $('#companySelect').val();
                if (term.length > 0 && cid) {
                    var filtered = allProducts.filter(p => p.company_id == cid && (p.product_name.toLowerCase().includes(term) || p.composition.toLowerCase().includes(term)));
                    var html = '';
                    filtered.slice(0, 10).forEach(p => {
                        let formattedRate = formatValueWithCommas(p.purchase_rate);
                        html += `<li onmousedown="addItem(${p.id}, '${p.product_name.replace(/'/g, "\\'")}', ${p.purchase_rate})">
                                    <div><strong>${p.product_name}</strong><br><small>${p.composition}</small></div>
                                    <span class="badge bg-light text-primary border">Rs. ${formattedRate}</span></li>`;
                    });
                    $('#searchResults').html(html || '<li class="text-center p-3 text-muted">No medicines found.</li>').show();
                } else { $('#searchResults').hide(); }
            });
            $('#productSearch').on('blur', () => { setTimeout(() => { $('#searchResults').hide(); }, 200); });
            $('#modalSearch').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $("#modalProductList tr").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1) });
            });
        });

        function addItem(id, name, rate) {
            if ($(`.pid-input[value="${id}"]`).length) { alert("Already in list."); return; }
            const modalEl = document.getElementById('productModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            if (modal) modal.hide();
            const rowId = Date.now();
            
            let formattedRate = formatValueWithCommas(rate);

            const html = `<tr id="row_${rowId}">
                <td><div class="fw-bold text-dark">${name}</div><input type="hidden" name="product_id[]" class="pid-input" value="${id}"></td>
                <td><input type="text" name="rate[]" class="form-control rate-input bg-readonly currency-input" value="${formattedRate}" readonly tabindex="-1"></td>
                <td><input type="text" name="quantity[]" class="form-control qty-input currency-input" value="1" required oninput="formatCurrencyInput(this); calcRow(${rowId})"></td>
                <td><input type="text" name="total[]" class="form-control total-input fw-bold bg-white currency-input" value="${formattedRate}" readonly tabindex="-1"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeRow(${rowId})"><i class="fas fa-trash-alt"></i></button></td></tr>`;
            $('#purchaseItems').append(html);
            $('#searchResults').hide(); $('#productSearch').val('');
            calculateTotal();
        }

        function removeRow(id) { $(`#row_${id}`).remove(); calculateTotal(); }
        
        function calcRow(id) {
            const row = $(`#row_${id}`);
            // Get raw numbers by stripping commas
            const rate = parseFloat(row.find('.rate-input').val().replace(/,/g, '')) || 0;
            let qty = parseFloat(row.find('.qty-input').val().replace(/,/g, '')) || 0;
            
            if(qty < 0) { row.find('.qty-input').val(0); qty = 0; }
            
            let rowTotal = rate * qty;
            row.find('.total-input').val(formatValueWithCommas(rowTotal));
            calculateTotal();
        }

        function calculateTotal() {
            let grand = 0;
            $('.total-input').each(function() { 
                grand += parseFloat($(this).val().replace(/,/g, '')) || 0; 
            });
            $('#grandTotalDisplay').val(formatValueWithCommas(grand));
            $('#grandTotalInput').val(grand);
            calculateBalance();
        }

        function calculateBalance() {
            const total = parseFloat($('#grandTotalInput').val()) || 0;
            let paid = parseFloat($('#paidAmount').val().replace(/,/g, '')) || 0;
            
            if (paid < 0) { $('#paidAmount').val(0); paid = 0; }
            
            if (paid > total + 0.01) { 
                $('#paymentErrorMsg').show(); 
                $('#submitBtn').prop('disabled', true); 
                $('#balanceDisplay').val("0.00"); 
            } else { 
                $('#paymentErrorMsg').hide(); 
                $('#submitBtn').prop('disabled', false); 
                $('#balanceDisplay').val(formatValueWithCommas(total - paid)); 
            }
        }

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
    </script>
</body>
</html>