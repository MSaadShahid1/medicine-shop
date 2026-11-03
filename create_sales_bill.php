<?php
// Start output buffering to prevent "headers already sent" errors during redirect
ob_start();
require_once 'config.php';
require_once 'page_overview.php';

// Set Timezone
date_default_timezone_set('Asia/Karachi');

$success_message = '';
$error_message = '';

/**
 * 1. Fetch Customers and Products for the UI
 */
// Fetch all registered customers from the new customers table
$customers_res = $conn->query("SELECT * FROM customers ORDER BY customer_name ASC");

// Fetch products for the Browse Modal (Limited to 100 for performance)
$all_products_query = "SELECT p.*, c.company_name FROM products p 
                       LEFT JOIN companies c ON p.company_id = c.id 
                       ORDER BY p.product_name ASC LIMIT 100";
$all_products = $conn->query($all_products_query);

/**
 * 2. Handle Bill Submission
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $conn->real_escape_string(trim($_POST['customer_name']));
    $customer_phone = $conn->real_escape_string(trim($_POST['customer_phone']));
    $bill_date = date('Y-m-d H:i:s');
    $bill_number = generateBillNumber('INV-');
    
    $product_ids = isset($_POST['product_id']) ? $_POST['product_id'] : [];
    
    // SANITIZATION: Strip commas from all numeric inputs before processing
    $grand_total = floatval(str_replace(',', '', $_POST['total_amount_hidden']));
    
    // Process Arrays: Strip commas for database floats/ints
    $quantities = isset($_POST['quantity']) ? array_map(function($val) { return intval(str_replace(',', '', $val)); }, $_POST['quantity']) : [];
    $rates = isset($_POST['rate']) ? array_map(function($val) { return floatval(str_replace(',', '', $val)); }, $_POST['rate']) : [];
    $totals = isset($_POST['total']) ? array_map(function($val) { return floatval(str_replace(',', '', $val)); }, $_POST['total']) : [];
    
    if (empty($customer_name)) {
        $error_message = "Customer Name is required.";
    } elseif (empty($product_ids)) {
        $error_message = "Please add at least one product to the bill.";
    } else {
        $conn->begin_transaction();
        try {
            // STEP A: Handle Customer Record (Find or Create)
            $check_cust = $conn->query("SELECT id FROM customers WHERE customer_name = '$customer_name' LIMIT 1");
            if ($check_cust && $check_cust->num_rows > 0) {
                $customer_id = $check_cust->fetch_assoc()['id'];
            } else {
                $sql_new_cust = "INSERT INTO customers (customer_name, customer_phone, created_at) VALUES ('$customer_name', '$customer_phone', NOW())";
                if (!$conn->query($sql_new_cust)) throw new Exception("Failed to create customer record.");
                $customer_id = $conn->insert_id;
            }

            // STEP C: Insert Bill Header
            $sql_bill = "INSERT INTO sales_bills (bill_number, customer_id, customer_name, customer_phone, total_amount, bill_date, created_at) 
                         VALUES ('$bill_number', $customer_id, '$customer_name', '$customer_phone', $grand_total, '$bill_date', NOW())";
            
            if (!$conn->query($sql_bill)) throw new Exception("Error creating bill header: " . $conn->error);
            
            $bill_id = $conn->insert_id;
            
            // STEP D: Add Items and Update Stock
            $stmt_item = $conn->prepare("INSERT INTO sales_bill_items (sales_bill_id, product_id, quantity, rate, total) VALUES (?, ?, ?, ?, ?)");
            $stmt_stock = $conn->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
            
            for ($i = 0; $i < count($product_ids); $i++) {
                $pid = intval($product_ids[$i]);
                $qty = $quantities[$i];
                $rate = $rates[$i];
                $row_total = $totals[$i];
                
                $stmt_item->bind_param("iiidd", $bill_id, $pid, $qty, $rate, $row_total);
                if (!$stmt_item->execute()) throw new Exception("Item insertion failed.");
                
                $stmt_stock->bind_param("ii", $qty, $pid);
                if (!$stmt_stock->execute()) throw new Exception("Stock deduction failed.");
            }
            
            $conn->commit();
            header("Location: sales_bill_detail.php?id=$bill_id&msg=created");
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
    <title>Create Sales Bill - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .search-list li { padding: 12px 15px; cursor: pointer; list-style: none; border-bottom: 1px solid var(--border-light); transition: all 0.2s; }
        .search-list li:hover { background-color: rgba(59, 130, 246, 0.08); color: var(--primary-start); padding-left: 20px; }
        .bill-totals { background: #F8FAFC; padding: 25px; border-radius: 16px; margin-top: 20px; border: 1px solid var(--border-light); }
        #invoiceTable .form-control { border: 1px solid var(--border-light); padding: 8px 12px; font-size: 0.95rem; }
    </style>
</head>
<body class="bp-page bp-bill-form">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-shopping-cart me-2"></i>New Sales Bill</h2>
                    <p>Link bills to customers to track their total history.</p>
                </div>
            </div>
            <div class="page-header-actions">
                <a href="view_sales_bills.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold"><i class="fas fa-history me-2"></i> History</a>
            </div>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4 mb-4"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>

        <section class="bp-workflow no-print" data-print-exclude aria-label="Sales bill workflow">
            <div><span class="po-eyebrow">Sales workspace</span><h3>A good sale starts with the details.</h3><p>Choose a customer, build the item list, then review the total before saving.</p></div>
            <ol class="bp-stepper"><li><span>01</span> Customer</li><li><span>02</span> Products &amp; quantities</li><li><span>03</span> Review &amp; generate</li></ol>
        </section>

        <form method="POST" action="" id="billForm">
            <div class="bp-form-intro">
            <div class="content-card bp-entry-card">
                <div class="d-flex align-items-center mb-4 border-bottom pb-3"><h5 class="fw-bold text-secondary mb-0"><i class="fas fa-user-circle me-2"></i> Customer Details</h5></div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Customer Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="customer_name" id="customerNameInput" list="customerList" placeholder="Select existing or type new name" required autocomplete="off">
                            <datalist id="customerList">
                                <?php if ($customers_res && $customers_res->num_rows > 0): ?>
                                    <?php while($c = $customers_res->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($c['customer_name']); ?>" data-phone="<?php echo htmlspecialchars($c['customer_phone']); ?>">
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Phone Number</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                            <input type="text" class="form-control fw-bold" id="phoneFormat" name="customer_phone" placeholder="03XX-XXXXXXX" maxlength="12">
                        </div>
                    </div>
                </div>
            </div>

            <?php renderPageGuide([
                'eyebrow' => 'Before you bill', 'title' => 'The right customer. The right stock.',
                'description' => number_format($customers_res ? $customers_res->num_rows : 0) . ' registered customers available. Select an existing name or type a new one; a new customer is saved with the bill.',
                'steps' => [
                    ['title' => 'Keep the customer connected', 'text' => 'Use the saved name to keep purchases together in the customer ledger. Check the phone number before saving.'],
                    ['title' => 'Check availability', 'text' => 'Search by product or browse inventory. The stock column shows available units and sale rates are read-only.'],
                    ['title' => 'Make the final check', 'text' => 'Review every quantity and the grand total. Saving creates the invoice and deducts stock; sales are paid in full.']
                ],
                'links' => [['label' => 'Customer directory', 'href' => 'view_customers.php', 'icon' => 'users'], ['label' => 'Inventory report', 'href' => 'inventory_report.php', 'icon' => 'package']]
            ]); ?>
            </div>

            <div class="content-card bp-items-card">
                <div class="d-flex align-items-center mb-4 border-bottom pb-3"><h5 class="fw-bold text-secondary mb-0"><i class="fas fa-box-open me-2"></i> Bill Items</h5></div>
                <div class="row mb-4">
                    <div class="col-md-12 position-relative">
                        <label class="form-label fw-bold text-secondary">Add Products</label>
                        <div class="d-flex gap-2">
                            <div class="input-group shadow-sm rounded flex-grow-1">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="productSearch" class="form-control" placeholder="Quick search..." autocomplete="off">
                            </div>
                            <button type="button" class="btn btn-primary px-4 rounded-pill shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#productModal"><i class="fas fa-list me-1"></i> Browse Inventory</button>
                        </div>
                        <ul id="searchResults" class="search-list shadow border-0 rounded-bottom"></ul>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" id="invoiceTable">
                        <thead class="bg-light">
                            <tr>
                                <th width="35%" class="ps-3 text-secondary">Medicine Details</th>
                                <th width="12%" class="py-3 text-center text-secondary">Stock</th>
                                <th width="15%" class="py-3 text-secondary">Rate (Rs)</th>
                                <th width="13%" class="py-3 text-secondary">Qty</th>
                                <th width="20%" class="py-3 text-secondary">Total (Rs)</th>
                                <th width="5%" class="py-3 text-center text-secondary">Action</th>
                            </tr>
                        </thead>
                        <tbody id="invoiceItems"></tbody>
                    </table>
                </div>

                <div id="emptyCartMsg" class="bp-cart-empty po-empty text-center p-5 rounded-3 mt-2"><span class="bp-empty-symbol" aria-hidden="true">＋</span><h6 class="fw-bold">Your next invoice starts here.</h6><p class="mb-0">Search for a medicine above or choose Browse Inventory to add the first item.<br>Review available stock before entering quantities.</p><a href="add_product.php" class="po-link mt-3">Need a new product? Add it to inventory ↗</a></div>

                <div class="row justify-content-end mt-4">
                    <div class="col-md-5 col-lg-4">
                        <div class="bill-totals shadow-sm d-flex justify-content-between align-items-center">
                            <span class="h5 mb-0 text-secondary fw-bold">Grand Total:</span>
                            <span class="h3 mb-0 text-primary fw-bolder" id="grandTotalDisplay">Rs. 0.00</span>
                            <input type="hidden" name="total_amount_hidden" id="grandTotalInput" value="0">
                        </div>
                    </div>
                </div>
                <hr class="my-4 text-muted">
                <div class="bp-submit-row"><p class="mb-0">Ready to save? The invoice total and stock deduction are recorded together.</p><button type="submit" class="btn btn-success px-5 py-3 rounded-pill shadow fw-bold fs-5">Generate Bill</button></div>
            </div>
        </form>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light border-bottom-0"><h5 class="modal-title fw-bold text-dark">Select Products</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <input type="text" id="modalSearch" class="form-control mb-4 shadow-sm rounded" placeholder="Filter medicines...">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light"><tr><th class="py-3 ps-3">Product Name</th><th>Price</th><th>Stock</th><th class="text-end">Action</th></tr></thead>
                            <tbody id="modalProductList">
                                <?php if ($all_products && $all_products->num_rows > 0): ?>
                                    <?php while($p = $all_products->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3"><div class="fw-bold text-dark"><?php echo $p['product_name']; ?></div><small class="text-muted"><?php echo $p['composition']; ?></small></td>
                                        <td class="fw-bold text-secondary">Rs. <?php echo number_format($p['sale_rate'], 2); ?></td>
                                        <td><?php echo $p['current_stock']; ?></td>
                                        <td class="text-end"><button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="addProduct(<?php echo $p['id']; ?>, '<?php echo addslashes($p['product_name']); ?>', <?php echo $p['sale_rate']; ?>, <?php echo $p['current_stock']; ?>)"><i class="fas fa-plus"></i></button></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        $(document).ready(function() {
            $('#customerNameInput').on('change', function() {
                var val = $(this).val();
                var phone = $('#customerList option').filter(function() {
                    return this.value === val;
                }).data('phone');
                if(phone) $('#phoneFormat').val(phone);
            });

            document.getElementById('phoneFormat').addEventListener('input', function (e) {
                let input = e.target.value.replace(/\D/g, '');
                if (input.length > 4) input = input.substring(0, 4) + '-' + input.substring(4, 11);
                e.target.value = input;
            });

            $('#productSearch').on('keyup', function() {
                var term = $(this).val();
                if (term.length > 1) {
                    $.ajax({
                        url: 'search_product.php', type: 'GET', data: { term: term }, dataType: 'json',
                        success: function(data) {
                            var html = '';
                            if (data.length > 0) {
                                $.each(data, function(key, item) {
                                    let formattedRate = formatValueWithCommas(item.sale_rate);
                                    html += '<li onmousedown="addProduct(' + item.id + ', \'' + item.label.replace(/'/g, "\\'") + '\', ' + item.sale_rate + ', ' + item.stock + ')">' + item.label + '<span class="float-end badge bg-light text-dark border">Rs. ' + formattedRate + '</span></li>';
                                });
                                $('#searchResults').html(html).show();
                            } else { $('#searchResults').html('<li class="text-muted text-center py-2">No results</li>').show(); }
                        }
                    });
                } else { $('#searchResults').hide(); }
            });

            $('#productSearch').on('blur', () => { setTimeout(() => { $('#searchResults').hide(); }, 200); });

            $('#modalSearch').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $("#modalProductList tr").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1) });
            });
        });

        function addProduct(id, name, rate, stock) {
            if ($(`.product-id-input[value="${id}"]`).length) { alert('Already added!'); return; }
            if (stock <= 0) { alert('Item is out of stock!'); return; }

            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('productModal'));
            if (modalInstance) modalInstance.hide();

            $('#emptyCartMsg').hide();
            $('#searchResults').hide();
            $('#productSearch').val(''); 

            var rowId = Date.now();
            let formattedRate = formatValueWithCommas(rate);

            var html = `<tr id="row_${rowId}">
                <td><div class="fw-bold text-dark">${name}</div><input type="hidden" name="product_id[]" class="product-id-input" value="${id}"></td>
                <td class="text-center"><span class="badge bg-secondary rounded-pill">${stock}</span></td>
                <td><input type="text" name="rate[]" class="form-control rate-input bg-readonly currency-input" value="${formattedRate}" readonly tabindex="-1"></td>
                <td><input type="text" name="quantity[]" class="form-control qty-input currency-input" value="1" required oninput="formatCurrencyInput(this); calculateRow(${rowId})"></td>
                <td><input type="text" name="total[]" class="form-control total-input fw-bold text-dark currency-input" value="${formattedRate}" readonly tabindex="-1"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeRow(${rowId})"><i class="fas fa-trash-alt"></i></button></td>
            </tr>`;

            $('#invoiceItems').append(html);
            calculateGrandTotal();
        }

        function removeRow(rowId) { $(`#row_${rowId}`).remove(); if ($('#invoiceItems tr').length === 0) $('#emptyCartMsg').show(); calculateGrandTotal(); }
        
        function calculateRow(rowId) {
            var row = $('#row_' + rowId);
            // Get raw numbers by stripping commas
            const rate = parseFloat(row.find('.rate-input').val().replace(/,/g, '')) || 0;
            let qty = parseFloat(row.find('.qty-input').val().replace(/,/g, '')) || 0;
            
            var max = parseFloat(row.find('.qty-input').attr('max'));
            // Note: Since qty is text now, we don't use 'max' attribute directly on input, but we check against 'stock' badge or similar.
            // For simplicity, we just calculate.
            
            let rowTotal = rate * qty;
            row.find('.total-input').val(formatValueWithCommas(rowTotal));
            calculateGrandTotal();
        }

        function calculateGrandTotal() {
            var grandTotal = 0;
            $('.total-input').each(function() { 
                grandTotal += parseFloat($(this).val().replace(/,/g, '')) || 0; 
            });
            $('#grandTotalDisplay').text('Rs. ' + formatValueWithCommas(grandTotal));
            $('#grandTotalInput').val(grandTotal);
        }

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
    </script>
</body>
</html>