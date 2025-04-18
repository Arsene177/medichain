<?php
session_start();
require_once "config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: login.php");
    exit;
}

// Check if doctor's account is approved
$sql = "SELECT status, rejection_reason FROM doctors WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if(mysqli_stmt_num_rows($stmt) == 1){
        mysqli_stmt_bind_result($stmt, $status, $rejection_reason);
        mysqli_stmt_fetch($stmt);
        
        if($status === "suspended"){
            session_destroy();
            $_SESSION['error'] = "Your account has been suspended. Reason: " . $rejection_reason;
            header("location: login.php");
            exit;
        } elseif($status !== "approved"){
            session_destroy();
            if($status === "pending"){
                $_SESSION['error'] = "Your account is pending approval. Please wait for admin validation.";
            } elseif($status === "rejected"){
                $_SESSION['error'] = "Your registration has been rejected. Please contact the administrator.";
            }
            header("location: login.php");
            exit;
        }
    }
    mysqli_stmt_close($stmt);
}

$doctor_id = $_SESSION["id"];

// Get doctor information
$doctor_sql = "SELECT full_name, hospital FROM doctors WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $doctor_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $doctor_result = mysqli_stmt_get_result($stmt);
    $doctor_info = mysqli_fetch_assoc($doctor_result);
}

// Get today's appointments
$today = date('Y-m-d');
$appointments_sql = "SELECT a.*, p.full_name as patient_name, p.medical_record_id 
                    FROM appointments a 
                    JOIN patients p ON a.patient_id = p.id 
                    WHERE a.doctor_id = ? AND a.appointment_date = ? 
                    ORDER BY a.appointment_time";
if($stmt = mysqli_prepare($conn, $appointments_sql)){
    mysqli_stmt_bind_param($stmt, "is", $doctor_id, $today);
    mysqli_stmt_execute($stmt);
    $appointments_result = mysqli_stmt_get_result($stmt);
}

// Get recent patients
$recent_patients_sql = "SELECT p.*, m.created_at as last_record_date 
                       FROM patients p 
                       LEFT JOIN medical_records m ON p.id = m.patient_id 
                       WHERE p.registered_by = ? 
                       GROUP BY p.id 
                       ORDER BY p.created_at DESC 
                       LIMIT 5";
if($stmt = mysqli_prepare($conn, $recent_patients_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $recent_patients_result = mysqli_stmt_get_result($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #0083B0, #00B4DB);
            color: white;
        }
        .nav-link {
            color: #0083B0;
        }
        .nav-link:hover {
            color: #006d94;
        }
        .nav-link.active {
            color: #006d94;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, Dr. <?php echo isset($doctor_info["full_name"]) ? htmlspecialchars($doctor_info["full_name"]) : "User"; ?></h2>
                    <div>
                        <a href="profile.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                        <a href="../logout.php" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
                <p class="text-muted"><?php echo isset($doctor_info["hospital"]) ? htmlspecialchars($doctor_info["hospital"]) : "Hospital"; ?></p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="nav nav-pills mb-4">
            <a class="nav-link active" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="profile.php">Profile</a>
            <a class="nav-link" href="appointments.php">Appointments</a>
            <a class="nav-link" href="patients.php">Patients</a>
            <a class="nav-link" href="medical_records.php">Medical Records</a>
            <a class="nav-link" href="settings.php">Settings</a>
        </nav>

        <!-- Quick Patient Search -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Patient Search</h5>
                        <form action="view_patient.php" method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="medical_record_id" 
                                       placeholder="Enter Patient's Medical Record ID" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Search Patient
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="register_patient.php" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Register New Patient
                            </a>
                            <a href="search_patient.php" class="btn btn-info text-white">
                                <i class="bi bi-search"></i> Search Patient
                            </a>
                            <a href="appointments.php" class="btn btn-success">
                                <i class="bi bi-calendar-check"></i> View Appointments
                            </a>
                            <a href="medical_records.php" class="btn btn-warning text-white">
                                <i class="bi bi-file-earmark-text"></i> Medical Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="col-md-8">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Today's Appointments</h5>
                        <?php if(mysqli_num_rows($appointments_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Patient</th>
                                            <th>MR ID</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($appointment = mysqli_fetch_assoc($appointments_result)): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['medical_record_id']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $appointment['status'] == 'completed' ? 'success' : 'primary'; ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="view_patient.php?id=<?php echo $appointment['patient_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No appointments scheduled for today.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Patients -->
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Patients</h5>
                        <?php if(mysqli_num_rows($recent_patients_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Medical Record ID</th>
                                            <th>Last Visit</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($patient = mysqli_fetch_assoc($recent_patients_result)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($patient['medical_record_id']); ?></td>
                                                <td><?php echo $patient['last_record_date'] ? date('M d, Y', strtotime($patient['last_record_date'])) : 'No visits yet'; ?></td>
                                                <td>
                                                    <a href="view_patient.php?id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="add_record.php?patient_id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-plus-circle"></i> Add Record
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No patients registered yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 