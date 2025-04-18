<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$doctor_id = $_SESSION["id"];
$patient_id = isset($_GET["id"]) ? $_GET["id"] : 0;
$patient = null;
$medical_records = array();
$appointments = array();
$error = "";

// Get patient information
if(isset($_GET["id"])) {
    $patient_id = $_GET["id"];
} elseif(isset($_GET["medical_record_id"])) {
    // First get the patient ID from the medical record ID
    $patient_sql = "SELECT id FROM patients WHERE medical_record_id = ?";
    if($stmt = mysqli_prepare($conn, $patient_sql)){
        mysqli_stmt_bind_param($stmt, "s", $_GET["medical_record_id"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if($row = mysqli_fetch_assoc($result)){
            $patient_id = $row["id"];
        } else {
            $error = "Patient not found with this Medical Record ID.";
        }
    }
} else {
    $error = "Please provide a patient ID or Medical Record ID.";
}

if($patient_id > 0){
    // Check if access is suspended
    $access_sql = "SELECT status, reason 
                   FROM access_control 
                   WHERE patient_id = ? AND doctor_id = ? AND status = 'suspended'";
    
    if($stmt = mysqli_prepare($conn, $access_sql)){
        mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $access_result = mysqli_stmt_get_result($stmt);
        
        if($access_row = mysqli_fetch_assoc($access_result)){
            $error = "Access to this patient's records has been suspended.";
            if($access_row["reason"]){
                $error .= " Reason: " . $access_row["reason"];
            }
        } else {
            $patient_sql = "SELECT p.*, u.username 
                           FROM patients p 
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.id = ?";
            
            if($stmt = mysqli_prepare($conn, $patient_sql)){
                mysqli_stmt_bind_param($stmt, "i", $patient_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $patient = mysqli_fetch_assoc($result);
                
                if($patient){
                    // Log this access
                    $log_sql = "INSERT INTO access_logs (record_id, accessed_by, access_type, ip_address) 
                               SELECT mr.id, ?, 'view', ? 
                               FROM medical_records mr 
                               WHERE mr.patient_id = ? 
                               ORDER BY mr.created_at DESC 
                               LIMIT 1";
                    
                    if($stmt = mysqli_prepare($conn, $log_sql)){
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        mysqli_stmt_bind_param($stmt, "isi", $doctor_id, $ip_address, $patient_id);
                        mysqli_stmt_execute($stmt);
                    }
                    
                    // Get medical records
                    $records_sql = "SELECT mr.*, d.full_name as doctor_name, d.hospital 
                                  FROM medical_records mr 
                                  JOIN doctors d ON mr.doctor_id = d.user_id 
                                  WHERE mr.patient_id = ? 
                                  ORDER BY mr.created_at DESC";
                    
                    if($stmt = mysqli_prepare($conn, $records_sql)){
                        mysqli_stmt_bind_param($stmt, "i", $patient_id);
                        mysqli_stmt_execute($stmt);
                        $records_result = mysqli_stmt_get_result($stmt);
                        while($row = mysqli_fetch_assoc($records_result)){
                            $medical_records[] = $row;
                        }
                    }
                    
                    // Get appointments
                    $appointments_sql = "SELECT a.*, d.full_name as doctor_name, d.hospital 
                                       FROM appointments a 
                                       JOIN doctors d ON a.doctor_id = d.user_id 
                                       WHERE a.patient_id = ? 
                                       ORDER BY a.appointment_date DESC, a.appointment_time DESC";
                    
                    if($stmt = mysqli_prepare($conn, $appointments_sql)){
                        mysqli_stmt_bind_param($stmt, "i", $patient_id);
                        mysqli_stmt_execute($stmt);
                        $appointments_result = mysqli_stmt_get_result($stmt);
                        while($row = mysqli_fetch_assoc($appointments_result)){
                            $appointments[] = $row;
                        }
                    }
                }
            }
        }
    }
} else {
    $error = "Invalid patient ID.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .info-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .record-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 1rem;
        }
        .emergency-record {
            border-left-color: #dc3545;
        }
        .appointment-card {
            border-left: 4px solid #198754;
            margin-bottom: 1rem;
        }
        .status-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Patient Details</h2>
                    <div>
                        <a href="search_patient.php" class="btn btn-secondary me-2">
                            <i class="bi bi-search"></i> Search Patients
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php else: ?>
                    <!-- Patient Information -->
                    <div class="card info-card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="card-title">Patient Information</h5>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo isset($patient["full_name"]) ? htmlspecialchars($patient["full_name"]) : "Not available"; ?></p>
                                    <p class="mb-1"><strong>Medical Record ID:</strong> <?php echo isset($patient["medical_record_id"]) ? htmlspecialchars($patient["medical_record_id"]) : "Not available"; ?></p>
                                    <p class="mb-1"><strong>Date of Birth:</strong> <?php echo isset($patient["date_of_birth"]) ? date('F j, Y', strtotime($patient["date_of_birth"])) : "Not available"; ?></p>
                                    <p class="mb-1"><strong>Gender:</strong> <?php echo isset($patient["gender"]) ? htmlspecialchars($patient["gender"]) : "Not available"; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="card-title">Contact Information</h5>
                                    <p class="mb-1"><strong>Phone:</strong> <?php echo isset($patient["phone_number"]) ? htmlspecialchars($patient["phone_number"]) : "Not available"; ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?php echo isset($patient["email"]) ? htmlspecialchars($patient["email"]) : "Not available"; ?></p>
                                    <p class="mb-1"><strong>Address:</strong> <?php echo isset($patient["address"]) ? htmlspecialchars($patient["address"]) : "Not available"; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Records -->
                    <div class="card info-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Medical Records</h5>
                                <a href="add_record.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle"></i> Add Record
                                </a>
                            </div>
                            <?php if(empty($medical_records)): ?>
                                <p class="text-muted">No medical records found.</p>
                            <?php else: ?>
                                <?php foreach($medical_records as $record): ?>
                                    <div class="card record-card <?php echo $record["entry_type"] == "emergency" ? "emergency-record" : ""; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title mb-1">
                                                        <?php echo ucfirst($record["entry_type"]); ?> Record
                                                    </h6>
                                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($record["content"])); ?></p>
                                                    <p class="card-text">
                                                        <small class="text-muted">
                                                            By Dr. <?php echo htmlspecialchars($record["doctor_name"]); ?> | 
                                                            <?php echo date("F j, Y g:i A", strtotime($record["entry_date"])); ?>
                                                        </small>
                                                    </p>
                                                </div>
                                                <?php if($record["entry_type"] == "emergency"): ?>
                                                    <span class="badge bg-danger status-badge">Emergency</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Appointments -->
                    <div class="card info-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Appointments</h5>
                                <a href="schedule_appointment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-calendar-plus"></i> Schedule Appointment
                                </a>
                            </div>
                            <?php if(empty($appointments)): ?>
                                <p class="text-muted">No appointments found.</p>
                            <?php else: ?>
                                <?php foreach($appointments as $appointment): ?>
                                    <div class="card appointment-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title mb-1">
                                                        Appointment with Dr. <?php echo htmlspecialchars($appointment["doctor_name"]); ?>
                                                    </h6>
                                                    <p class="card-text mb-1">
                                                        <strong>Date:</strong> <?php echo date("F j, Y", strtotime($appointment["appointment_date"])); ?>
                                                    </p>
                                                    <p class="card-text mb-1">
                                                        <strong>Time:</strong> <?php echo date("g:i A", strtotime($appointment["appointment_time"])); ?>
                                                    </p>
                                                    <?php if($appointment["notes"]): ?>
                                                        <p class="card-text">
                                                            <strong>Notes:</strong> <?php echo htmlspecialchars($appointment["notes"]); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $appointment["status"] == "scheduled" ? "primary" : 
                                                        ($appointment["status"] == "completed" ? "success" : "secondary"); 
                                                ?> status-badge">
                                                    <?php echo ucfirst($appointment["status"]); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 