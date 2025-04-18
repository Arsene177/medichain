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

// Get all patients for the dropdown
$patients_sql = "SELECT p.id, p.full_name, p.phone_number, p.medical_record_id 
                 FROM patients p 
                 ORDER BY p.full_name";
$patients_result = mysqli_query($conn, $patients_sql);
$patients = [];
while($row = mysqli_fetch_assoc($patients_result)){
    $patients[] = $row;
}

// Get selected patient ID from URL if present
$selected_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

// Get all appointments for this doctor
$appointments_sql = "SELECT a.*, p.full_name as patient_name, p.phone_number as patient_phone, p.medical_record_id 
                    FROM appointments a 
                    JOIN patients p ON a.patient_id = p.id 
                    WHERE a.doctor_id = ? 
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments_stmt = mysqli_prepare($conn, $appointments_sql);
mysqli_stmt_bind_param($appointments_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($appointments_stmt);
$appointments_result = mysqli_stmt_get_result($appointments_stmt);
$appointments = [];
while($row = mysqli_fetch_assoc($appointments_result)){
        $appointments[] = $row;
}

// Handle form submission for new appointment
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])){
    if($_POST["action"] == "create"){
        $patient_id = trim($_POST["patient_id"] ?? '');
        $appointment_date = trim($_POST["appointment_date"] ?? '');
        $appointment_time = trim($_POST["appointment_time"] ?? '');
        $reason = trim($_POST["reason"] ?? '');
        
        if(empty($patient_id) || empty($appointment_date) || empty($appointment_time) || empty($reason)){
            $error_message = "All fields are required.";
        } else {
            $status = "scheduled";
            $create_sql = "INSERT INTO appointments (doctor_id, patient_id, appointment_date, appointment_time, reason, status) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            
            if($create_stmt = mysqli_prepare($conn, $create_sql)){
                mysqli_stmt_bind_param($create_stmt, "iissss", 
                    $_SESSION["id"],
                    $patient_id,
                    $appointment_date,
                    $appointment_time,
                    $reason,
                    $status
                );
                
                if(mysqli_stmt_execute($create_stmt)){
                    $appointment_id = mysqli_insert_id($conn);
                    $success_message = "Appointment scheduled successfully!";
                    
                    // Get patient information for notification
                    $patient_sql = "SELECT p.full_name, p.phone_number, p.email, u.id as user_id 
                                   FROM patients p 
                                   JOIN users u ON p.user_id = u.id 
                                   WHERE p.id = ?";
                    
                    if($patient_stmt = mysqli_prepare($conn, $patient_sql)){
                        mysqli_stmt_bind_param($patient_stmt, "i", $patient_id);
                        mysqli_stmt_execute($patient_stmt);
                        $patient_result = mysqli_stmt_get_result($patient_stmt);
                        $patient_info = mysqli_fetch_assoc($patient_result);
                        
                        // Format date and time for notification
                        $formatted_date = date('F j, Y', strtotime($appointment_date));
                        $formatted_time = date('g:i A', strtotime($appointment_time));
                        
                        // Create notification message
                        $notification_message = "Dear " . $patient_info['full_name'] . ", 
                                               an appointment has been scheduled for you on " . 
                                               $formatted_date . " at " . $formatted_time . 
                                               " with Dr. " . $doctor['full_name'] . 
                                               ". Reason: " . $reason;
                        
                        // Insert notification into database
                        $notification_sql = "INSERT INTO notifications (user_id, message, type, reference_id, created_at) 
                                           VALUES (?, ?, 'appointment', ?, NOW())";
                        
                        if($notification_stmt = mysqli_prepare($conn, $notification_sql)){
                            mysqli_stmt_bind_param($notification_stmt, "isi", 
                                $patient_info['user_id'], 
                                $notification_message, 
                                $appointment_id
                            );
                            mysqli_stmt_execute($notification_stmt);
                            
                            // Send email notification if email is available
                            if(!empty($patient_info['email'])){
                                $to = $patient_info['email'];
                                $subject = "Appointment Scheduled - MediChain Cameroon";
                                $headers = "From: noreply@medichain.cm\r\n";
                                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                                
                                $email_body = "<html><body>";
                                $email_body .= "<h2>Appointment Scheduled</h2>";
                                $email_body .= "<p>" . $notification_message . "</p>";
                                $email_body .= "<p>Please log in to your account to view more details.</p>";
                                $email_body .= "<p>Thank you for choosing MediChain Cameroon.</p>";
                                $email_body .= "</body></html>";
                                
                                mail($to, $subject, $email_body, $headers);
                            }
                        }
                    }
                } else {
                    $error_message = "Error scheduling appointment: " . mysqli_error($conn);
                }
                mysqli_stmt_close($create_stmt);
            }
        }
    } else if($_POST["action"] == "update_status" && isset($_POST["appointment_id"]) && isset($_POST["status"])){
        $appointment_id = $_POST["appointment_id"];
        $status = $_POST["status"];
        
        $update_sql = "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?";
        
        if($update_stmt = mysqli_prepare($conn, $update_sql)){
            mysqli_stmt_bind_param($update_stmt, "sii", $status, $appointment_id, $_SESSION["id"]);
            
            if(mysqli_stmt_execute($update_stmt)){
                $success_message = "Appointment status updated successfully!";
            } else {
                $error_message = "Error updating appointment status: " . mysqli_error($conn);
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
    <title>Appointments - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
        }
        .appointments-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin: 2rem auto;
            max-width: 1200px;
        }
        .appointment-card {
            border-left: 4px solid #0083B0;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .appointment-card.scheduled {
            border-left-color: #0083B0;
        }
        .appointment-card.completed {
            border-left-color: #28a745;
        }
        .appointment-card.cancelled {
            border-left-color: #dc3545;
        }
        .appointment-card.no-show {
            border-left-color: #ffc107;
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
        <div class="appointments-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Appointments</h1>
                <div>
                    <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav nav-pills mb-4">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link active" href="appointments.php">Appointments</a>
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

            <!-- New Appointment Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Schedule New Appointment</h5>
                </div>
                    <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Patient</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>" <?php echo ($selected_patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($patient['full_name']); ?> 
                                        (MR: <?php echo htmlspecialchars($patient['medical_record_id']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="appointment_date" class="form-control" required>
                                            </div>
                                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" name="appointment_time" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reason</label>
                                <input type="text" name="reason" class="form-control" required>
                                    </div>
                                </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                        </div>
                    </form>
                    </div>
                </div>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Your Appointments</h5>
                </div>
                        <div class="card-body">
                    <?php if(empty($appointments)): ?>
                    <div class="alert alert-info">
                        You don't have any appointments scheduled.
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($appointments as $appointment): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card appointment-card <?php echo $appointment['status']; ?>">
                                    <div class="card-body">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                        <span class="badge bg-<?php 
                                            echo $appointment['status'] == 'scheduled' ? 'primary' : 
                                                ($appointment['status'] == 'completed' ? 'success' : 
                                                ($appointment['status'] == 'cancelled' ? 'danger' : 'warning')); 
                                        ?> float-end">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </h5>
                                                <p class="card-text">
                                        <strong>Date:</strong> <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?><br>
                                        <strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?><br>
                                        <strong>Patient Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?><br>
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                                    </p>
                                    <?php if($appointment['status'] == 'scheduled'): ?>
                                    <div class="btn-group">
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-success">Mark as Completed</button>
                                        </form>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                        </form>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="no-show">
                                            <button type="submit" class="btn btn-sm btn-warning">Mark as No-Show</button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 