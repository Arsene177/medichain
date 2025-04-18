<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

// Handle status updates
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["doctor_id"])) {
    $doctor_id = $_POST["doctor_id"];
    $action = $_POST["action"];
    $rejection_reason = isset($_POST["rejection_reason"]) ? trim($_POST["rejection_reason"]) : "";
    $suspension_reason = isset($_POST["suspension_reason"]) ? trim($_POST["suspension_reason"]) : "";
    
    if($action === "approve") {
        $sql = "UPDATE doctors SET status = 'approved' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    } elseif($action === "reject") {
        $sql = "UPDATE doctors SET status = 'rejected', rejection_reason = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $rejection_reason, $doctor_id);
    } elseif($action === "suspend") {
        $sql = "UPDATE doctors SET status = 'suspended', suspension_reason = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $suspension_reason, $doctor_id);
    } elseif($action === "reactivate") {
        $sql = "UPDATE doctors SET status = 'approved', suspension_reason = NULL WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        $_SESSION["success"] = "Doctor status updated successfully.";
    } else {
        $_SESSION["error"] = "Error updating doctor status.";
    }
    
    header("location: manage_doctors.php");
    exit;
}

// Get all doctors with their status
$sql = "SELECT d.*, u.username, u.email 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        ORDER BY d.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.9rem;
        }
        .doctor-card {
            transition: transform 0.2s;
        }
        .doctor-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Doctors</h2>
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
            <?php while($doctor = mysqli_fetch_assoc($result)): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card doctor-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">Dr. <?php echo htmlspecialchars($doctor["full_name"]); ?></h5>
                                <span class="badge bg-<?php 
                                    echo $doctor["status"] === "approved" ? "success" : 
                                        ($doctor["status"] === "rejected" ? "danger" : "warning"); 
                                ?> status-badge">
                                    <?php echo ucfirst($doctor["status"] ?? 'pending'); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor["specialization"]); ?></p>
                                <p class="mb-1"><strong>Hospital:</strong> <?php echo htmlspecialchars($doctor["hospital"]); ?></p>
                                <p class="mb-1"><strong>License:</strong> <?php echo htmlspecialchars($doctor["license_number"]); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($doctor["email"]); ?></p>
                            </div>

                            <?php if($doctor["status"] === "pending"): ?>
                                <div class="d-grid gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doctor["id"]; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-danger w-100" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal<?php echo $doctor["id"]; ?>">
                                        <i class="bi bi-x-circle"></i> Reject
                                    </button>
                                </div>

                                <!-- Rejection Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $doctor["id"]; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Doctor Registration</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor["id"]; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <div class="mb-3">
                                                        <label class="form-label">Rejection Reason</label>
                                                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif($doctor["status"] === "approved"): ?>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-warning w-100" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#suspendModal<?php echo $doctor["id"]; ?>">
                                        <i class="bi bi-pause-circle"></i> Suspend Account
                                    </button>
                                </div>

                                <!-- Suspend Modal -->
                                <div class="modal fade" id="suspendModal<?php echo $doctor["id"]; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Suspend Doctor Account</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor["id"]; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <div class="mb-3">
                                                        <label class="form-label">Reason for Suspension</label>
                                                        <textarea name="suspension_reason" class="form-control" rows="3" required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-warning">Suspend Account</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif($doctor["status"] === "suspended"): ?>
                                <div class="d-grid gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doctor["id"]; ?>">
                                        <input type="hidden" name="action" value="reactivate">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-play-circle"></i> Reactivate Account
                                        </button>
                                    </form>
                                </div>
                                <?php if(!empty($doctor["suspension_reason"])): ?>
                                    <div class="alert alert-warning mt-3">
                                        <strong>Suspension Reason:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($doctor["suspension_reason"])); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if($doctor["status"] === "rejected" && !empty($doctor["rejection_reason"])): ?>
                                <div class="alert alert-danger mt-3">
                                    <strong>Rejection Reason:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($doctor["rejection_reason"])); ?>
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