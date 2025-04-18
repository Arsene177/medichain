<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../index.php");
    exit;
}

// Fetch statistics
$stats = array();

// Total patients
$sql = "SELECT COUNT(*) as total FROM patients";
$result = mysqli_query($conn, $sql);
$stats['total_patients'] = mysqli_fetch_assoc($result)['total'];

// Total doctors
$sql = "SELECT COUNT(*) as total FROM doctors";
$result = mysqli_query($conn, $sql);
$stats['total_doctors'] = mysqli_fetch_assoc($result)['total'];

// Pending doctor approvals
$sql = "SELECT COUNT(*) as total FROM doctors WHERE status = 'pending'";
$result = mysqli_query($conn, $sql);
$stats['pending_doctors'] = mysqli_fetch_assoc($result)['total'];

// Total appointments
$sql = "SELECT COUNT(*) as total FROM appointments";
$result = mysqli_query($conn, $sql);
$stats['total_appointments'] = mysqli_fetch_assoc($result)['total'];

// Fetch all pending doctor registrations
$sql = "SELECT d.*, u.username, u.email 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC";
$pending_doctors = mysqli_query($conn, $sql);

// Recent appointments
$sql = "SELECT a.*, p.full_name as patient_name, d.full_name as doctor_name 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        JOIN doctors d ON a.doctor_id = d.id 
        ORDER BY a.appointment_date DESC LIMIT 5";
$recent_appointments = mysqli_query($conn, $sql);

// Recent doctor registrations (excluding pending)
$sql = "SELECT d.*, u.username 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.status != 'pending'
        ORDER BY d.created_at DESC LIMIT 5";
$recent_doctors = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
        }
        .dashboard-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin: 2rem auto;
            max-width: 1200px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2rem;
            color: #0083B0;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0083B0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .section-title {
            color: #0083B0;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0083B0;
        }
        .table-responsive {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        .pending-doctors {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .pending-doctors h4 {
            color: #856404;
            margin-bottom: 1rem;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Admin Dashboard</h1>
                <div>
                    <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav nav-pills mb-4">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_doctors.php">Manage Doctors</a>
                <a class="nav-link" href="manage_users.php">Manage Users</a>
                <a class="nav-link" href="analytics.php">Analytics</a>
                <a class="nav-link" href="settings.php">Settings</a>
            </nav>

            <!-- Pending Doctor Approvals -->
            <?php if(mysqli_num_rows($pending_doctors) > 0): ?>
            <div class="pending-doctors">
                <h4><i class="bi bi-exclamation-triangle"></i> Pending Doctor Approvals</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Specialization</th>
                                <th>Hospital</th>
                                <th>Contact</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($doctor = mysqli_fetch_assoc($pending_doctors)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doctor['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['hospital']); ?></td>
                                <td>
                                    <div>Email: <?php echo htmlspecialchars($doctor['email']); ?></div>
                                    <div>Phone: <?php echo htmlspecialchars($doctor['phone_number']); ?></div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($doctor['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <form action="manage_doctors.php" method="post" class="d-inline">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $doctor['id']; ?>">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?php echo $doctor['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Doctor Registration</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="manage_doctors.php" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Reason for Rejection</label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_doctors']; ?></div>
                        <div class="stat-label">Total Doctors</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['pending_doctors']; ?></div>
                        <div class="stat-label">Pending Approvals</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6">
                    <h4 class="section-title">Recent Appointments</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($appointment = mysqli_fetch_assoc($recent_appointments)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $appointment['status'] == 'completed' ? 'success' : 
                                                ($appointment['status'] == 'cancelled' ? 'danger' : 'primary'); 
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-6">
                    <h4 class="section-title">Recent Doctor Registrations</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Specialization</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($doctor = mysqli_fetch_assoc($recent_doctors)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $doctor['status'] == 'approved' ? 'success' : 
                                                ($doctor['status'] == 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($doctor['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($doctor['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Manage Doctors Card -->
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Doctors</h5>
                            <p class="card-text">Review and manage doctor registrations, including approving or rejecting new applications.</p>
                            <a href="manage_doctors.php" class="btn btn-primary">
                                <i class="bi bi-people"></i> Manage Doctors
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Manage Patients Card -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Users</h5>
                            <p class="card-text">View and manage all user accounts, including patients, doctors, and administrators.</p>
                            <a href="manage_users.php" class="btn btn-primary">
                                <i class="bi bi-people-fill"></i> Manage Users
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Manage Appointments Card -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Appointments</h5>
                            <p class="card-text">View and manage all appointments in the system, including scheduling and status updates.</p>
                            <a href="appointments.php" class="btn btn-primary">
                                <i class="bi bi-calendar-check"></i> Manage Appointments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 