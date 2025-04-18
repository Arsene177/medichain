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
$patient = null;
$records = array();

// Get doctor information
$doctor_sql = "SELECT full_name, hospital, phone_number FROM doctors WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $doctor_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $doctor_result = mysqli_stmt_get_result($stmt);
    $doctor_info = mysqli_fetch_assoc($doctor_result);
}

// Handle record search
if($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["medical_record_id"])){
    $medical_record_id = $_GET["medical_record_id"];
    
    // Check if doctor has access to this record
    $access_sql = "SELECT p.* FROM patients p 
                   JOIN record_access ra ON p.id = ra.patient_id 
                   WHERE p.medical_record_id = ? AND ra.doctor_id = ? AND ra.status = 'active'";
    
    if($stmt = mysqli_prepare($conn, $access_sql)){
        mysqli_stmt_bind_param($stmt, "si", $medical_record_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $patient = mysqli_fetch_assoc($result);
        
        if($patient){
            // Get patient's medical records
            $sql = "SELECT mr.*, p.full_name as patient_name, p.gender, p.date_of_birth, p.address, p.phone_number, p.email, p.hospital 
                    FROM medical_records mr 
                    JOIN patients p ON mr.patient_id = p.user_id 
                    WHERE mr.doctor_id = ? 
                    ORDER BY mr.created_at DESC";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "i", $doctor_id);
                mysqli_stmt_execute($stmt);
                $records_result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($records_result)){
                    $records[] = $row;
                }
            }
        } else {
            $error = "No access to this medical record or record not found.";
        }
    }
}

// Handle new record entry
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["patient_id"])){
    $patient_id = $_POST["patient_id"];
    $entry_type = $_POST["entry_type"];
    $content = $_POST["content"];
    
    // Create doctor signature
    $signature = $doctor_info["full_name"] . " | " . 
                 $doctor_info["hospital"] . " | " . 
                 $doctor_info["phone_number"];
    
    $record_sql = "INSERT INTO medical_records (patient_id, doctor_id, entry_type, content, doctor_signature) 
                   VALUES (?, ?, ?, ?, ?)";
    
    if($stmt = mysqli_prepare($conn, $record_sql)){
        mysqli_stmt_bind_param($stmt, "iisss", 
            $patient_id,
            $doctor_id,
            $entry_type,
            $content,
            $signature
        );
        
        if(mysqli_stmt_execute($stmt)){
            $success = "Medical record updated successfully.";
            
            // Log access
            $log_sql = "INSERT INTO access_logs (medical_record_id, accessed_by, access_type) 
                       VALUES ((SELECT id FROM medical_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1), ?, 'edit')";
            
            if($stmt = mysqli_prepare($conn, $log_sql)){
                mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
                mysqli_stmt_execute($stmt);
            }
            
            // Refresh records
            header("location: medical_records.php?medical_record_id=" . $_GET["medical_record_id"]);
            exit;
        } else {
            $error = "Error updating medical record.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .record-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .signature {
            font-style: italic;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">Medical Records</h2>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Medical Record ID</label>
                                <input type="text" class="form-control" name="medical_record_id" 
                                       value="<?php echo isset($_GET["medical_record_id"]) ? htmlspecialchars($_GET["medical_record_id"]) : ""; ?>" 
                                       required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if($patient): ?>
                    <!-- Patient Information -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Patient Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient["full_name"]); ?></p>
                                    <p><strong>Medical Record ID:</strong> <?php echo htmlspecialchars($patient["medical_record_id"]); ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo date("F j, Y", strtotime($patient["date_of_birth"])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Gender:</strong> <?php echo $patient["gender"]; ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient["phone_number"]); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($patient["address"]); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Record Entry -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Add New Record</h5>
                            <form method="POST">
                                <input type="hidden" name="patient_id" value="<?php echo $patient["id"]; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Entry Type</label>
                                    <select class="form-select" name="entry_type" required>
                                        <option value="consultation">Consultation</option>
                                        <option value="prescription">Prescription</option>
                                        <option value="lab_result">Lab Result</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Content</label>
                                    <textarea class="form-control" name="content" rows="5" required></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Add Record</button>
                            </form>
                        </div>
                    </div>

                    <!-- Medical Records History -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Medical Records History</h5>
                            <?php foreach($records as $record): ?>
                                <div class="card record-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <?php echo date("F j, Y g:i A", strtotime($record["entry_date"])); ?>
                                            </h6>
                                            <span class="badge bg-primary"><?php echo ucfirst($record["entry_type"]); ?></span>
                                        </div>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($record["content"])); ?></p>
                                        <p class="signature">Signed by: <?php echo htmlspecialchars($record["doctor_signature"]); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 