<?php
session_start();
require_once "config/database.php";

$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $role = trim($_POST["role"]);

    // Validate input
    if (empty($username) || empty($password) || empty($confirm_password) || empty($full_name) || empty($email) || empty($phone)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must have at least 6 characters.";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "This username is already taken.";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert into users table
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bind_param("sss", $username, $hashed_password, $role);
                $stmt->execute();
                $user_id = $conn->insert_id;

                if ($role === "doctor") {
                    // Insert into doctors table
                    $stmt = $conn->prepare("INSERT INTO doctors (user_id, full_name, email, phone_number, specialization, hospital, license_number, date_of_birth, gender, emergency_contact, id_type, id_number, years_of_experience, qualification, certification, consultation_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $specialization = trim($_POST["specialization"]);
                    $hospital = trim($_POST["hospital"]);
                    $license_number = trim($_POST["license_number"]);
                    $date_of_birth = trim($_POST["date_of_birth"]);
                    $gender = trim($_POST["gender"]);
                    $emergency_contact = trim($_POST["emergency_contact"]);
                    $id_type = trim($_POST["id_type"]);
                    $id_number = trim($_POST["id_number"]);
                    $years_of_experience = trim($_POST["years_of_experience"]);
                    $qualification = trim($_POST["qualification"]);
                    $certification = trim($_POST["certification"]);
                    $consultation_fee = trim($_POST["consultation_fee"]);
                    
                    $stmt->bind_param("isssssssssssssd", 
                        $user_id, $full_name, $email, $phone, $specialization, $hospital, 
                        $license_number, $date_of_birth, $gender, $emergency_contact, 
                        $id_type, $id_number, $years_of_experience, $qualification, 
                        $certification, $consultation_fee);
                    
                    $stmt->execute();
                } else {
                    // Insert into patients table
                    $stmt = $conn->prepare("INSERT INTO patients (user_id, full_name, email, phone_number, gender, date_of_birth, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $gender = trim($_POST["gender"]);
                    $date_of_birth = trim($_POST["date_of_birth"]);
                    $address = trim($_POST["address"]);
                    $stmt->bind_param("issssss", $user_id, $full_name, $email, $phone, $gender, $date_of_birth, $address);
                    $stmt->execute();
                }

                $conn->commit();
                if ($role === "doctor") {
                    $success = "Registration successful! Your account is pending approval. You will be notified via email once your account is approved. You can check your registration status <a href='check_status.php'>here</a>.";
                    // Redirect to login page after 5 seconds for doctors (to give time to read the message)
                    header("refresh:5;url=login.php");
                } else {
                    // For patients, automatically log them in and redirect to patient dashboard
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $user_id;
                    $_SESSION["username"] = $username;
                    $_SESSION["role"] = $role;
                    
                    // Get patient details for session
                    $patient_sql = "SELECT * FROM patients WHERE user_id = ?";
                    $patient_stmt = $conn->prepare($patient_sql);
                    $patient_stmt->bind_param("i", $user_id);
                    $patient_stmt->execute();
                    $patient_result = $patient_stmt->get_result();
                    $patient = $patient_result->fetch_assoc();
                    
                    $_SESSION["patient_id"] = $patient["id"];
                    $_SESSION["full_name"] = $patient["full_name"];
                    
                    // Redirect to patient dashboard
                    header("location: patient/dashboard.php");
                    exit;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
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
    <title>Register - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Register</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select" required onchange="toggleFields(this.value)">
                                    <option value="">Select Role</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="patient">Patient</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>

                            <div id="doctorFields" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Specialization</label>
                                    <input type="text" name="specialization" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Hospital</label>
                                    <input type="text" name="hospital" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">License Number</label>
                                    <input type="text" name="license_number" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select Gender</option>
                                        <option value="M">Male</option>
                                        <option value="F">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Emergency Contact</label>
                                    <input type="tel" name="emergency_contact" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">ID Type</label>
                                    <input type="text" name="id_type" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" name="id_number" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Years of Experience</label>
                                    <input type="number" name="years_of_experience" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Qualification</label>
                                    <input type="text" name="qualification" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Certification</label>
                                    <input type="text" name="certification" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Consultation Fee</label>
                                    <input type="number" name="consultation_fee" class="form-control" step="0.01" required>
                                </div>
                            </div>

                            <div id="patientFields" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="3"></textarea>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFields(role) {
            document.getElementById('doctorFields').style.display = role === 'doctor' ? 'block' : 'none';
            document.getElementById('patientFields').style.display = role === 'patient' ? 'block' : 'none';
        }
    </script>
</body>
</html> 