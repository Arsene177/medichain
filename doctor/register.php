<?php
session_start();
require_once "../config/database.php";

// Redirect if already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: ../dashboard.php");
    exit;
}

// Initialize variables
$username = $password = $confirm_password = $full_name = $specialization = $hospital = $phone_number = $license_number = 
$email = $date_of_birth = $gender = $address = $emergency_contact = $id_type = $id_number = $years_of_experience = 
$qualification = $certification = $consultation_fee = "";

// Initialize error variables
$username_err = $password_err = $confirm_password_err = $full_name_err = $specialization_err = $hospital_err = 
$phone_number_err = $license_number_err = $email_err = $date_of_birth_err = $gender_err = $address_err = 
$emergency_contact_err = $id_type_err = $id_number_err = $years_of_experience_err = $qualification_err = 
$certification_err = $consultation_fee_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Add error logging
    error_log("Form submitted");
    
    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else{
        $sql = "SELECT id FROM users WHERE username = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = trim($_POST["username"]);
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $username_err = "This username is already taken.";
                } else{
                    $username = trim($_POST["username"]);
                }
            } else{
                error_log("Database error: " . mysqli_error($conn));
                $error = "Oops! Something went wrong. Please try again later.";
            }
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
    
    // Validate other fields
    if(empty(trim($_POST["full_name"]))){
        $full_name_err = "Please enter your full name.";
    } else{
        $full_name = trim($_POST["full_name"]);
    }
    
    if(empty(trim($_POST["specialization"]))){
        $specialization_err = "Please enter your specialization.";
    } else{
        $specialization = trim($_POST["specialization"]);
    }
    
    if(empty(trim($_POST["hospital"]))){
        $hospital_err = "Please enter your hospital.";
    } else{
        $hospital = trim($_POST["hospital"]);
    }
    
    if(empty(trim($_POST["phone_number"]))){
        $phone_number_err = "Please enter your phone number.";
    } else{
        $phone_number = trim($_POST["phone_number"]);
    }
    
    if(empty(trim($_POST["license_number"]))){
        $license_number_err = "Please enter your license number.";
    } else{
        $license_number = trim($_POST["license_number"]);
    }

    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter your email.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)){
        $email_err = "Please enter a valid email address.";
    } else{
        $email = trim($_POST["email"]);
    }

    // Validate date of birth
    if(empty(trim($_POST["date_of_birth"]))){
        $date_of_birth_err = "Please enter your date of birth.";
    } else{
        $date_of_birth = trim($_POST["date_of_birth"]);
    }

    // Validate gender
    if(empty(trim($_POST["gender"]))){
        $gender_err = "Please select your gender.";
    } else{
        $gender = trim($_POST["gender"]);
    }

    // Validate address
    if(empty(trim($_POST["address"]))){
        $address_err = "Please enter your address.";
    } else{
        $address = trim($_POST["address"]);
    }

    // Validate emergency contact
    if(empty(trim($_POST["emergency_contact"]))){
        $emergency_contact_err = "Please enter emergency contact number.";
    } else{
        $emergency_contact = trim($_POST["emergency_contact"]);
    }

    // Validate ID type and number
    if(empty(trim($_POST["id_type"]))){
        $id_type_err = "Please select ID type.";
    } else{
        $id_type = trim($_POST["id_type"]);
    }

    if(empty(trim($_POST["id_number"]))){
        $id_number_err = "Please enter ID number.";
    } else{
        $id_number = trim($_POST["id_number"]);
    }

    // Validate years of experience
    if(empty(trim($_POST["years_of_experience"]))){
        $years_of_experience_err = "Please enter years of experience.";
    } elseif(!is_numeric(trim($_POST["years_of_experience"])) || trim($_POST["years_of_experience"]) < 0){
        $years_of_experience_err = "Please enter a valid number of years.";
    } else{
        $years_of_experience = trim($_POST["years_of_experience"]);
    }

    // Validate qualification
    if(empty(trim($_POST["qualification"]))){
        $qualification_err = "Please enter your qualification.";
    } else{
        $qualification = trim($_POST["qualification"]);
    }

    // Validate certification
    if(empty(trim($_POST["certification"]))){
        $certification_err = "Please enter your certification.";
    } else{
        $certification = trim($_POST["certification"]);
    }

    // Validate consultation fee
    if(empty(trim($_POST["consultation_fee"]))){
        $consultation_fee_err = "Please enter consultation fee.";
    } elseif(!is_numeric(trim($_POST["consultation_fee"])) || trim($_POST["consultation_fee"]) < 0){
        $consultation_fee_err = "Please enter a valid consultation fee.";
    } else{
        $consultation_fee = trim($_POST["consultation_fee"]);
    }

    // Check input errors before inserting in database
    if(empty($username_err) && empty($password_err) && empty($confirm_password_err) && 
       empty($full_name_err) && empty($specialization_err) && empty($hospital_err) && 
       empty($phone_number_err) && empty($license_number_err) && empty($email_err) &&
       empty($date_of_birth_err) && empty($gender_err) && empty($address_err) &&
       empty($emergency_contact_err) && empty($id_type_err) && empty($id_number_err) &&
       empty($years_of_experience_err) && empty($qualification_err) && empty($certification_err) &&
       empty($consultation_fee_err)){
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into users table
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'doctor')";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "ss", $param_username, $param_password);
                $param_username = $username;
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                
                if(mysqli_stmt_execute($stmt)){
                    $user_id = mysqli_insert_id($conn);
                    
                    // Insert into doctors table
                    $sql = "INSERT INTO doctors (user_id, full_name, specialization, hospital, phone_number, license_number, 
                            email, date_of_birth, gender, address, emergency_contact, id_type, id_number, 
                            years_of_experience, qualification, certification, consultation_fee, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    if($stmt = mysqli_prepare($conn, $sql)){
                        mysqli_stmt_bind_param($stmt, "isssssssssssssssd", 
                            $user_id, $full_name, $specialization, $hospital, $phone_number, $license_number,
                            $email, $date_of_birth, $gender, $address, $emergency_contact, $id_type, $id_number,
                            $years_of_experience, $qualification, $certification, $consultation_fee);
                        
                        if(mysqli_stmt_execute($stmt)){
                            mysqli_commit($conn);
                            $_SESSION['success'] = "Registration successful! Your account is pending approval. You can check your registration status using your email and license number.";
                            $_SESSION['registration_email'] = $email;
                            $_SESSION['registration_license'] = $license_number;
                            header("location: ../index.php");
                            exit;
                        } else {
                            error_log("Error inserting doctor data: " . mysqli_error($conn));
                            throw new Exception("Error inserting doctor data: " . mysqli_error($conn));
                        }
                    }
                } else {
                    error_log("Error inserting user data: " . mysqli_error($conn));
                    throw new Exception("Error inserting user data: " . mysqli_error($conn));
                }
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Registration error: " . $e->getMessage());
            $error = "Registration failed: " . $e->getMessage();
        }
    } else {
        $error = "Please fix the errors in the form.";
    }
    
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 1000px;
            margin: 0 auto;
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 {
            color: #0083B0;
            font-size: 2rem;
        }
        .form-control:focus {
            border-color: #0083B0;
            box-shadow: 0 0 0 0.2rem rgba(0,131,176,0.25);
        }
        .btn-primary {
            background-color: #0083B0;
            border-color: #0083B0;
        }
        .btn-primary:hover {
            background-color: #006d94;
            border-color: #006d94;
        }
        .section-title {
            color: #0083B0;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0083B0;
        }
        .status-check {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
            border: 1px solid #dee2e6;
        }
        .status-check h5 {
            color: #0083B0;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="logo">
                <h1>Doctor Registration</h1>
                <p class="text-muted">Join MediChain Cameroon as a healthcare provider</p>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div class="status-check">
                    <h5>Check Registration Status</h5>
                    <p>You can check your registration status using your email and license number.</p>
                    <form action="../check_status.php" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo isset($_SESSION['registration_email']) ? $_SESSION['registration_email'] : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">License Number</label>
                                <input type="text" name="license_number" class="form-control" value="<?php echo isset($_SESSION['registration_license']) ? $_SESSION['registration_license'] : ''; ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Check Status</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <h4 class="section-title">Account Information</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>
                </div>

                <h4 class="section-title">Personal Information</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control <?php echo (!empty($date_of_birth_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $date_of_birth; ?>">
                        <span class="invalid-feedback"><?php echo $date_of_birth_err; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select <?php echo (!empty($gender_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Gender</option>
                            <option value="M" <?php echo $gender == "M" ? "selected" : ""; ?>>Male</option>
                            <option value="F" <?php echo $gender == "F" ? "selected" : ""; ?>>Female</option>
                            <option value="Other" <?php echo $gender == "Other" ? "selected" : ""; ?>>Other</option>
                        </select>
                        <span class="invalid-feedback"><?php echo $gender_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone_number" class="form-control <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone_number; ?>">
                        <span class="invalid-feedback"><?php echo $phone_number_err; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo $address; ?></textarea>
                        <span class="invalid-feedback"><?php echo $address_err; ?></span>
                    </div>
                </div>

                <h4 class="section-title">Professional Information</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control <?php echo (!empty($specialization_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $specialization; ?>">
                        <span class="invalid-feedback"><?php echo $specialization_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hospital</label>
                        <input type="text" name="hospital" class="form-control <?php echo (!empty($hospital_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $hospital; ?>">
                        <span class="invalid-feedback"><?php echo $hospital_err; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">License Number</label>
                        <input type="text" name="license_number" class="form-control <?php echo (!empty($license_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $license_number; ?>">
                        <span class="invalid-feedback"><?php echo $license_number_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" name="years_of_experience" class="form-control <?php echo (!empty($years_of_experience_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $years_of_experience; ?>">
                        <span class="invalid-feedback"><?php echo $years_of_experience_err; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control <?php echo (!empty($qualification_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $qualification; ?>">
                        <span class="invalid-feedback"><?php echo $qualification_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Certification</label>
                        <input type="text" name="certification" class="form-control <?php echo (!empty($certification_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $certification; ?>">
                        <span class="invalid-feedback"><?php echo $certification_err; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Consultation Fee (XAF)</label>
                        <input type="number" name="consultation_fee" class="form-control <?php echo (!empty($consultation_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $consultation_fee; ?>">
                        <span class="invalid-feedback"><?php echo $consultation_fee_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Emergency Contact</label>
                        <input type="tel" name="emergency_contact" class="form-control <?php echo (!empty($emergency_contact_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $emergency_contact; ?>">
                        <span class="invalid-feedback"><?php echo $emergency_contact_err; ?></span>
                    </div>
                </div>

                <h4 class="section-title">Identity Verification</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ID Type</label>
                        <select name="id_type" class="form-select <?php echo (!empty($id_type_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select ID Type</option>
                            <option value="National ID" <?php echo $id_type == "National ID" ? "selected" : ""; ?>>National ID</option>
                            <option value="Passport" <?php echo $id_type == "Passport" ? "selected" : ""; ?>>Passport</option>
                            <option value="Driving License" <?php echo $id_type == "Driving License" ? "selected" : ""; ?>>Driving License</option>
                            <option value="Other" <?php echo $id_type == "Other" ? "selected" : ""; ?>>Other</option>
                        </select>
                        <span class="invalid-feedback"><?php echo $id_type_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ID Number</label>
                        <input type="text" name="id_number" class="form-control <?php echo (!empty($id_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $id_number; ?>">
                        <span class="invalid-feedback"><?php echo $id_number_err; ?></span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Register</button>
                    <a href="../index.php" class="btn btn-outline-secondary">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 