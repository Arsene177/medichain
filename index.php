<?php
session_start();
require_once "config/database.php";

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    // Redirect based on role
    if($_SESSION["role"] === "admin") {
        header("location: admin/dashboard.php");
    } else if($_SESSION["role"] === "doctor") {
        header("location: doctor/dashboard.php");
    } else if($_SESSION["role"] === "patient") {
        header("location: patient/dashboard.php");
    } else {
        header("location: dashboard.php");
    }
    exit;
}

$username = $password = "";
$username_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    if(empty($username_err) && empty($password_err)){
        $sql = "SELECT u.id, u.username, u.password, u.role, d.status, d.rejection_reason 
                FROM users u 
                LEFT JOIN doctors d ON u.id = d.user_id 
                WHERE u.username = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role, $status, $rejection_reason);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Check if user is a doctor and account is suspended
                            if($role === "doctor" && $status === "suspended") {
                                $_SESSION["suspension_reason"] = $rejection_reason;
                                header("location: account_suspended.php");
                                exit;
                            }
                            // Check if user is a doctor and account is rejected
                            elseif($role === "doctor" && $status === "rejected") {
                                $login_err = "Your account has been banned. Reason: " . $rejection_reason;
                            } else {
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["username"] = $username;
                                $_SESSION["role"] = $role;
                                
                                // Redirect based on role
                                if($role === "admin") {
                                    header("location: admin/dashboard.php");
                                } else if($role === "doctor") {
                                    // Get doctor details for session
                                    $doctor_sql = "SELECT * FROM doctors WHERE user_id = ?";
                                    $doctor_stmt = mysqli_prepare($conn, $doctor_sql);
                                    mysqli_stmt_bind_param($doctor_stmt, "i", $id);
                                    mysqli_stmt_execute($doctor_stmt);
                                    $doctor_result = mysqli_stmt_get_result($doctor_stmt);
                                    $doctor = mysqli_fetch_assoc($doctor_result);
                                    
                                    $_SESSION["doctor_id"] = $doctor["id"];
                                    $_SESSION["full_name"] = $doctor["full_name"];
                                    $_SESSION["specialization"] = $doctor["specialization"];
                                    
                                    header("location: doctor/dashboard.php");
                                    exit;
                                } else if($role === "patient") {
                                    // Get patient details for session
                                    $patient_sql = "SELECT * FROM patients WHERE user_id = ?";
                                    $patient_stmt = mysqli_prepare($conn, $patient_sql);
                                    mysqli_stmt_bind_param($patient_stmt, "i", $id);
                                    mysqli_stmt_execute($patient_stmt);
                                    $patient_result = mysqli_stmt_get_result($patient_stmt);
                                    $patient = mysqli_fetch_assoc($patient_result);
                                    
                                    $_SESSION["patient_id"] = $patient["id"];
                                    $_SESSION["full_name"] = $patient["full_name"];
                                    
                                    header("location: patient/dashboard.php");
                                    exit;
                                } else {
                                    header("location: dashboard.php");
                                    exit;
                                }
                            }
                        } else{
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else{
                    $login_err = "Invalid username or password.";
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            mysqli_stmt_close($stmt);
        }
    }
    
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediChain Cameroon - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>MediChain Cameroon</h1>
        </div>
        
        <?php 
        if(!empty($login_err)){
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>    
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>
            <div class="mb-3">
                <input type="submit" class="btn btn-primary w-100" value="Login">
            </div>
            <div class="text-center">
                <p class="mb-2">Are you a doctor?</p>
                <a href="doctor/register.php" class="btn btn-outline-primary">
                    <i class="bi bi-person-plus"></i> Register as Doctor
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 