<?php
require_once 'config.php';
require_once 'page_overview.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

// Check if ID is valid
if ($id == 0) {
    header("Location: view_companies.php");
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    
    // VALIDATION: Check if company name is empty
    if (empty(trim($company_name))) {
        $error_message = 'Company name cannot be empty!';
    } else {
        $sql = "UPDATE companies SET 
                company_name = '$company_name', 
                contact_person = '$contact_person', 
                phone = '$phone', 
                address = '$address' 
                WHERE id = $id";
        
        if ($conn->query($sql) === TRUE) {
            // REDIRECTION: Redirect to view_companies.php on success
            header("Location: view_companies.php?msg=updated");
            exit(); 
        } else {
            $error_message = 'Error: ' . $conn->error;
        }
    }
}

// Fetch Existing Data
$sql = "SELECT * FROM companies WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "Company not found!";
    exit();
}

$company = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Company - Medicine Shop</title>
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
                    <h2><i class="fas fa-edit me-2"></i>Edit Company</h2>
                    <p>Update supplier details for <strong><?php echo $company['company_name']; ?></strong></p>
                </div>
            </div>

            <div class="page-header-actions">
                <a href="view_companies.php" class="btn btn-outline-light-soft px-4 py-2 rounded-pill fw-bold">
                    <i class="fas fa-arrow-left me-2"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Error Messages (Success is handled via redirect) -->
        <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-pill px-4">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="row po-form-row">
        <div class="col-lg-8">
        <div class="content-card po-form-card">
            <div class="po-section-head"><div><span class="po-eyebrow">Supplier profile</span><h3>Keep the connection current</h3><p>Update contact information without changing purchase or payment history.</p></div></div>
            <form method="POST" action="">
                <h4 class="po-field-heading">01 / Company identity</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Company Name <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-building text-muted"></i></span>
                            <input type="text" class="form-control" name="company_name" required value="<?php echo $company['company_name']; ?>" placeholder="Enter company name">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Contact Person</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" class="form-control" name="contact_person" value="<?php echo $company['contact_person']; ?>" placeholder="Enter contact person name">
                        </div>
                    </div>
                </div>
                
                <h4 class="po-field-heading">02 / Contact & location</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Phone Number</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-phone-alt text-muted"></i></span>
                            <input type="text" class="form-control fw-bold" id="phoneFormat" name="phone" value="<?php echo $company['phone']; ?>" placeholder="03XX-XXXXXXX" maxlength="12">
                        </div>
                        <small class="text-muted mt-1 d-block">Auto-formats to: 0300-1234567</small>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold text-secondary">Address</label>
                        <div class="input-group shadow-sm rounded">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                            <input type="text" class="form-control" name="address" value="<?php echo $company['address']; ?>" placeholder="Enter full address">
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 text-muted">
                
                <div class="d-flex justify-content-end gap-3">
                    <a href="view_companies.php" class="btn btn-light px-4 py-2 rounded-pill shadow-sm fw-bold border">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-1"></i> Update Company
                    </button>
                </div>
            </form>
        </div>
        </div>
        <div class="col-lg-4">
            <?php renderPageGuide([
                'eyebrow' => 'Supplier care / 3 checks', 'title' => 'The same partner. Better details.',
                'description' => 'You are updating ' . $company['company_name'] . '. The existing supplier record stays connected to its history.',
                'steps' => [
                    ['title' => 'Confirm the company name', 'text' => 'Keep a recognisable name for selecting this supplier on products and purchase bills.'],
                    ['title' => 'Check contact details', 'text' => 'Review the contact person, phone and address before saving the profile.'],
                    ['title' => 'Use the ledger for balances', 'text' => 'This form changes profile details only. Review purchase bills and recorded payments in the supplier ledger.'],
                ],
                'links' => [
                    ['label' => 'View this supplier ledger', 'href' => 'company_details.php?id=' . $id, 'icon' => 'book-open'],
                    ['label' => 'Back to supplier directory', 'href' => 'view_companies.php', 'icon' => 'building-2'],
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

        // Trigger the formatting on load in case the database value needs it
        window.addEventListener('DOMContentLoaded', (event) => {
            let phoneInput = document.getElementById('phoneFormat');
            let eventInput = new Event('input');
            phoneInput.dispatchEvent(eventInput);
        });
    </script>
</body>
</html>