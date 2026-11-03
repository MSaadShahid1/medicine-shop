<?php
require_once 'config.php';
require_once 'page_overview.php';

$success_message = '';
$error_message = '';

if (isset($_GET['assistant_created']) && $_GET['assistant_created'] === '1') {
    $success_message = 'Customer created with your AI assistant. You can find them in the customer directory.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $conn->real_escape_string(trim($_POST['customer_name']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    
    // Validate required fields
    if (empty($customer_name)) {
        $error_message = 'Customer name is required!';
    } else {
        // 1. Check if the customer already exists
        $check_sql = "SELECT id FROM customers WHERE customer_name = '$customer_name'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "A customer named '$customer_name' is already registered in the system.";
        } else {
            // 2. Insert into the new customers table
            $sql = "INSERT INTO customers (customer_name, customer_phone, address, created_at) 
                    VALUES ('$customer_name', '$phone', '$address', NOW())";
            
            if ($conn->query($sql) === TRUE) {
                $success_message = 'Customer registered successfully!';
                // Clear post data to empty the form
                $_POST = array();
            } else {
                $error_message = 'Error saving customer: ' . $conn->error;
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
    <title>Add Customer - Medicine Shop</title>
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
                    <h2><i class="fas fa-user-plus me-2"></i>Add New Customer</h2>
                    <p>Register a customer to track their purchase history and ledger</p>
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
            <div class="po-section-head"><div><span class="po-eyebrow">Make it personal</span><h3>A customer worth remembering</h3><p>A name is all you need to begin. Add contact details for a more useful directory.</p></div></div>
            <form method="POST" action="">
                <h4 class="po-field-heading">01 / Name & contact</h4>
                <div class="row">
                    <!-- Customer Name -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Customer Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" class="form-control" name="customer_name" placeholder="e.g. Zubair" required value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Phone Number -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Phone Number</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-phone-alt text-muted"></i></span>
                            <input type="text" class="form-control fw-bold" id="phoneFormat" name="phone" placeholder="03XX-XXXXXXX" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" maxlength="12">
                        </div>
                        <small class="text-muted mt-1 d-block">Formats to: 0300-1234567</small>
                    </div>
                </div>
                
                <h4 class="po-field-heading">02 / Location</h4>
                <div class="row">
                    <!-- Address -->
                    <div class="col-md-12 mb-4">
                        <label class="form-label fw-bold text-secondary">Address / Area</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                            <input type="text" class="form-control" name="address" placeholder="Enter customer address or area" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 text-muted">
                
                <!-- Form Actions -->
                <div class="d-flex justify-content-end gap-3 flex-wrap">
                    <button type="reset" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">
                        <i class="fas fa-redo me-1"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-1"></i> Register Customer
                    </button>
                </div>
            </form>
        </div>
        </div>
        <div class="col-lg-4">
            <?php renderPageGuide([
                'eyebrow' => 'Customer setup / 3 steps', 'title' => 'Start a purchase history.',
                'description' => 'Customers are your buyers, not suppliers. Their ledger brings their recorded purchases together.',
                'steps' => [
                    ['title' => 'Choose a unique name', 'text' => 'A customer name is required. Check the directory first to avoid creating a duplicate profile.'],
                    ['title' => 'Add helpful details', 'text' => 'A phone number and address or area help distinguish customers when selecting a buyer.'],
                    ['title' => 'Connect the next sale', 'text' => 'Choose this customer when creating a sales bill. Sales are paid in full at creation, not tracked as outstanding customer debt.'],
                ],
                'links' => [
                    ['label' => 'Back to customer directory', 'href' => 'view_customers.php', 'icon' => 'users'],
                    ['label' => 'Create a sales bill', 'href' => 'create_sales_bill.php', 'icon' => 'shopping-cart'],
                ],
            ]); ?>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto Format Phone Number (Pakistani standard)
        document.getElementById('phoneFormat').addEventListener('input', function (e) {
            let input = e.target.value.replace(/\D/g, '');
            if (input.length > 4) {
                input = input.substring(0, 4) + '-' + input.substring(4, 11);
            }
            e.target.value = input;
        });
    </script>
</body>
</html>