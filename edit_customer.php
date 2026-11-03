<?php
require_once 'config.php';
require_once 'page_overview.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';
$warning_message = '';

// 1. Validate ID
if ($id == 0) {
    header("Location: view_customers.php");
    exit();
}

// 2. Fetch Existing Customer Data
$sql = "SELECT * FROM customers WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "Customer record not found!";
    exit();
}

$customer = $result->fetch_assoc();

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $conn->real_escape_string(trim($_POST['customer_name']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    
    // Validation
    if (empty($customer_name)) {
        $error_message = 'Customer name is required!';
    } else {
        // Check if values actually changed
        if ($customer_name == $customer['customer_name'] && 
            $phone == $customer['customer_phone'] && 
            $address == $customer['address']) {
            
            $warning_message = 'No changes were detected in the customer profile.';
        } else {
            // Check for duplicate name (excluding current ID)
            $check_sql = "SELECT id FROM customers WHERE customer_name = '$customer_name' AND id != $id";
            $check_result = $conn->query($check_sql);

            if ($check_result && $check_result->num_rows > 0) {
                $error_message = "Another customer with the name '$customer_name' already exists.";
            } else {
                // Update Database
                $sql_update = "UPDATE customers SET 
                               customer_name = '$customer_name', 
                               customer_phone = '$phone', 
                               address = '$address' 
                               WHERE id = $id";
                
                if ($conn->query($sql_update) === TRUE) {
                    header("Location: view_customers.php?msg=updated");
                    exit();
                } else {
                    $error_message = 'Error updating record: ' . $conn->error;
                }
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
    <title>Edit Customer - Medicine Shop</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body>
        
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Navigation Header -->
        <div class="page-header">
            <div class="page-header-left">
                <i class="fas fa-bars mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"></i>
                <div>
                    <h2><i class="fas fa-user-edit me-2"></i>Edit Customer</h2>
                    <p>Update profile details for <strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong></p>
                </div>
            </div>
            <a href="view_customers.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
        </div>

        <?php if ($warning_message): ?>
        <div class="alert alert-warning shadow-sm border-0 rounded-pill px-4"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $warning_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="row po-form-row">
        <div class="col-lg-8">
        <div class="content-card po-form-card">
            <div class="po-section-head"><div><span class="po-eyebrow">Customer profile</span><h3>Small details. Better service.</h3><p>Keep the buyer easy to find while preserving their existing sales history.</p></div></div>
            <form method="POST" action="">
                <h4 class="po-field-heading">01 / Name & contact</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Customer Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" class="form-control" name="customer_name" required value="<?php echo htmlspecialchars($customer['customer_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Phone Number</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-phone-alt text-muted"></i></span>
                            <input type="text" class="form-control fw-bold" id="phoneFormat" name="phone" value="<?php echo htmlspecialchars($customer['customer_phone']); ?>" maxlength="12">
                        </div>
                    </div>
                </div>
                
                <h4 class="po-field-heading">02 / Location</h4>
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <label class="form-label fw-bold text-secondary">Address / Area</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                            <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($customer['address']); ?>">
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 text-muted">
                
                <div class="d-flex justify-content-end gap-3 flex-wrap">
                    <a href="view_customers.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-1"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
        </div>
        <div class="col-lg-4">
            <?php renderPageGuide([
                'eyebrow' => 'Customer care / 3 checks', 'title' => 'Keep their story connected.',
                'description' => 'You are updating ' . $customer['customer_name'] . '. Existing invoices remain linked to this customer.',
                'steps' => [
                    ['title' => 'Keep the name distinct', 'text' => 'The name is required and cannot duplicate another customer profile.'],
                    ['title' => 'Review how to reach them', 'text' => 'Update the phone number and address or area so the directory stays useful.'],
                    ['title' => 'Review purchases separately', 'text' => 'Profile edits do not change sales bills. The customer ledger shows purchase history, not an outstanding debt account.'],
                ],
                'links' => [
                    ['label' => 'View this customer ledger', 'href' => 'customer_ledger.php?id=' . $id, 'icon' => 'book-open'],
                    ['label' => 'Back to customer directory', 'href' => 'view_customers.php', 'icon' => 'users'],
                ],
            ]); ?>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('phoneFormat').addEventListener('input', function (e) {
            let input = e.target.value.replace(/\D/g, '');
            if (input.length > 4) input = input.substring(0, 4) + '-' + input.substring(4, 11);
            e.target.value = input;
        });
    </script>
</body>
</html>