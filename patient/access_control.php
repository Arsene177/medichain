<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a patient
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient"){
    header("location: ../index.php");
    exit;
}

$patient_id = $_SESSION["id"];
$success = $error = "";
$access_list = array();

// Get patient's access list
$sql = "SELECT ra.*, d.full_name as doctor_name, d.hospital_affiliation, d.phone_number 
        FROM record_access ra 
        JOIN doctors d ON ra.doctor_id = d.id 
        WHERE ra.patient_id = ? 
        ORDER BY ra.granted_at DESC";

if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $access_list[] = $row;
    }
}

// Handle access revocation
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["revoke_access"])){
    $access_id = $_POST["access_id"];
    
    $revoke_sql = "UPDATE record_access SET status = 'revoked' WHERE id = ? AND patient_id = ?";
    if($stmt = mysqli_prepare($conn, $revoke_sql)){
        mysqli_stmt_bind_param($stmt, "ii", $access_id, $patient_id);
        if(mysqli_stmt_execute($stmt)){
            $success = "Access revoked successfully.";
            header("location: access_control.php");
            exit;
        } else {
            $error = "Error revoking access.";
        }
    }
}

// Handle access grant
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["grant_access"])){
    $doctor_id = $_POST["doctor_id"];
    
    // Check if access already exists
    $check_sql = "SELECT id FROM record_access WHERE patient_id = ? AND doctor_id = ? AND status = 'active'";
    if($stmt = mysqli_prepare($conn, $check_sql)){
        mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 0){
            // Grant new access
            $grant_sql = "INSERT INTO record_access (patient_id, doctor_id, status) VALUES (?, ?, 'active')";
            if($stmt = mysqli_prepare($conn, $grant_sql)){
                mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
                if(mysqli_stmt_execute($stmt)){
                    $success = "Access granted successfully.";
                    header("location: access_control.php");
                    exit;
                } else {
                    $error = "Error granting access.";
                }
            }
        } else {
            $error = "Access already granted to this doctor.";
        }
    }
}

// Get list of available doctors
$doctors_sql = "SELECT d.* FROM doctors d 
                WHERE d.id NOT IN (
                    SELECT doctor_id FROM record_access 
                    WHERE patient_id = ? AND status = 'active'
                )";
$available_doctors = array();
if($stmt = mysqli_prepare($conn, $doctors_sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $available_doctors[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Control - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .access-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .status-active {
            color: #28a745;
        }
        .status-revoked {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Access Control</h2>
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

                <!-- Grant Access Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Grant Access to Doctor</h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Select Doctor</label>
                                    <select class="form-select" name="doctor_id" required>
                                        <option value="">Choose a doctor...</option>
                                        <?php foreach($available_doctors as $doctor): ?>
                                            <option value="<?php echo $doctor["id"]; ?>">
                                                <?php echo htmlspecialchars($doctor["full_name"]); ?> 
                                                (<?php echo htmlspecialchars($doctor["hospital_affiliation"]); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" name="grant_access" class="btn btn-primary w-100">
                                        Grant Access
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Access List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Current Access List</h5>
                        <?php if(empty($access_list)): ?>
                            <p class="text-muted">No doctors currently have access to your medical records.</p>
                        <?php else: ?>
                            <?php foreach($access_list as $access): ?>
                                <div class="card access-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($access["doctor_name"]); ?>
                                                </h6>
                                                <p class="card-text text-muted mb-0">
                                                    <?php echo htmlspecialchars($access["hospital_affiliation"]); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo $access["status"] == "active" ? "success" : "danger"; ?>">
                                                    <?php echo ucfirst($access["status"]); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="card-text">
                                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($access["phone_number"]); ?><br>
                                            <i class="bi bi-calendar"></i> Access granted on: <?php echo date("F j, Y", strtotime($access["granted_at"])); ?>
                                        </p>
                                        
                                        <?php if($access["status"] == "active"): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="access_id" value="<?php echo $access["id"]; ?>">
                                                <button type="submit" name="revoke_access" class="btn btn-danger btn-sm">
                                                    Revoke Access
                                                </button>
                                            </form>
                                        <?php endif; ?>
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