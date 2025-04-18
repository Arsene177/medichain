<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

$success = $error = "";

// Handle settings update
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST["update_system_settings"])) {
        // Update system settings
        $appointment_duration = $_POST["appointment_duration"];
        $max_appointments_per_day = $_POST["max_appointments_per_day"];
        $enable_waiting_list = isset($_POST["enable_waiting_list"]) ? 1 : 0;
        $enable_email_notifications = isset($_POST["enable_email_notifications"]) ? 1 : 0;
        
        $sql = "UPDATE system_settings SET 
                appointment_duration = ?,
                max_appointments_per_day = ?,
                enable_waiting_list = ?,
                enable_email_notifications = ?
                WHERE id = 1";
                
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iiii", 
                $appointment_duration,
                $max_appointments_per_day,
                $enable_waiting_list,
                $enable_email_notifications
            );
            
            if(mysqli_stmt_execute($stmt)) {
                $success = "System settings updated successfully.";
            } else {
                $error = "Error updating system settings.";
            }
        }
    }
    
    if(isset($_POST["update_email_settings"])) {
        // Update email settings
        $smtp_host = $_POST["smtp_host"];
        $smtp_port = $_POST["smtp_port"];
        $smtp_username = $_POST["smtp_username"];
        $smtp_password = $_POST["smtp_password"];
        $smtp_encryption = $_POST["smtp_encryption"];
        
        $sql = "UPDATE email_settings SET 
                smtp_host = ?,
                smtp_port = ?,
                smtp_username = ?,
                smtp_password = ?,
                smtp_encryption = ?
                WHERE id = 1";
                
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sisss", 
                $smtp_host,
                $smtp_port,
                $smtp_username,
                $smtp_password,
                $smtp_encryption
            );
            
            if(mysqli_stmt_execute($stmt)) {
                $success = "Email settings updated successfully.";
            } else {
                $error = "Error updating email settings.";
            }
        }
    }
}

// Fetch current settings
$system_settings = $conn->query("SELECT * FROM system_settings WHERE id = 1")->fetch_assoc();
$email_settings = $conn->query("SELECT * FROM email_settings WHERE id = 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>System Settings</h2>
                    <div>
                        <a href="../dashboard.php" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="../logout.php" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- System Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Appointment Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Appointment Duration (minutes)</label>
                                    <input type="number" name="appointment_duration" class="form-control" 
                                           value="<?php echo $system_settings['appointment_duration']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Max Appointments per Day</label>
                                    <input type="number" name="max_appointments_per_day" class="form-control" 
                                           value="<?php echo $system_settings['max_appointments_per_day']; ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="enable_waiting_list" class="form-check-input" 
                                               <?php echo $system_settings['enable_waiting_list'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enable Waiting List</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="enable_email_notifications" class="form-check-input" 
                                               <?php echo $system_settings['enable_email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enable Email Notifications</label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="update_system_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save System Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Email Settings -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Email Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control" 
                                           value="<?php echo $email_settings['smtp_host']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" name="smtp_port" class="form-control" 
                                           value="<?php echo $email_settings['smtp_port']; ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" name="smtp_username" class="form-control" 
                                           value="<?php echo $email_settings['smtp_username']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" name="smtp_password" class="form-control" 
                                           value="<?php echo $email_settings['smtp_password']; ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Encryption</label>
                                    <select name="smtp_encryption" class="form-select" required>
                                        <option value="tls" <?php echo $email_settings['smtp_encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $email_settings['smtp_encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $email_settings['smtp_encryption'] == 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-info w-100" onclick="testEmailSettings()">
                                        <i class="bi bi-envelope"></i> Test Email Settings
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="update_email_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Email Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testEmailSettings() {
            // Add AJAX call to test email settings
            alert("Email test functionality will be implemented here.");
        }
    </script>
</body>
</html> 