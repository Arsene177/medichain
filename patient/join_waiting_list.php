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

// Get patient information
$patient_sql = "SELECT p.*, u.username 
                FROM patients p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = ?";

if($stmt = mysqli_prepare($conn, $patient_sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_assoc($result);
}

// Get available doctors
$doctors_sql = "SELECT d.*, u.username 
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                ORDER BY d.full_name";

if($stmt = mysqli_prepare($conn, $doctors_sql)){
    mysqli_stmt_execute($stmt);
    $doctors_result = mysqli_stmt_get_result($stmt);
    $doctors = array();
    while($row = mysqli_fetch_assoc($doctors_result)){
        $doctors[] = $row;
    }
}

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["doctor_id"])){
    $doctor_id = $_POST["doctor_id"];
    $preferred_date = $_POST["preferred_date"];
    $preferred_time = $_POST["preferred_time"];
    $reason = $_POST["reason"];
    
    // Check if patient is already on waiting list for this doctor
    $check_sql = "SELECT COUNT(*) as count FROM waiting_list 
                  WHERE patient_id = ? AND doctor_id = ? AND status = 'waiting'";
    
    if($stmt = mysqli_prepare($conn, $check_sql)){
        mysqli_stmt_bind_param($stmt, "ii", $patient_id, $doctor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if($row["count"] > 0){
            $error = "You are already on the waiting list for this doctor.";
        } else {
            // Add to waiting list
            $waiting_sql = "INSERT INTO waiting_list (patient_id, doctor_id, preferred_date, preferred_time, reason, status) 
                          VALUES (?, ?, ?, ?, ?, 'waiting')";
            
            if($stmt = mysqli_prepare($conn, $waiting_sql)){
                mysqli_stmt_bind_param($stmt, "iisss", 
                    $patient_id,
                    $doctor_id,
                    $preferred_date,
                    $preferred_time,
                    $reason
                );
                
                if(mysqli_stmt_execute($stmt)){
                    $success = "You have been added to the waiting list. We will notify you when a slot becomes available.";
                    
                    // TODO: Send email notification to patient
                    // This would require integrating with an email service
                } else {
                    $error = "Error joining waiting list.";
                }
            }
        }
    }
}

// Get current waiting list entries
$waiting_list_sql = "SELECT w.*, d.full_name as doctor_name, d.hospital 
                     FROM waiting_list w 
                     JOIN doctors d ON w.doctor_id = d.id 
                     WHERE w.patient_id = ? AND w.status = 'waiting' 
                     ORDER BY w.created_at DESC";

if($stmt = mysqli_prepare($conn, $waiting_list_sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $waiting_list_result = mysqli_stmt_get_result($stmt);
    $waiting_list = array();
    while($row = mysqli_fetch_assoc($waiting_list_result)){
        $waiting_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Waiting List - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Join Waiting List</h2>
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

                <div class="card form-card">
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Doctor</label>
                                <select class="form-select" name="doctor_id" required>
                                    <option value="">Choose a doctor</option>
                                    <?php foreach($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor["id"]; ?>">
                                            <?php echo htmlspecialchars($doctor["full_name"]); ?> 
                                            (<?php echo htmlspecialchars($doctor["hospital_affiliation"]); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Preferred Date</label>
                                <input type="date" class="form-control" name="preferred_date" required 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Preferred Time</label>
                                <select class="form-select" name="preferred_time" required>
                                    <option value="">Select time</option>
                                    <?php
                                    $start_time = strtotime("09:00");
                                    $end_time = strtotime("17:00");
                                    $interval = 30 * 60; // 30 minutes
                                    
                                    for($time = $start_time; $time <= $end_time; $time += $interval){
                                        echo "<option value='" . date("H:i", $time) . "'>" . 
                                             date("g:i A", $time) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Reason for Visit</label>
                                <textarea class="form-control" name="reason" rows="3" required 
                                    placeholder="Please describe why you need to see the doctor"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-clock-history"></i> Join Waiting List
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Display current waiting list entries -->
                <?php if(!empty($waiting_list)): ?>
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Your Current Waiting List Entries</h5>
                            <?php foreach($waiting_list as $entry): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">
                                            Dr. <?php echo htmlspecialchars($entry["doctor_name"]); ?> - 
                                            <?php echo htmlspecialchars($entry["hospital"]); ?>
                                        </h6>
                                        <p class="card-text">
                                            <strong>Preferred Date:</strong> <?php echo date('F j, Y', strtotime($entry["preferred_date"])); ?><br>
                                            <strong>Preferred Time:</strong> <?php echo date('g:i A', strtotime($entry["preferred_time"])); ?><br>
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($entry["reason"]); ?><br>
                                            <strong>Added to List:</strong> <?php echo date('F j, Y g:i A', strtotime($entry["created_at"])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 