<?php
require_once 'config.php';
require_once 'page_overview.php';

$success_message = '';
$error_message = '';

// Fetch companies for the dropdown
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_name = $conn->real_escape_string($_POST['product_name']);
    $composition = $conn->real_escape_string($_POST['composition']);
    $company_id = intval($_POST['company_id']);
    
    // SANITIZATION: Remove commas from the formatted input strings before converting to float
    $purchase_rate = floatval(str_replace(',', '', $_POST['purchase_rate']));
    $sale_rate = floatval(str_replace(',', '', $_POST['sale_rate']));
    
    $current_stock = intval($_POST['current_stock']);
    
    // Validation
    if (empty(trim($product_name))) {
        $error_message = 'Product Name is required!';
    } elseif ($company_id == 0) {
        $error_message = 'Please select a company!';
    } elseif ($purchase_rate < 0 || $sale_rate < 0) {
        $error_message = 'Rates cannot be negative!';
    } else {
        
        // START TRANSACTION: Link Product creation with Purchase Billing
        $conn->begin_transaction();

        try {
            // 1. Insert into products table
            $sql_product = "INSERT INTO products (product_name, composition, company_id, purchase_rate, sale_rate, current_stock) 
                            VALUES ('$product_name', '$composition', $company_id, $purchase_rate, $sale_rate, $current_stock)";
            
            if (!$conn->query($sql_product)) {
                throw new Exception("Error creating product: " . $conn->error);
            }
            
            $product_id = $conn->insert_id;

            // 2. If opening stock is provided, create an automatic Purchase Bill (PUR-)
            if ($current_stock > 0) {
                $bill_number = "PUR-" . date('Ymd') . "-" . str_pad($product_id, 4, '0', STR_PAD_LEFT);
                $total_amount = $current_stock * $purchase_rate;
                $bill_date = date('Y-m-d');

                // Record as an Unpaid Bill (Balance Due) so it doesn't clutter Opening Balances
                $sql_bill = "INSERT INTO purchase_bills (bill_number, company_id, total_amount, paid_amount, balance, bill_date, created_at) 
                             VALUES ('$bill_number', $company_id, $total_amount, 0, $total_amount, '$bill_date', NOW())";
                
                if (!$conn->query($sql_bill)) {
                    throw new Exception("Error creating auto-purchase bill: " . $conn->error);
                }
                
                $purchase_bill_id = $conn->insert_id;

                // Create Bill Items record
                $sql_item = "INSERT INTO purchase_bill_items (purchase_bill_id, product_id, quantity, rate, total) 
                             VALUES ($purchase_bill_id, $product_id, $current_stock, $purchase_rate, $total_amount)";
                
                if (!$conn->query($sql_item)) {
                    throw new Exception("Error recording bill item: " . $conn->error);
                }
            }

            // Commit all changes
            $conn->commit();
            $success_message = 'Product added successfully! Stock recorded in Purchase History.';
            $_POST = array(); // Clear form

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
    <title>Add Product - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <h2><i class="fas fa-plus-circle me-2"></i>Add New Product</h2>
                    <p>Register a new medicine and set initial stock</p>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success shadow-sm border-0 rounded-pill px-4">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="row po-form-row">
        <div class="col-lg-8">
        <div class="content-card po-form-card">
            <div class="po-section-head"><div><span class="po-eyebrow">Grow your catalogue</span><h3>From supplier to shelf</h3><p>Set the medicine details, supplier and per-unit rates before saving.</p></div></div>
            <?php if ($companies->num_rows === 0): ?>
            <div class="po-empty"><h4>Add a supplier first</h4><p>A product needs a supplier. Create a company profile, then return here to register the medicine.</p><a href="add_company.php" class="btn btn-primary">Add a supplier</a></div>
            <?php endif; ?>
            <form method="POST" action="" id="productForm">
                <h4 class="po-field-heading">01 / Medicine identity</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Product Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-box text-muted"></i></span>
                            <input type="text" class="form-control" name="product_name" placeholder="Enter product name" required value="<?php echo isset($_POST['product_name']) ? $_POST['product_name'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Composition / Generic Name</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-flask text-muted"></i></span>
                            <input type="text" class="form-control" name="composition" placeholder="Enter generic name" value="<?php echo isset($_POST['composition']) ? $_POST['composition'] : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <h4 class="po-field-heading">02 / Supplier & opening quantity</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Company / Supplier <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-building text-muted"></i></span>
                            <select class="form-select" name="company_id" required>
                                <option value="">Select Company...</option>
                                <?php 
                                if ($companies->num_rows > 0) {
                                    $companies->data_seek(0);
                                    while($company = $companies->fetch_assoc()) {
                                        $selected = (isset($_POST['company_id']) && $_POST['company_id'] == $company['id']) ? 'selected' : '';
                                        echo "<option value='" . $company['id'] . "' $selected>" . $company['company_name'] . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Opening Stock Quantity</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-cubes text-muted"></i></span>
                            <input type="number" class="form-control" name="current_stock" placeholder="0" min="0" value="<?php echo isset($_POST['current_stock']) ? $_POST['current_stock'] : '0'; ?>">
                        </div>
                    </div>
                </div>

                <h4 class="po-field-heading">03 / Per-unit pricing</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Purchase Rate (Cost) <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text fw-bold text-muted">Rs.</span>
                            <!-- Type changed to text to support comma formatting -->
                            <input type="text" class="form-control currency-input" name="purchase_rate" placeholder="0.00" required oninput="formatCurrencyInput(this)" value="<?php echo isset($_POST['purchase_rate']) ? $_POST['purchase_rate'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Sale Rate (Retail) <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text fw-bold text-muted">Rs.</span>
                            <!-- Type changed to text to support comma formatting -->
                            <input type="text" class="form-control currency-input" name="sale_rate" placeholder="0.00" required oninput="formatCurrencyInput(this)" value="<?php echo isset($_POST['sale_rate']) ? $_POST['sale_rate'] : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 text-muted">
                
                <div class="d-flex justify-content-end gap-3 flex-wrap">
                    <button type="reset" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">
                        <i class="fas fa-redo me-1"></i> Reset Fields
                    </button>
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-1"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
        </div>
        <div class="col-lg-4">
            <?php renderPageGuide([
                'eyebrow' => 'Product setup / 3 steps', 'title' => 'Start stock on the right foot.',
                'description' => 'Opening quantity has a financial effect. Review the supplier and cost before you save.',
                'steps' => [
                    ['title' => 'Identify the medicine', 'text' => 'Use a clear product name and, where available, its composition or generic name for easier searching.'],
                    ['title' => 'Choose its supplier', 'text' => 'Products belong to a company. There are ' . $companies->num_rows . ' suppliers available in the selector.'],
                    ['title' => 'Check opening stock carefully', 'text' => 'A positive opening quantity automatically creates an unpaid purchase bill at quantity × purchase rate. Use zero if you will record stock through a purchase bill later.'],
                ],
                'links' => [
                    ['label' => 'Back to product catalogue', 'href' => 'view_products.php', 'icon' => 'package'],
                    ['label' => 'Add a supplier', 'href' => 'add_company.php', 'icon' => 'building-2'],
                ],
            ]); ?>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }

        /**
         * Real-time Currency Formatter
         * Automatically adds commas as thousands separator and handles decimal points
         */
        function formatCurrencyInput(input) {
            // Remove all characters except digits and dot
            let value = input.value.replace(/[^\d.]/g, '');
            
            // Split into integer and decimal parts
            let parts = value.split('.');
            
            // Add commas to the integer part
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            
            // Limit decimal places to 2
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