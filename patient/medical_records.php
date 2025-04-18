<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a patient
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient"){
    header("location: ../index.php");
    exit;
}

// Handle record download
if(isset($_GET['download']) && isset($_GET['record_id'])) {
    $record_id = $_GET['record_id'];
    
    // Verify the record belongs to this patient
    $verify_sql = "SELECT mr.*, d.full_name as doctor_name, d.specialization, p.full_name as patient_name, p.medical_record_id 
                   FROM medical_records mr 
                   JOIN doctors d ON mr.doctor_id = d.user_id 
                   JOIN patients p ON mr.patient_id = p.id 
                   WHERE mr.id = ? AND p.user_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "ii", $record_id, $_SESSION["id"]);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if($record = mysqli_fetch_assoc($verify_result)) {
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="medical_record_'.$record_id.'.pdf"');
        
        // Create PDF content
        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { text-align: center; margin-bottom: 20px; }
                .record-info { margin-bottom: 20px; }
                .content { margin-top: 20px; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Medical Record</h1>
                <p>MediChain Cameroon</p>
            </div>
            <div class="record-info">
                <p><strong>Patient Name:</strong> '.$record['patient_name'].'</p>
                <p><strong>Medical Record ID:</strong> '.$record['medical_record_id'].'</p>
                <p><strong>Doctor:</strong> Dr. '.$record['doctor_name'].' ('.$record['specialization'].')</p>
                <p><strong>Date:</strong> '.date("F j, Y", strtotime($record['entry_date'])).'</p>
                <p><strong>Type:</strong> '.ucfirst($record['entry_type']).'</p>
            </div>
            <div class="content">
                <h3>Record Content:</h3>
                <p>'.nl2br($record['content']).'</p>
            </div>
            <div class="footer">
                <p>This is an official medical record from MediChain Cameroon.</p>
                <p>Signed by: '.$record['doctor_signature'].'</p>
                <p>Generated on: '.date("F j, Y g:i A").'</p>
            </div>
        </body>
        </html>';
        
        // Convert HTML to PDF using a library like TCPDF or Dompdf
        // For this example, we'll just output the HTML
        echo $html;
        exit;
    }
}

// Get unread notification count
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$unread_stmt = mysqli_prepare($conn, $unread_sql);
mysqli_stmt_bind_param($unread_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($unread_stmt);
$unread_result = mysqli_stmt_get_result($unread_stmt);
$unread_row = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_row['count'];

// Get patient ID
$patient_sql = "SELECT id FROM patients WHERE user_id = ?";
$patient_stmt = mysqli_prepare($conn, $patient_sql);
mysqli_stmt_bind_param($patient_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($patient_stmt);
$patient_result = mysqli_stmt_get_result($patient_stmt);
$patient = mysqli_fetch_assoc($patient_result);
$patient_id = $patient["id"];

// Get all medical records for this patient
$records_sql = "SELECT mr.*, d.full_name as doctor_name, d.specialization 
                FROM medical_records mr 
                JOIN doctors d ON mr.doctor_id = d.user_id 
                WHERE mr.patient_id = ? 
                ORDER BY mr.entry_date DESC";
$records_stmt = mysqli_prepare($conn, $records_sql);
mysqli_stmt_bind_param($records_stmt, "i", $patient_id);
mysqli_stmt_execute($records_stmt);
$records_result = mysqli_stmt_get_result($records_stmt);
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
            transition: transform 0.2s;
            position: relative;
            margin-bottom: 20px;
        }
        .record-card:hover {
            transform: translateY(-5px);
        }
        .record-date {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .record-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        .download-btn {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .card-title {
            margin-right: 100px; /* Make space for the download button */
        }
        .doctor-info {
            margin-bottom: 15px;
        }
        .specialization {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Medical Records</h1>

        <!-- Navigation -->
        <nav class="nav nav-pills mb-4">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="profile.php">Profile</a>
            <a class="nav-link" href="appointments.php">Appointments</a>
            <a class="nav-link" href="notifications.php">
                Notifications
                <?php if($unread_count > 0): ?>
                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link active" href="medical_records.php">Medical Records</a>
        </nav>

        <!-- Medical Records List -->
        <div class="row">
            <?php if(mysqli_num_rows($records_result) > 0): ?>
                <?php while($record = mysqli_fetch_assoc($records_result)): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card record-card">
                            <a href="?download=1&record_id=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-primary download-btn">
                                <i class="bi bi-download"></i> Download
                            </a>
                            <div class="card-body">
                                <div class="doctor-info">
                                    <h5 class="card-title">
                                        Dr. <?php echo htmlspecialchars($record["doctor_name"]); ?>
                                    </h5>
                                    <div class="specialization">
                                        <?php echo htmlspecialchars($record["specialization"]); ?>
                                    </div>
                                    <div class="record-date">
                                        <?php echo date("F j, Y", strtotime($record["entry_date"])); ?>
                                    </div>
                                </div>
                                <div class="record-details">
                                    <p><strong>Type:</strong> <?php echo ucfirst(htmlspecialchars($record["entry_type"])); ?></p>
                                    <p><strong>Content:</strong> <?php echo nl2br(htmlspecialchars($record["content"])); ?></p>
                                    <?php if(!empty($record["doctor_signature"])): ?>
                                        <p class="text-muted"><small>Signed by: <?php echo htmlspecialchars($record["doctor_signature"]); ?></small></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        You don't have any medical records yet.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 