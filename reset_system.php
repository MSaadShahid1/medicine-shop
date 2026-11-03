<?php
require_once 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $password = $_POST['password'];

    // Simple protection: Password is 'admin123' (You can change this)
    if ($password !== 'admin123') {
        $message = '<div class="alert alert-danger">Incorrect Password!</div>';
    } else {
        // Disable Foreign Key Checks to allow truncation
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        if ($action == 'transactions') {
            // 1. Clear Transactions ONLY (Keep Companies & Products)
            $conn->query("TRUNCATE TABLE sales_bill_items");
            $conn->query("TRUNCATE TABLE sales_bills");
            $conn->query("TRUNCATE TABLE payments");
            $conn->query("TRUNCATE TABLE purchase_bill_items");
            $conn->query("TRUNCATE TABLE purchase_bills");
            
            // Reset Product Stock to 0 (Optional: Remove this line if you want to keep current stock numbers)
            // $conn->query("UPDATE products SET current_stock = 0"); 
            
            $message = '<div class="alert alert-success">All transactions (Sales, Purchases, Payments) deleted. Companies and Products remain.</div>';
        } 
        elseif ($action == 'full') {
            // 2. Full Factory Reset
            $tables = ['sales_bill_items', 'sales_bills', 'payments', 'purchase_bill_items', 'purchase_bills', 'products', 'companies'];
            foreach ($tables as $table) {
                $conn->query("TRUNCATE TABLE $table");
            }
            $message = '<div class="alert alert-warning">System completely wiped. Database is empty.</div>';
        }

        // Re-enable Foreign Key Checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reset Tool</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 20% 15%, #fef2f2 0%, transparent 55%),
                        radial-gradient(circle at 80% 85%, #f8fafc 0%, transparent 55%),
                        #F8FAFC;
            height: 100vh; display: flex; align-items: center; justify-content: center;
            color: #111827;
        }
        .reset-card {
            background: #FFFFFF;
            padding: 40px; border-radius: 20px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 20px 45px rgba(17, 24, 39, 0.08);
            width: 100%; max-width: 500px; text-align: center; border-top: 4px solid #EF4444;
            position: relative; z-index: 1;
        }
        .danger-icon {
            width: 70px; height: 70px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: #EF4444;
            display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
            margin: 0 auto 15px;
        }
        .text-danger { color: #EF4444 !important; }
        .text-muted { color: #64748B !important; }
        .btn-warning { color: #78350f; background-color: #F59E0B; border-color: #F59E0B; border-radius: 12px; font-weight: 700; }
        .btn-danger { border-radius: 12px; background: #EF4444; border: none; font-weight: 700; }
        .form-control {
            border-radius: 10px; background-color: #fff;
            border: 1px solid #E2E8F0; color: #111827;
        }
        .form-control:focus { border-color: #EF4444; box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.12); color: #111827; }
        .form-label { color: #111827; }
        a.text-muted:hover { color: #EF4444 !important; }
    </style>
</head>
<body>

    <div class="reset-card">
        <div class="danger-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h2 class="text-danger fw-bold mb-3">Danger Zone</h2>
        <p class="text-muted mb-4">Use this tool to clean the database before handing over to the client.</p>
        
        <?php echo $message; ?>

        <form method="POST" onsubmit="return confirm('Are you strictly sure? This cannot be undone!');">
            <div class="mb-3 text-start">
                <label class="form-label fw-bold">Enter Admin Password to Confirm:</label>
                <input type="password" name="password" class="form-control" required placeholder="Enter: admin123">
            </div>

            <div class="d-grid gap-3">
                <button type="submit" name="action" value="transactions" class="btn btn-warning btn-lg">
                    Reset Transactions Only <br>
                    <small style="font-size: 0.7rem; color: #78350f;">(Keeps Products & Companies)</small>
                </button>

                <button type="submit" name="action" value="full" class="btn btn-danger btn-lg">
                    FULL FACTORY RESET <br>
                    <small style="font-size: 0.7rem; color: #fee2e2;">(Deletes Everything)</small>
                </button>
            </div>
        </form>
        
        <div class="mt-4">
            <a href="dashboard.php" class="text-decoration-none text-muted">← Go back to Dashboard</a>
        </div>
    </div>

</body>
</html>