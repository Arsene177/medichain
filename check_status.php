<?php
session_start();
require_once "config/database.php";

$status_message = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $license_number = trim($_POST["license_number"]);
    
    if (empty($email) || empty($license_number)) {
        $status_message = "Please provide both email and license number.";
    } else {
        $sql = "SELECT d.status, d.rejection_reason, d.full_name 
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.email = ? AND d.license_number = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $email, $license_number);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $status, $rejection_reason, $full_name);
                    mysqli_stmt_fetch($stmt);
                    
                    switch ($status) {
                        case "pending":
                            $status_message = "Dear Dr. " . $full_name . ", your registration is still pending approval. Our admin team will review your application shortly.";
                            break;
                        case "approved":
                            $status_message = "Dear Dr. " . $full_name . ", your registration has been approved! You can now login to your account.";
                            break;
                        case "rejected":
                            $status_message = "Dear Dr. " . $full_name . ", your registration has been rejected. Reason: " . ($rejection_reason ?: "Not specified");
                            break;
                        default:
                            $status_message = "Unable to determine status.";
                    }
                } else {
                    $status_message = "No registration found with the provided email and license number.";
                }
            } else {
                $status_message = "Oops! Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Registration Status - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .status-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .status-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .status-header h1 {
            color: #0083B0;
            font-size: 2rem;
        }
        .alert {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-container">
            <div class="status-header">
                <h1>Check Registration Status</h1>
                <p class="text-muted">Enter your email and license number to check your registration status</p>
            </div>

            <?php if (!empty($status_message)): ?>
                <div class="alert <?php echo $status === 'approved' ? 'alert-success' : ($status === 'rejected' ? 'alert-danger' : 'alert-warning'); ?>">
                    <?php echo $status_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">License Number</label>
                    <input type="text" name="license_number" class="form-control" value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>" required>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Check Status</button>
                    <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 