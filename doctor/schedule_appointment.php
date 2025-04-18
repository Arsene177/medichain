<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$doctor_id = $_SESSION["id"];
$patient_id = isset($_GET["patient_id"]) ? $_GET["patient_id"] : 0;
$patient = null;
$success = $error = "";

// Get patient information
if($patient_id > 0){
    $patient_sql = "SELECT p.*, u.username 
                    FROM patients p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.id = ?";
    
    if($stmt = mysqli_prepare($conn, $patient_sql)){
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $patient = mysqli_fetch_assoc($result);
    }
}

// Get doctor information
$doctor_sql = "SELECT full_name, hospital FROM doctors WHERE user_id = ?";
if($stmt = mysqli_prepare($conn, $doctor_sql)){
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $doctor_result = mysqli_stmt_get_result($stmt);
    $doctor_info = mysqli_fetch_assoc($doctor_result);
}

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["appointment_date"])){
    $appointment_date = $_POST["appointment_date"];
    $appointment_time = $_POST["appointment_time"];
    $notes = $_POST["notes"];
    
    // Check if the time slot is available
    $check_sql = "SELECT COUNT(*) as count FROM appointments 
                  WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'";
    
    if($stmt = mysqli_prepare($conn, $check_sql)){
        mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $appointment_date, $appointment_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if($row["count"] > 0){
            $error = "This time slot is already booked. Please choose another time.";
        } else {
            // Schedule the appointment
            $appointment_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, notes, status) 
                              VALUES (?, ?, ?, ?, ?, 'scheduled')";
            
            if($stmt = mysqli_prepare($conn, $appointment_sql)){
                mysqli_stmt_bind_param($stmt, "iisss", 
                    $patient_id,
                    $doctor_id,
                    $appointment_date,
                    $appointment_time,
                    $notes
                );
                
                if(mysqli_stmt_execute($stmt)){
                    $success = "Appointment scheduled successfully.";
                    
                    // Redirect to patient view after 2 seconds
                    header("refresh:2;url=view_patient.php?id=" . $patient_id);
                } else {
                    $error = "Error scheduling appointment.";
                }
            }
        }
    }
}

// Get available time slots for the selected date
$available_times = array();
if(isset($_GET["date"])){
    $date = $_GET["date"];
    $times_sql = "SELECT appointment_time FROM appointments 
                  WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'";
    
    if($stmt = mysqli_prepare($conn, $times_sql)){
        mysqli_stmt_bind_param($stmt, "is", $doctor_id, $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($result)){
            $available_times[] = $row["appointment_time"];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .time-slot {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .time-slot:hover {
            background-color: #f8f9fa;
        }
        .time-slot.selected {
            background-color: #e9ecef;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Schedule Appointment</h2>
                    <a href="view_patient.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Patient
                    </a>
                </div>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if($patient): ?>
                    <div class="card form-card">
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5 class="card-title">Patient Information</h5>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($patient["full_name"]); ?></p>
                                    <p class="mb-1"><strong>Medical Record ID:</strong> <?php echo htmlspecialchars($patient["medical_record_id"]); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="card-title">Doctor Information</h5>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($doctor_info["full_name"]); ?></p>
                                    <p class="mb-1"><strong>Hospital:</strong> <?php echo htmlspecialchars($doctor_info["hospital"]); ?></p>
                                </div>
                            </div>

                            <form method="POST" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Appointment Date</label>
                                    <input type="date" class="form-control" name="appointment_date" required 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); ?>"
                                           onchange="this.form.submit()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Appointment Time</label>
                                    <select class="form-select" name="appointment_time" required>
                                        <option value="">Select time</option>
                                        <?php
                                        $start_time = strtotime("09:00");
                                        $end_time = strtotime("17:00");
                                        $interval = 30 * 60; // 30 minutes
                                        
                                        for($time = $start_time; $time <= $end_time; $time += $interval){
                                            $time_str = date("H:i", $time);
                                            $disabled = in_array($time_str, $available_times) ? "disabled" : "";
                                            echo "<option value='$time_str' $disabled>$time_str</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                        placeholder="Enter any additional notes or instructions for the appointment"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-calendar-check"></i> Schedule Appointment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">Invalid patient ID.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 