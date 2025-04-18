<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$doctor_id = $_SESSION["id"];
$success = $error = "";
$medical_record_id = "";

// Get doctor information
$doctor_sql = "SELECT full_name, hospital FROM doctors WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $doctor_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $doctor_result = mysqli_stmt_get_result($stmt);
    $doctor_info = mysqli_fetch_assoc($doctor_result);
}

// Handle patient registration
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Generate unique medical record ID
    $medical_record_id = "MR" . date("Ymd") . rand(1000, 9999);
    
    // Create user account
    $username = $_POST["username"]; // Get username from form
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); // Use provided password
    
    $user_sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'patient')";
    
    if($stmt = mysqli_prepare($conn, $user_sql)){
        mysqli_stmt_bind_param($stmt, "ss", $username, $password);
        
        if(mysqli_stmt_execute($stmt)){
            $user_id = mysqli_insert_id($conn);
            
            // Create patient record
            $patient_sql = "INSERT INTO patients (user_id, full_name, date_of_birth, gender, address, phone_number, 
                           emergency_contact, blood_type, medical_record_id, registered_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if($stmt = mysqli_prepare($conn, $patient_sql)){
                mysqli_stmt_bind_param($stmt, "issssssssi", 
                    $user_id,
                    $_POST["full_name"],
                    $_POST["date_of_birth"],
                    $_POST["gender"],
                    $_POST["address"],
                    $_POST["phone_number"],
                    $_POST["emergency_contact"],
                    $_POST["blood_type"],
                    $medical_record_id,
                    $doctor_id
                );
                
                if(mysqli_stmt_execute($stmt)){
                    $success = "Patient registered successfully. Medical Record ID: " . $medical_record_id . 
                              "<br>Username: " . $username . 
                              "<br>Password: " . $_POST["password"];
                } else {
                    $error = "Error creating patient record.";
                }
            }
        } else {
            $error = "Error creating user account.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Patient - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .medical-card {
            width: 85.6mm;
            height: 54mm;
            background: linear-gradient(135deg, #0083B0, #00B4DB);
            border-radius: 10px;
            padding: 15px;
            color: white;
            position: relative;
            overflow: hidden;
            margin: 20px auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .medical-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="50" y="50" font-size="40" text-anchor="middle" fill="rgba(255,255,255,0.1)">MC</text></svg>') repeat;
            opacity: 0.1;
        }
        .medical-card h3 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .medical-card .id-number {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 2px;
        }
        .medical-card .patient-name {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .medical-card .hospital {
            font-size: 12px;
            opacity: 0.8;
        }
        .medical-card .expiry {
            position: absolute;
            bottom: 15px;
            right: 15px;
            font-size: 10px;
            opacity: 0.8;
        }
        #cardContainer {
            position: fixed;
            top: -9999px;
            left: -9999px;
            background: white;
            padding: 20px;
            z-index: 9999;
        }
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 20px;
            border-radius: 10px;
            z-index: 10000;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Register New Patient</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div class="mt-3">
                            <button class="btn btn-primary" onclick="downloadCard()">
                                <i class="bi bi-download"></i> Download Medical Card
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card form-card">
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Blood Type</label>
                                <select class="form-select" name="blood_type" required>
                                    <option value="">Select blood type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone_number" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact</label>
                                <input type="tel" class="form-control" name="emergency_contact" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Initial Password</label>
                                <input type="text" class="form-control" name="password" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Register Patient
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden container for the medical card -->
    <div id="cardContainer">
        <div class="medical-card">
            <h3>MEDICAL RECORD CARD</h3>
            <div class="id-number"><?php echo $medical_record_id; ?></div>
            <div class="patient-name"><?php echo isset($_POST["full_name"]) ? $_POST["full_name"] : ""; ?></div>
            <div class="hospital"><?php echo $doctor_info["hospital"]; ?></div>
            <div class="expiry">Valid until: <?php echo date("Y-m-d", strtotime("+1 year")); ?></div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="loading" id="loading">
        <div class="text-center">
            <div class="spinner-border text-light mb-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Generating card...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script>
        function downloadCard() {
            const loading = document.getElementById('loading');
            const cardContainer = document.getElementById('cardContainer');
            const card = cardContainer.querySelector('.medical-card');
            
            // Show loading indicator
            loading.style.display = 'block';
            
            // Ensure the card is visible for capture
            cardContainer.style.position = 'fixed';
            cardContainer.style.top = '0';
            cardContainer.style.left = '0';
            cardContainer.style.background = 'white';
            cardContainer.style.padding = '20px';
            cardContainer.style.zIndex = '9999';
            
            html2canvas(card, {
                scale: 2,
                backgroundColor: null,
                logging: true,
                useCORS: true
            }).then(canvas => {
                // Hide loading indicator
                loading.style.display = 'none';
                
                // Reset card container position
                cardContainer.style.position = 'fixed';
                cardContainer.style.top = '-9999px';
                cardContainer.style.left = '-9999px';
                
                // Create download link
                const link = document.createElement('a');
                link.download = 'medical_card_<?php echo $medical_record_id; ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            }).catch(error => {
                // Hide loading indicator
                loading.style.display = 'none';
                
                // Reset card container position
                cardContainer.style.position = 'fixed';
                cardContainer.style.top = '-9999px';
                cardContainer.style.left = '-9999px';
                
                alert('Error generating card. Please try again.');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html> 