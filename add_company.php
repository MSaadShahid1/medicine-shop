<?php
require_once 'config.php';
require_once 'page_overview.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    
    // Validate required fields
    if (empty(trim($company_name))) {
        $error_message = 'Company name is required!';
    } else {
        // 1. Check if the company name already exists in the database
        $check_sql = "SELECT id FROM companies WHERE company_name = '$company_name'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            // Enforce uniqueness
            $error_message = "The company name '$company_name' already exists. Please enter a unique name.";
        } else {
            // 2. Insert into database if unique
            $sql = "INSERT INTO companies (company_name, contact_person, phone, address) 
                    VALUES ('$company_name', '$contact_person', '$phone', '$address')";
            
            if ($conn->query($sql) === TRUE) {
                $success_message = 'Company added successfully!';
                // Clear form
                $_POST = array();
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
    <title>Add Company - Medicine Shop</title>
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
                    <h2><i class="fas fa-building me-2"></i>Add New Company</h2>
                    <p>Register a new supplier or business partner</p>
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
            <div class="po-section-head"><div><span class="po-eyebrow">Build your supplier network</span><h3>A new business relationship</h3><p>Start with the company name. Contact details can be added now or later.</p></div></div>
            <form method="POST" action="">
                <h4 class="po-field-heading">01 / Company identity</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Company Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-building text-muted"></i></span>
                            <input type="text" class="form-control" name="company_name" placeholder="Enter company name" required value="<?php echo isset($_POST['company_name']) ? $_POST['company_name'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Contact Person</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" class="form-control" name="contact_person" placeholder="Enter contact person name" value="<?php echo isset($_POST['contact_person']) ? $_POST['contact_person'] : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <h4 class="po-field-heading">02 / Contact & location</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Phone Number</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-phone-alt text-muted"></i></span>
                            <input type="text" class="form-control fw-bold" id="phoneFormat" name="phone" placeholder="03XX-XXXXXXX" value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>" maxlength="12">
                        </div>
                        <small class="text-muted mt-1 d-block">Auto-formats to: 0300-1234567</small>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Address</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                            <input type="text" class="form-control" name="address" placeholder="Enter full address" value="<?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 text-muted">
                
                <div class="d-flex justify-content-end gap-3 flex-wrap">
                    <button type="reset" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">
                        <i class="fas fa-redo me-1"></i> Reset Fields
                    </button>
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Save Company
                    </button>
                </div>
            </form>
        </div>
        </div>
        <div class="col-lg-4">
            <?php renderPageGuide([
                'eyebrow' => 'Supplier setup / 3 steps', 'title' => 'A clear start, together.',
                'description' => 'Companies supply your medicines. Their purchase bills and payments belong in the supplier ledger.',
                'steps' => [
                    ['title' => 'Use a distinct company name', 'text' => 'A company name is required. New suppliers cannot share an existing company name.'],
                    ['title' => 'Keep the right contact close', 'text' => 'Add a contact person, phone and address so the directory is useful when ordering stock.'],
                    ['title' => 'Bring history in separately', 'text' => 'Saving a profile does not create stock or debt. Use Add Opening Balance for a historical supplier balance.'],
                ],
                'links' => [
                    ['label' => 'Back to supplier directory', 'href' => 'view_companies.php', 'icon' => 'building-2'],
                    ['label' => 'Add an opening balance', 'href' => 'add_opening_balance.php', 'icon' => 'file-input'],
                ],
            ]); ?>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto Format Phone Number Script -->
    <script>
        document.getElementById('phoneFormat').addEventListener('input', function (e) {
            // Remove all non-numeric characters
            let input = e.target.value.replace(/\D/g, '');
            
            // Format as XXXX-XXXXXXX
            if (input.length > 4) {
                input = input.substring(0, 4) + '-' + input.substring(4, 11);
            }
            
            e.target.value = input;
        });
    </script>
</body>
</html>