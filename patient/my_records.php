<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a patient
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient"){
    header("location: ../index.php");
    exit;
}

$patient_id = $_SESSION["id"];
$records = array();
$access_logs = array();

// Get patient information
$patient_sql = "SELECT * FROM patients WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $patient_sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_assoc($result);
}

// Get medical records
$records_sql = "SELECT mr.*, d.full_name as doctor_name 
                FROM medical_records mr 
                JOIN doctors d ON mr.doctor_id = d.id 
                WHERE mr.patient_id = ? 
                ORDER BY mr.entry_date DESC";

if($stmt = mysqli_prepare($conn, $records_sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $records[] = $row;
    }
}

// Get access logs
$access_logs_sql = "SELECT al.*, u.username, d.full_name as doctor_name, d.hospital 
                    FROM access_logs al 
                    JOIN users u ON al.accessed_by = u.id 
                    JOIN doctors d ON u.id = d.user_id 
                    JOIN medical_records mr ON al.record_id = mr.id 
                    WHERE mr.patient_id = ? 
                    ORDER BY al.access_time DESC";

if($stmt = mysqli_prepare($conn, $access_logs_sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $access_logs_result = mysqli_stmt_get_result($stmt);
    $access_logs = array();
    while($row = mysqli_fetch_assoc($access_logs_result)){
        $access_logs[] = $row;
    }
}

// Handle access control actions
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST["suspend_access"]) && isset($_POST["doctor_id"])) {
        $doctor_id = $_POST["doctor_id"];
        $reason = isset($_POST["reason"]) ? $_POST["reason"] : "";
        
        // Check if entry exists
        $check_sql = "SELECT id, status FROM access_control WHERE patient_id = ? AND doctor_id = ?";
        if($stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $patient["id"], $doctor_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if($row = mysqli_fetch_assoc($result)) {
                // Update existing entry
                $update_sql = "UPDATE access_control SET status = 'suspended', reason = ? WHERE id = ?";
                if($stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($stmt, "si", $reason, $row["id"]);
                    mysqli_stmt_execute($stmt);
                }
            } else {
                // Insert new entry
                $insert_sql = "INSERT INTO access_control (patient_id, doctor_id, status, reason) VALUES (?, ?, 'suspended', ?)";
                if($stmt = mysqli_prepare($conn, $insert_sql)) {
                    mysqli_stmt_bind_param($stmt, "iis", $patient["id"], $doctor_id, $reason);
                    mysqli_stmt_execute($stmt);
                }
            }
        }
    } elseif(isset($_POST["allow_access"]) && isset($_POST["doctor_id"])) {
        $doctor_id = $_POST["doctor_id"];
        
        // Update or insert access control entry
        $upsert_sql = "INSERT INTO access_control (patient_id, doctor_id, status) 
                       VALUES (?, ?, 'allowed') 
                       ON DUPLICATE KEY UPDATE status = 'allowed', reason = NULL";
        if($stmt = mysqli_prepare($conn, $upsert_sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $patient["id"], $doctor_id);
            mysqli_stmt_execute($stmt);
        }
    }
}

// Get list of doctors who have accessed records
$doctors_sql = "SELECT DISTINCT 
                d.user_id as doctor_id,
                d.full_name as doctor_name,
                d.hospital,
                ac.status as access_status,
                ac.reason as suspension_reason,
                MAX(al.access_time) as last_access
                FROM access_logs al
                JOIN doctors d ON al.accessed_by = d.user_id
                JOIN medical_records mr ON al.record_id = mr.id
                LEFT JOIN access_control ac ON (ac.patient_id = mr.patient_id AND ac.doctor_id = d.user_id)
                WHERE mr.patient_id = ?
                GROUP BY d.user_id
                ORDER BY last_access DESC";

$doctors = array();
if($stmt = mysqli_prepare($conn, $doctors_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $patient["id"]);
    mysqli_stmt_execute($stmt);
    $doctors_result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($doctors_result)) {
        $doctors[] = $row;
    }
}

// Handle record download
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["download_records"])){
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="medical_records.pdf"');
    
    // Generate PDF content
    $pdf_content = "Medical Records for " . $patient["full_name"] . "\n";
    $pdf_content .= "Medical Record ID: " . $patient["medical_record_id"] . "\n\n";
    
    foreach($records as $record){
        $pdf_content .= "Date: " . date("F j, Y g:i A", strtotime($record["entry_date"])) . "\n";
        $pdf_content .= "Type: " . ucfirst($record["entry_type"]) . "\n";
        $pdf_content .= "Doctor: " . $record["doctor_name"] . "\n";
        $pdf_content .= "Content:\n" . $record["content"] . "\n";
        $pdf_content .= "Signature: " . $record["doctor_signature"] . "\n\n";
    }
    
    // Output PDF content
    echo $pdf_content;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical Records - MediChain Cameroon</title>
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
        .access-log {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>My Medical Records</h2>
                    <div>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="download_records" class="btn btn-primary me-2">
                                <i class="bi bi-download"></i> Download Records
                            </button>
                        </form>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Patient Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">My Information</h5>
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

                <!-- Display access logs -->
                <?php if(!empty($access_logs)): ?>
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Recent Access Logs</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Hospital</th>
                                            <th>Access Type</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($access_logs as $log): ?>
                                            <tr>
                                                <td>Dr. <?php echo htmlspecialchars($log["doctor_name"]); ?></td>
                                                <td><?php echo htmlspecialchars($log["hospital"]); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $log["access_type"] == "view" ? "info" : "warning"; ?>">
                                                        <?php echo ucfirst($log["access_type"]); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('F j, Y g:i A', strtotime($log["access_time"])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Doctor Access Control -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Doctor Access Control</h5>
                        <?php if(empty($doctors)): ?>
                            <p class="text-muted">No doctors have accessed your records yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Hospital</th>
                                            <th>Last Access</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($doctors as $doctor): ?>
                                            <tr>
                                                <td>Dr. <?php echo htmlspecialchars($doctor["doctor_name"]); ?></td>
                                                <td><?php echo htmlspecialchars($doctor["hospital"]); ?></td>
                                                <td><?php echo date('F j, Y g:i A', strtotime($doctor["last_access"])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $doctor["access_status"] == "suspended" ? "danger" : "success"; ?>">
                                                        <?php echo ucfirst($doctor["access_status"] ?? "allowed"); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($doctor["access_status"] != "suspended"): ?>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#suspendModal<?php echo $doctor["doctor_id"]; ?>">
                                                            Suspend Access
                                                        </button>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor["doctor_id"]; ?>">
                                                            <button type="submit" name="allow_access" class="btn btn-success btn-sm">
                                                                Allow Access
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- Suspend Access Modal -->
                                            <div class="modal fade" id="suspendModal<?php echo $doctor["doctor_id"]; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Suspend Access for Dr. <?php echo htmlspecialchars($doctor["doctor_name"]); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor["doctor_id"]; ?>">
                                                                <div class="mb-3">
                                                                    <label for="reason" class="form-label">Reason for Suspension (Optional)</label>
                                                                    <textarea class="form-control" name="reason" rows="3"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="suspend_access" class="btn btn-danger">Suspend Access</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical Records -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Medical Records History</h5>
                        <?php if(empty($records)): ?>
                            <p class="text-muted">No medical records found.</p>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 