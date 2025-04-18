<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$success_message = $error_message = "";

// Get all patients who have appointments with this doctor
$patients_sql = "SELECT DISTINCT p.*, 
                 COUNT(DISTINCT a.id) as total_appointments
                 FROM patients p 
                 LEFT JOIN appointments a ON p.user_id = a.patient_id AND a.doctor_id = ?
                 GROUP BY p.user_id
                 ORDER BY p.full_name";
$patients_stmt = mysqli_prepare($conn, $patients_sql);
mysqli_stmt_bind_param($patients_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($patients_stmt);
$patients_result = mysqli_stmt_get_result($patients_stmt);
$patients = [];
while($row = mysqli_fetch_assoc($patients_result)){
    $patients[] = $row;
}

// Get patient details if a specific patient is selected
$selected_patient = null;
$patient_appointments = [];

if(isset($_GET["patient_id"])) {
    $patient_id = $_GET["patient_id"];
    
    // Get patient details
    $patient_sql = "SELECT * FROM patients WHERE user_id = ?";
    $patient_stmt = mysqli_prepare($conn, $patient_sql);
    mysqli_stmt_bind_param($patient_stmt, "i", $patient_id);
    mysqli_stmt_execute($patient_stmt);
    $patient_result = mysqli_stmt_get_result($patient_stmt);
    $selected_patient = mysqli_fetch_assoc($patient_result);
    
    if($selected_patient) {
        // Get patient's appointments
        $appointments_sql = "SELECT * FROM appointments 
                           WHERE patient_id = ? AND doctor_id = ? 
                           ORDER BY appointment_date DESC, appointment_time DESC";
        $appointments_stmt = mysqli_prepare($conn, $appointments_sql);
        mysqli_stmt_bind_param($appointments_stmt, "ii", $patient_id, $_SESSION["id"]);
        mysqli_stmt_execute($appointments_stmt);
        $appointments_result = mysqli_stmt_get_result($appointments_stmt);
        while($row = mysqli_fetch_assoc($appointments_result)){
            $patient_appointments[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 1200px; margin: 2rem auto; }
        .card { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Patients</h1>
        
        <?php if($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Patients List -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Your Patients</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($patients)): ?>
                        <p>You don't have any patients yet.</p>
                        <?php else: ?>
                        <div class="list-group">
                            <?php foreach($patients as $patient): ?>
                            <a href="?patient_id=<?php echo $patient['user_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo isset($_GET['patient_id']) && $_GET['patient_id'] == $patient['user_id'] ? 'active' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($patient['full_name']); ?></h6>
                                    <small><?php echo $patient['total_appointments']; ?> appointments</small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($patient['phone_number']); ?></p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Patient Details -->
            <div class="col-md-8">
                <?php if($selected_patient): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Patient Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_patient['full_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_patient['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($selected_patient['phone_number']); ?></p>
                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($selected_patient['gender']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($selected_patient['date_of_birth'])); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($selected_patient['address']); ?></p>
                                <p><strong>Medical Record ID:</strong> <?php echo htmlspecialchars($selected_patient['medical_record_id']); ?></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="appointments.php?patient_id=<?php echo $selected_patient['user_id']; ?>" class="btn btn-primary">
                                Schedule Appointment
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Patient Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($patient_appointments)): ?>
                        <p>No appointments scheduled for this patient.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($patient_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                        <td><?php echo ucfirst($appointment['status']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Select a Patient</h5>
                        <p class="card-text">Choose a patient from the list to view their details and appointments.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 