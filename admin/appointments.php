<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

// Handle appointment status updates
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["appointment_id"])) {
    $appointment_id = $_POST["appointment_id"];
    $action = $_POST["action"];
    
    if($action === "cancel") {
        $sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
    } elseif($action === "complete") {
        $sql = "UPDATE appointments SET status = 'completed' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        $_SESSION["success"] = "Appointment status updated successfully.";
    } else {
        $_SESSION["error"] = "Error updating appointment status.";
    }
    
    header("location: appointments.php");
    exit;
}

// Get all appointments with doctor and patient details
$sql = "SELECT a.*, 
        d.full_name as doctor_name, d.specialization,
        p.full_name as patient_name, p.email as patient_email,
        u.username as doctor_username
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON d.user_id = u.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.9rem;
        }
        .appointment-card {
            transition: transform 0.2s;
        }
        .appointment-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Appointments</h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if(isset($_SESSION["success"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION["success"];
                unset($_SESSION["success"]);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION["error"])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION["error"];
                unset($_SESSION["error"]);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php while($appointment = mysqli_fetch_assoc($result)): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card appointment-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">Appointment #<?php echo $appointment["id"]; ?></h5>
                                <span class="badge bg-<?php 
                                    echo $appointment["status"] === "completed" ? "success" : 
                                        ($appointment["status"] === "cancelled" ? "danger" : "primary"); 
                                ?> status-badge">
                                    <?php echo ucfirst($appointment["status"]); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($appointment["doctor_name"]); ?></p>
                                <p class="mb-1"><strong>Specialization:</strong> <?php echo htmlspecialchars($appointment["specialization"]); ?></p>
                                <p class="mb-1"><strong>Patient:</strong> <?php echo htmlspecialchars($appointment["patient_name"]); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($appointment["appointment_date"])); ?></p>
                                <p class="mb-1"><strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment["appointment_time"])); ?></p>
                                <p class="mb-1"><strong>Type:</strong> <?php echo ucfirst($appointment["appointment_type"]); ?></p>
                            </div>

                            <?php if($appointment["status"] === "scheduled"): ?>
                                <div class="d-grid gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment["id"]; ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-check-circle"></i> Mark as Completed
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment["id"]; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="btn btn-danger w-100">
                                            <i class="bi bi-x-circle"></i> Cancel Appointment
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php if($appointment["notes"]): ?>
                                <div class="mt-3">
                                    <strong>Notes:</strong>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment["notes"])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 