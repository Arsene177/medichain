<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a patient
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient"){
    header("location: ../index.php");
    exit;
}

// Get unread notification count
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$unread_stmt = mysqli_prepare($conn, $unread_sql);
mysqli_stmt_bind_param($unread_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($unread_stmt);
$unread_result = mysqli_stmt_get_result($unread_stmt);
$unread_row = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_row['count'];

// Get patient information
$patient_sql = "SELECT p.*, u.username 
                FROM patients p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = ?";
$patient_stmt = mysqli_prepare($conn, $patient_sql);
mysqli_stmt_bind_param($patient_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($patient_stmt);
$patient_result = mysqli_stmt_get_result($patient_stmt);
$patient = mysqli_fetch_assoc($patient_result);

// Handle profile update
$success_message = $error_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    if(empty($_POST["full_name"]) || empty($_POST["phone_number"]) || empty($_POST["email"]) || empty($_POST["address"])) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Update patient information
        $update_sql = "UPDATE patients SET 
                      full_name = ?, 
                      phone_number = ?, 
                      email = ?, 
                      address = ? 
                      WHERE user_id = ?";
        
        if($stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($stmt, "ssssi", 
                $_POST["full_name"],
                $_POST["phone_number"],
                $_POST["email"],
                $_POST["address"],
                $_SESSION["id"]
            );
            
            if(mysqli_stmt_execute($stmt)) {
                $success_message = "Profile updated successfully.";
                
                // Refresh patient data
                mysqli_stmt_execute($patient_stmt);
                $patient = mysqli_fetch_assoc($patient_result);
            } else {
                $error_message = "Error updating profile. Please try again.";
            }
        }
    }
    
    // Handle password change
    if(!empty($_POST["new_password"]) && !empty($_POST["confirm_password"])) {
        if($_POST["new_password"] !== $_POST["confirm_password"]) {
            $error_message = "New passwords do not match.";
        } else {
            $password_sql = "UPDATE users SET password = ? WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $password_sql)) {
                $hashed_password = password_hash($_POST["new_password"], PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt, "si", $hashed_password, $_SESSION["id"]);
                
                if(mysqli_stmt_execute($stmt)) {
                    $success_message .= " Password updated successfully.";
                } else {
                    $error_message = "Error updating password. Please try again.";
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
    <title>My Profile - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .profile-card {
            transition: transform 0.2s;
        }
        .profile-card:hover {
            transform: translateY(-5px);
        }
        .profile-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 15px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">My Profile</h1>

        <!-- Navigation -->
        <nav class="nav nav-pills mb-4">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="profile.php">Profile</a>
            <a class="nav-link" href="appointments.php">Appointments</a>
            <a class="nav-link" href="notifications.php">
                Notifications
                <?php if($unread_count > 0): ?>
                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link" href="medical_records.php">Medical Records</a>
            <a class="nav-link text-danger" href="../logout.php">Logout</a>
        </nav>

        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-4 mb-4">
                <div class="card profile-card">
                    <div class="card-body text-center">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($patient["full_name"], 0, 1)); ?>
                        </div>
                        <h4 class="card-title"><?php echo htmlspecialchars($patient["full_name"]); ?></h4>
                        <p class="text-muted">Patient</p>
                        <p class="mb-1"><strong>Medical Record ID:</strong> <?php echo htmlspecialchars($patient["medical_record_id"]); ?></p>
                        <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($patient["username"]); ?></p>
                        <p class="mb-1"><strong>Gender:</strong> <?php echo $patient["gender"] == "M" ? "Male" : ($patient["gender"] == "F" ? "Female" : "Other"); ?></p>
                        <p class="mb-1"><strong>Date of Birth:</strong> <?php echo date("F j, Y", strtotime($patient["date_of_birth"])); ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($patient["phone_number"]); ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($patient["email"]); ?></p>
                        <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($patient["address"]); ?></p>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="col-md-8">
                <div class="card profile-card">
                    <div class="card-body">
                        <h5 class="card-title">Edit Profile</h5>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($patient["full_name"]); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($patient["phone_number"]); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($patient["email"]); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($patient["address"]); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="card profile-card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Change Password</h5>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 