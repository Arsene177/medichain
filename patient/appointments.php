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

// Handle appointment cancellation
if(isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    // Check if appointment belongs to this patient
    $check_sql = "SELECT * FROM appointments WHERE id = ? AND patient_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $appointment_id, $_SESSION["patient_id"]);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if(mysqli_num_rows($check_result) == 1) {
        // Update appointment status to cancelled
        $update_sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $appointment_id);
        
        if(mysqli_stmt_execute($update_stmt)) {
            $success_message = "Appointment cancelled successfully.";
        } else {
            $error_message = "Error cancelling appointment. Please try again.";
        }
    } else {
        $error_message = "Invalid appointment.";
    }
}

// Get all appointments for this patient
$appointments_sql = "SELECT a.*, d.full_name as doctor_name, d.specialization 
                    FROM appointments a 
                    JOIN doctors d ON a.doctor_id = d.id 
                    WHERE a.patient_id = ? 
                    ORDER BY a.appointment_date DESC";
$appointments_stmt = mysqli_prepare($conn, $appointments_sql);
mysqli_stmt_bind_param($appointments_stmt, "i", $_SESSION["patient_id"]);
mysqli_stmt_execute($appointments_stmt);
$appointments_result = mysqli_stmt_get_result($appointments_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .appointment-card {
            transition: transform 0.2s;
        }
        .appointment-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">My Appointments</h1>

        <!-- Navigation -->
        <nav class="nav nav-pills mb-4">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="profile.php">Profile</a>
            <a class="nav-link active" href="appointments.php">Appointments</a>
            <a class="nav-link" href="notifications.php">
                Notifications
                <?php if($unread_count > 0): ?>
                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link" href="medical_records.php">Medical Records</a>
        </nav>

        <?php if(isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Appointments List -->
        <div class="row">
            <?php if(mysqli_num_rows($appointments_result) > 0): ?>
                <?php while($appointment = mysqli_fetch_assoc($appointments_result)): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card appointment-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Dr. <?php echo htmlspecialchars($appointment["doctor_name"]); ?>
                                    <span class="badge bg-<?php 
                                        echo $appointment["status"] == "scheduled" ? "primary" : 
                                            ($appointment["status"] == "completed" ? "success" : 
                                            ($appointment["status"] == "cancelled" ? "danger" : "warning")); 
                                    ?> status-badge float-end">
                                        <?php echo ucfirst($appointment["status"]); ?>
                                    </span>
                                </h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <?php echo htmlspecialchars($appointment["specialization"]); ?>
                                </h6>
                                <p class="card-text">
                                    <strong>Date:</strong> <?php echo date("F j, Y", strtotime($appointment["appointment_date"])); ?><br>
                                    <strong>Time:</strong> <?php echo date("g:i A", strtotime($appointment["appointment_date"])); ?><br>
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($appointment["reason"]); ?>
                                </p>
                                <?php if($appointment["status"] == "scheduled"): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment["id"]; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn btn-danger btn-sm">
                                            Cancel Appointment
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        You don't have any appointments yet.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 