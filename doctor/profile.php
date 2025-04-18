<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$success_message = $error_message = "";

// Get doctor information
$sql = "SELECT d.*, u.email, u.username 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.user_id = ?";

if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_assoc($result);
}

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate input
    if(empty(trim($_POST["full_name"] ?? ''))){
        $full_name_err = "Please enter your full name.";
    } else{
        $full_name = trim($_POST["full_name"]);
    }
    
    if(empty(trim($_POST["hospital"] ?? ''))){
        $hospital_err = "Please enter your hospital.";
    } else{
        $hospital = trim($_POST["hospital"]);
    }
    
    if(empty(trim($_POST["phone_number"] ?? ''))){
        $phone_number_err = "Please enter your phone number.";
    } else{
        $phone_number = trim($_POST["phone_number"]);
    }
    
    if(empty(trim($_POST["address"] ?? ''))){
        $address_err = "Please enter your address.";
    } else{
        $address = trim($_POST["address"]);
    }
    
    if(empty(trim($_POST["emergency_contact"] ?? ''))){
        $emergency_contact_err = "Please enter your emergency contact.";
    } else{
        $emergency_contact = trim($_POST["emergency_contact"]);
    }
    
    if(empty(trim($_POST["consultation_fee"] ?? ''))){
        $consultation_fee_err = "Please enter your consultation fee.";
    } else{
        $consultation_fee = trim($_POST["consultation_fee"]);
    }
    
    // Check input errors before updating
    if(empty($full_name_err) && empty($hospital_err) && empty($phone_number_err) && 
       empty($address_err) && empty($emergency_contact_err) && empty($consultation_fee_err)){
        
        // Update doctor information
        $update_sql = "UPDATE doctors SET 
                      full_name = ?, 
                      hospital = ?, 
                      phone_number = ?,
                      address = ?,
                      emergency_contact = ?,
                      consultation_fee = ?
                      WHERE user_id = ?";
        
        if($update_stmt = mysqli_prepare($conn, $update_sql)){
            mysqli_stmt_bind_param($update_stmt, "sssssdi", 
                $full_name, 
                $hospital, 
                $phone_number,
                $address,
                $emergency_contact,
                $consultation_fee,
                $_SESSION["id"]
            );
            
            if(mysqli_stmt_execute($update_stmt)){
                if(mysqli_affected_rows($conn) > 0) {
                    $success_message = "Profile updated successfully!";
                    // Refresh doctor information
                    $refresh_sql = "SELECT d.*, u.email, u.username 
                                  FROM doctors d 
                                  JOIN users u ON d.user_id = u.id 
                                  WHERE d.user_id = ?";
                    $refresh_stmt = mysqli_prepare($conn, $refresh_sql);
                    mysqli_stmt_bind_param($refresh_stmt, "i", $_SESSION["id"]);
                    mysqli_stmt_execute($refresh_stmt);
                    $result = mysqli_stmt_get_result($refresh_stmt);
                    $doctor = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($refresh_stmt);
                } else {
                    $error_message = "No changes were made to your profile.";
                }
            } else {
                $error_message = "Error updating profile: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
        }
        .profile-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin: 2rem auto;
            max-width: 1000px;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #0083B0;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #0083B0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin: 0 auto 1rem;
        }
        .section-title {
            color: #0083B0;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0083B0;
        }
        .nav-link {
            color: #0083B0;
        }
        .nav-link:hover {
            color: #006d94;
        }
        .nav-link.active {
            color: #006d94;
            font-weight: bold;
        }
        .form-control:focus {
            border-color: #0083B0;
            box-shadow: 0 0 0 0.2rem rgba(0,131,176,0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Doctor Profile</h1>
                <div>
                    <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav nav-pills mb-4">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="profile.php">Profile</a>
                <a class="nav-link" href="appointments.php">Appointments</a>
                <a class="nav-link" href="patients.php">Patients</a>
                <a class="nav-link" href="settings.php">Settings</a>
            </nav>

            <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h2><?php echo htmlspecialchars($doctor['full_name']); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <h4 class="section-title">Personal Information</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($doctor['full_name']); ?>" disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($doctor['phone_number']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Emergency Contact</label>
                        <input type="tel" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($doctor['emergency_contact']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($doctor['address']); ?></textarea>
                    </div>
                </div>

                <h4 class="section-title">Professional Information</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($doctor['specialization']); ?>" disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hospital</label>
                        <input type="text" name="hospital" class="form-control" value="<?php echo htmlspecialchars($doctor['hospital']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">License Number</label>
                        <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($doctor['license_number']); ?>" disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Consultation Fee (XAF)</label>
                        <input type="number" name="consultation_fee" class="form-control" value="<?php echo htmlspecialchars($doctor['consultation_fee']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Qualification</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($doctor['qualification']); ?>" disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" class="form-control" value="<?php echo htmlspecialchars($doctor['years_of_experience']); ?>" disabled>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 