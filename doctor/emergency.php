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
$emergency_records = array();

// Get doctor information
$doctor_sql = "SELECT full_name, hospital_affiliation, phone_number FROM doctors WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $doctor_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $doctor_result = mysqli_stmt_get_result($stmt);
    $doctor_info = mysqli_fetch_assoc($doctor_result);
}

// Handle new emergency record
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["patient_id"])){
    $patient_id = $_POST["patient_id"];
    $content = $_POST["content"];
    
    // Create doctor signature
    $signature = $doctor_info["full_name"] . " | " . 
                 $doctor_info["hospital_affiliation"] . " | " . 
                 $doctor_info["phone_number"];
    
    $record_sql = "INSERT INTO medical_records (patient_id, doctor_id, entry_type, content, doctor_signature) 
                   VALUES (?, ?, 'emergency', ?, ?)";
    
    if($stmt = mysqli_prepare($conn, $record_sql)){
        mysqli_stmt_bind_param($stmt, "iiss", 
            $patient_id,
            $doctor_id,
            $content,
            $signature
        );
        
        if(mysqli_stmt_execute($stmt)){
            $success = "Emergency record created successfully.";
            
            // Log access
            $log_sql = "INSERT INTO access_logs (medical_record_id, accessed_by, access_type) 
                       VALUES ((SELECT id FROM medical_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1), ?, 'edit')";
            
            if($stmt = mysqli_prepare($conn, $log_sql)){
                mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
                mysqli_stmt_execute($stmt);
            }
            
            // Refresh records
            header("location: emergency.php");
            exit;
        } else {
            $error = "Error creating emergency record.";
        }
    }
}

// Get emergency records
$records_sql = "SELECT mr.*, p.full_name as patient_name, p.medical_record_id 
                FROM medical_records mr 
                JOIN patients p ON mr.patient_id = p.id 
                WHERE mr.doctor_id = ? AND mr.entry_type = 'emergency' 
                ORDER BY mr.entry_date DESC";

if($stmt = mysqli_prepare($conn, $records_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $emergency_records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Records - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .emergency-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .emergency-card:hover {
            transform: translateY(-5px);
        }
        .signature {
            font-style: italic;
            color: #666;
            font-size: 0.9rem;
        }
        .emergency-badge {
            background-color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Emergency Records</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- New Emergency Record Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Create Emergency Record</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient Medical Record ID</label>
                                <input type="text" class="form-control" name="patient_id" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Emergency Details</label>
                                <textarea class="form-control" name="content" rows="5" required 
                                    placeholder="Enter emergency details including:&#10;- Patient's condition&#10;- Vital signs&#10;- Immediate actions taken&#10;- Required follow-up"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-exclamation-triangle"></i> Create Emergency Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Emergency Records List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Emergency Records History</h5>
                        <?php if(empty($emergency_records)): ?>
                            <p class="text-muted">No emergency records found.</p>
                        <?php else: ?>
                            <?php foreach($emergency_records as $record): ?>
                                <div class="card emergency-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($record["patient_name"]); ?>
                                                </h5>
                                                <p class="card-text text-muted mb-0">
                                                    Medical Record ID: <?php echo htmlspecialchars($record["medical_record_id"]); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <span class="badge emergency-badge">Emergency</span>
                                            </div>
                                        </div>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($record["content"])); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="signature mb-0">Signed by: <?php echo htmlspecialchars($record["doctor_signature"]); ?></p>
                                            <small class="text-muted">
                                                <?php echo date("F j, Y g:i A", strtotime($record["entry_date"])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 