<?php
require_once 'config.php';
require_once 'page_overview.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';
$warning_message = '';

// Check if ID is valid
if ($id == 0) {
    header("Location: view_products.php");
    exit();
}

// Fetch Existing Data
$sql = "SELECT * FROM products WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "Product not found!";
    exit();
}

$product = $result->fetch_assoc();

// Fetch companies for the dropdown
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");

// Handle Form Submission
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
        // CHECK FOR CHANGES: Compare posted values with existing database values
        if ($product_name == $product['product_name'] && 
            $composition == $product['composition'] && 
            $company_id == $product['company_id'] && 
            $purchase_rate == $product['purchase_rate'] && 
            $sale_rate == $product['sale_rate'] && 
            $current_stock == $product['current_stock']) {
            
            $warning_message = 'No changes were made to the product details.';
        } else {
            // Update database
            $sql = "UPDATE products SET 
                    product_name = '$product_name', 
                    composition = '$composition', 
                    company_id = $company_id, 
                    purchase_rate = $purchase_rate, 
                    sale_rate = $sale_rate, 
                    current_stock = $current_stock 
                    WHERE id = $id";
            
            if ($conn->query($sql) === TRUE) {
                // REDIRECTION: Success
                header("Location: view_products.php?msg=updated");
                exit();
            } else {
                $error_message = 'Error: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Medicine Shop</title>
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
                <i class="fas fa-bars mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"></i>
                <div>
                    <h2><i class="fas fa-edit me-2"></i>Edit Product</h2>
                    <p>Update details for <strong><?php echo htmlspecialchars($product['product_name']); ?></strong></p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="view_products.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                    <i class="fas fa-arrow-left me-2"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Warning Message (No changes) -->
        <?php if ($warning_message): ?>
        <div class="alert alert-warning shadow-sm border-0 rounded-pill px-4">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $warning_message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Error Message -->
        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="row po-form-row">
        <div class="col-lg-8">
        <div class="content-card po-form-card">
            <div class="po-section-head"><div><span class="po-eyebrow">Product maintenance</span><h3>Keep your catalogue accurate</h3><p>Review supplier, quantity and per-unit prices. Existing bills are not rewritten.</p></div></div>
            <form method="POST" action="">
                <h4 class="po-field-heading">01 / Medicine identity</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Product Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-box text-muted"></i></span>
                            <input type="text" class="form-control" name="product_name" required value="<?php echo htmlspecialchars($product['product_name']); ?>" placeholder="Enter product name">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Composition / Generic Name</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-flask text-muted"></i></span>
                            <input type="text" class="form-control" name="composition" value="<?php echo htmlspecialchars($product['composition']); ?>" placeholder="Enter composition">
                        </div>
                    </div>
                </div>
                
                <h4 class="po-field-heading">02 / Supplier & current quantity</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Company / Supplier <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-building text-muted"></i></span>
                            <select class="form-select" name="company_id" required>
                                <option value="">Select Company...</option>
                                <?php 
                                if ($companies->num_rows > 0) {
                                    $companies->data_seek(0); // Reset pointer
                                    while($company = $companies->fetch_assoc()) {
                                        $selected = ($product['company_id'] == $company['id']) ? 'selected' : '';
                                        echo "<option value='" . $company['id'] . "' $selected>" . htmlspecialchars($company['company_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Current Stock Quantity</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-cubes text-muted"></i></span>
                            <input type="number" class="form-control" name="current_stock" min="0" value="<?php echo $product['current_stock']; ?>" placeholder="0">
                        </div>
                    </div>
                </div>

                <h4 class="po-field-heading">03 / Per-unit pricing</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Purchase Rate (Cost Price) <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text fw-bold text-muted">Rs.</span>
                            <!-- Type changed to text to support comma formatting -->
                            <input type="text" class="form-control currency-input" name="purchase_rate" id="purchase_rate" required value="<?php echo $product['purchase_rate']; ?>" placeholder="0.00" oninput="formatCurrencyInput(this)">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Sale Rate (Selling Price) <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text fw-bold text-muted">Rs.</span>
                            <!-- Type changed to text to support comma formatting -->
                            <input type="text" class="form-control currency-input" name="sale_rate" id="sale_rate" required value="<?php echo $product['sale_rate']; ?>" placeholder="0.00" oninput="formatCurrencyInput(this)">
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 text-muted">
                
                <div class="d-flex justify-content-end gap-3 flex-wrap">
                    <a href="view_products.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-1"></i> Update Product
                    </button>
                </div>
            </form>
        </div>
        </div>
        <div class="col-lg-4">
            <?php renderPageGuide([
                'eyebrow' => 'Product care / 3 checks', 'title' => 'Make every unit count.',
                'description' => 'You are updating ' . $product['product_name'] . '. Current recorded stock: ' . $product['current_stock'] . ' units.',
                'steps' => [
                    ['title' => 'Review medicine & supplier', 'text' => 'Keep the name, composition and company link accurate for product searches and new bills.'],
                    ['title' => 'Use stock edits with care', 'text' => 'This form directly changes current stock; it does not create a purchase bill or payment. Record new stock arrivals through a purchase bill.'],
                    ['title' => 'Confirm both rates', 'text' => 'Purchase rate is the cost per unit; sale rate is the selling price per unit. Changing these does not alter previous bill items.'],
                ],
                'links' => [
                    ['label' => 'Back to product catalogue', 'href' => 'view_products.php', 'icon' => 'package'],
                    ['label' => 'Record a stock purchase', 'href' => 'create_purchase_bill.php', 'icon' => 'truck'],
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

        // Format existing values on page load
        window.addEventListener('DOMContentLoaded', (event) => {
            const purchaseInput = document.getElementById('purchase_rate');
            const saleInput = document.getElementById('sale_rate');
            if(purchaseInput) formatCurrencyInput(purchaseInput);
            if(saleInput) formatCurrencyInput(saleInput);
        });
    </script>
</body>
</html>