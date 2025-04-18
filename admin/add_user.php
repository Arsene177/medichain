<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../index.php");
    exit;
}

$username = $password = $confirm_password = $role = $full_name = $email = $phone_number = "";
$username_err = $password_err = $confirm_password_err = $role_err = $full_name_err = $email_err = $phone_number_err = "";

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                /* store result */
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $username_err = "This username is already taken.";
                } else{
                    $username = trim($_POST["username"]);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password must have at least 6 characters.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate role
    if(empty(trim($_POST["role"]))){
        $role_err = "Please select a role.";
    } else{
        $role = trim($_POST["role"]);
    }
    
    // Validate full name
    if(empty(trim($_POST["full_name"]))){
        $full_name_err = "Please enter full name.";
    } else{
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter email.";
    } else{
        $email = trim($_POST["email"]);
    }
    
    // Validate phone number
    if(empty(trim($_POST["phone_number"]))){
        $phone_number_err = "Please enter phone number.";
    } else{
        $phone_number = trim($_POST["phone_number"]);
    }
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err) && empty($full_name_err) && empty($email_err) && empty($phone_number_err)){
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into users table
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "sss", $param_username, $param_password, $param_role);
                $param_username = $username;
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                $param_role = $role;
                
                if(mysqli_stmt_execute($stmt)){
                    $user_id = mysqli_insert_id($conn);
                    
                    // If role is doctor, insert into doctors table
                    if($role == "doctor"){
                        $sql = "INSERT INTO doctors (user_id, full_name, email, phone_number, specialization, hospital, license_number, date_of_birth, gender, emergency_contact, id_type, id_number, years_of_experience, qualification, certification, consultation_fee, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        if($stmt = mysqli_prepare($conn, $sql)){
                            mysqli_stmt_bind_param($stmt, "isssssssssssisssd", 
                                $user_id, 
                                $full_name, 
                                $email, 
                                $phone_number, 
                                $_POST["specialization"], 
                                $_POST["hospital"], 
                                $_POST["license_number"], 
                                $_POST["date_of_birth"], 
                                $_POST["gender"], 
                                $_POST["emergency_contact"], 
                                $_POST["id_type"], 
                                $_POST["id_number"], 
                                $_POST["years_of_experience"], 
                                $_POST["qualification"], 
                                $_POST["certification"], 
                                $_POST["consultation_fee"],
                                $_POST["status"]
                            );
                            
                            if(!mysqli_stmt_execute($stmt)){
                                throw new Exception("Error inserting doctor data.");
                            }
                        }
                    } 
                    // If role is patient, insert into patients table
                    elseif($role == "patient"){
                        $sql = "INSERT INTO patients (user_id, full_name, date_of_birth, gender, phone_number, email, address, medical_record_id, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        if($stmt = mysqli_prepare($conn, $sql)){
                            mysqli_stmt_bind_param($stmt, "isssssssi", 
                                $user_id, 
                                $full_name, 
                                $_POST["date_of_birth"], 
                                $_POST["gender"], 
                                $phone_number, 
                                $email, 
                                $_POST["address"], 
                                $_POST["medical_record_id"],
                                $_SESSION["id"] // Admin who is registering the patient
                            );
                            
                            if(!mysqli_stmt_execute($stmt)){
                                throw new Exception("Error inserting patient data.");
                            }
                        }
                    }
                    
                    mysqli_commit($conn);
                    $_SESSION["success_msg"] = "User added successfully.";
                    header("location: manage_users.php");
                    exit();
                } else{
                    throw new Exception("Error inserting user data.");
                }
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - MediChain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-color: #e9ecef;
        }

        body {
            background: var(--light-bg);
            color: var(--primary-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar {
            background: var(--primary-color);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-heart-pulse"></i> MediChain
            </a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-person-plus"></i> Add New User</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="userForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                                    <span class="invalid-feedback"><?php echo $username_err; ?></span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" id="roleSelect">
                                        <option value="">Select Role</option>
                                        <option value="admin" <?php echo $role == "admin" ? "selected" : ""; ?>>Admin</option>
                                        <option value="doctor" <?php echo $role == "doctor" ? "selected" : ""; ?>>Doctor</option>
                                        <option value="patient" <?php echo $role == "patient" ? "selected" : ""; ?>>Patient</option>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $role_err; ?></span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                                    <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                                    <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone_number" class="form-control <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone_number; ?>">
                                    <span class="invalid-feedback"><?php echo $phone_number_err; ?></span>
                                </div>
                            </div>
                            
                            <!-- Doctor-specific fields -->
                            <div id="doctorFields" style="display: none;">
                                <h5 class="mb-3">Doctor Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Specialization</label>
                                        <input type="text" name="specialization" class="form-control" value="General">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Hospital</label>
                                        <input type="text" name="hospital" class="form-control" value="General Hospital">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">License Number</label>
                                        <input type="text" name="license_number" class="form-control" value="LIC123456">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="form-control" value="1990-01-01">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="M">Male</option>
                                            <option value="F">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact</label>
                                        <input type="text" name="emergency_contact" class="form-control" value="1234567890">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">ID Type</label>
                                        <input type="text" name="id_type" class="form-control" value="National ID">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Number</label>
                                        <input type="text" name="id_number" class="form-control" value="ID123456">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Years of Experience</label>
                                        <input type="number" name="years_of_experience" class="form-control" value="5">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Qualification</label>
                                        <input type="text" name="qualification" class="form-control" value="MD">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Certification</label>
                                        <input type="text" name="certification" class="form-control" value="Board Certified">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Consultation Fee</label>
                                        <input type="number" name="consultation_fee" class="form-control" value="50.00" step="0.01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="suspended">Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Patient-specific fields -->
                            <div id="patientFields" style="display: none;">
                                <h5 class="mb-3">Patient Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="form-control" value="1990-01-01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="M">Male</option>
                                            <option value="F">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2">123 Main St, City, Country</textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Medical Record ID</label>
                                        <input type="text" name="medical_record_id" class="form-control" value="MR<?php echo date('YmdHis'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">Add User</button>
                                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('roleSelect');
            const doctorFields = document.getElementById('doctorFields');
            const patientFields = document.getElementById('patientFields');
            
            // Function to show/hide fields based on role
            function toggleFields() {
                const role = roleSelect.value;
                
                if (role === 'doctor') {
                    doctorFields.style.display = 'block';
                    patientFields.style.display = 'none';
                } else if (role === 'patient') {
                    doctorFields.style.display = 'none';
                    patientFields.style.display = 'block';
                } else {
                    doctorFields.style.display = 'none';
                    patientFields.style.display = 'none';
                }
            }
            
            // Initial toggle
            toggleFields();
            
            // Add event listener for role changes
            roleSelect.addEventListener('change', toggleFields);
        });
    </script>
</body>
</html> 