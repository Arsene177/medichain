<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

// Handle patient status updates
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["patient_id"])) {
    $patient_id = $_POST["patient_id"];
    $action = $_POST["action"];
    
    if($action === "deactivate") {
        $sql = "UPDATE patients SET status = 'inactive' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
    } elseif($action === "activate") {
        $sql = "UPDATE patients SET status = 'active' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        $_SESSION["success"] = "Patient status updated successfully.";
    } else {
        $_SESSION["error"] = "Error updating patient status.";
    }
    
    header("location: manage_patients.php");
    exit;
}

// Get all patients with their details
$sql = "SELECT p.*, 
        u.username, u.email,
        COALESCE(COUNT(DISTINCT a.id), 0) as total_appointments,
        COALESCE(COUNT(DISTINCT mr.id), 0) as total_medical_records,
        GROUP_CONCAT(DISTINCT mc.name) as conditions
        FROM patients p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN appointments a ON p.id = a.patient_id
        LEFT JOIN medical_records mr ON p.id = mr.patient_id
        LEFT JOIN medical_conditions mc ON mr.condition_id = mc.id
        GROUP BY p.id, p.full_name, p.phone_number, p.gender, p.date_of_birth, p.address, p.status, p.created_at, u.username, u.email
        ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $sql);

if(!$result) {
    $_SESSION["error"] = "Error fetching patient data: " . mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .patient-card {
            transition: transform 0.2s;
        }
        .patient-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.9rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Patients</h2>
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

        <?php if(mysqli_num_rows($result) > 0): ?>
            <div class="row g-4">
                <?php while($patient = mysqli_fetch_assoc($result)): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card patient-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($patient["full_name"]); ?></h5>
                                    <span class="badge bg-<?php echo $patient["status"] === "active" ? "success" : "danger"; ?> status-badge">
                                        <?php echo ucfirst($patient["status"]); ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($patient["email"]); ?></p>
                                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($patient["phone_number"]); ?></p>
                                    <p class="mb-1"><strong>Gender:</strong> <?php echo ucfirst($patient["gender"]); ?></p>
                                    <p class="mb-1"><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($patient["date_of_birth"])); ?></p>
                                    <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($patient["address"]); ?></p>
                                </div>

                                <div class="mb-3">
                                    <p class="mb-1"><strong>Total Appointments:</strong> <?php echo $patient["total_appointments"]; ?></p>
                                    <p class="mb-1"><strong>Medical Records:</strong> <?php echo $patient["total_medical_records"]; ?></p>
                                    <?php if($patient["conditions"]): ?>
                                        <p class="mb-1"><strong>Conditions:</strong> <?php echo htmlspecialchars($patient["conditions"]); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="d-grid gap-2">
                                    <?php if($patient["status"] === "active"): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="patient_id" value="<?php echo $patient["id"]; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-danger w-100">
                                                <i class="bi bi-person-x"></i> Deactivate Account
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="patient_id" value="<?php echo $patient["id"]; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-person-check"></i> Activate Account
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h3>No Patients Found</h3>
                <p class="text-muted">There are currently no patients registered in the system.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 