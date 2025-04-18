<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a patient
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient"){
    header("location: ../index.php");
    exit;
}

// Get unread notification count
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$unread_stmt = mysqli_prepare($conn, $unread_sql);
mysqli_stmt_bind_param($unread_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($unread_stmt);
$unread_result = mysqli_stmt_get_result($unread_stmt);
$unread_row = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_row['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h1>

        <!-- Navigation -->
        <nav class="nav nav-pills mb-4">
            <a class="nav-link active" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="profile.php">Profile</a>
            <a class="nav-link" href="appointments.php">Appointments</a>
            <a class="nav-link" href="notifications.php">
                Notifications
                <?php if($unread_count > 0): ?>
                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link" href="medical_records.php">Medical Records</a>
        </nav>

        <!-- Dashboard Content -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Upcoming Appointments</h5>
                        <?php
                        $appointments_sql = "SELECT a.*, d.full_name as doctor_name, d.specialization 
                                           FROM appointments a 
                                           JOIN doctors d ON a.doctor_id = d.id 
                                           WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() 
                                           ORDER BY a.appointment_date ASC LIMIT 5";
                        $appointments_stmt = mysqli_prepare($conn, $appointments_sql);
                        mysqli_stmt_bind_param($appointments_stmt, "i", $_SESSION["patient_id"]);
                        mysqli_stmt_execute($appointments_stmt);
                        $appointments_result = mysqli_stmt_get_result($appointments_stmt);
                        
                        if(mysqli_num_rows($appointments_result) > 0):
                            while($appointment = mysqli_fetch_assoc($appointments_result)):
                        ?>
                            <div class="mb-3">
                                <strong><?php echo htmlspecialchars($appointment["doctor_name"]); ?></strong><br>
                                <?php echo htmlspecialchars($appointment["specialization"]); ?><br>
                                <small class="text-muted">
                                    <?php echo date("F j, Y g:i A", strtotime($appointment["appointment_date"])); ?>
                                </small>
                            </div>
                        <?php 
                            endwhile;
                        else:
                            echo "<p class='text-muted'>No upcoming appointments</p>";
                        endif;
                        ?>
                        <a href="appointments.php" class="btn btn-primary btn-sm">View All Appointments</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Medical Records</h5>
                        <?php
                        $records_sql = "SELECT * FROM medical_records 
                                      WHERE patient_id = ? 
                                      ORDER BY record_date DESC LIMIT 5";
                        $records_stmt = mysqli_prepare($conn, $records_sql);
                        mysqli_stmt_bind_param($records_stmt, "i", $_SESSION["patient_id"]);
                        mysqli_stmt_execute($records_stmt);
                        $records_result = mysqli_stmt_get_result($records_stmt);
                        
                        if(mysqli_num_rows($records_result) > 0):
                            while($record = mysqli_fetch_assoc($records_result)):
                        ?>
                            <div class="mb-3">
                                <strong><?php echo htmlspecialchars($record["diagnosis"]); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo date("F j, Y", strtotime($record["record_date"])); ?>
                                </small>
                            </div>
                        <?php 
                            endwhile;
                        else:
                            echo "<p class='text-muted'>No medical records found</p>";
                        endif;
                        ?>
                        <a href="medical_records.php" class="btn btn-primary btn-sm">View All Records</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 