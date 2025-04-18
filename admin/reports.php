<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

$success = $error = "";

// Handle report generation
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["generate_report"])) {
    $report_type = $_POST["report_type"];
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];
    
    // Generate report based on type
    switch($report_type) {
        case "patient_statistics":
            $sql = "SELECT 
                        COUNT(*) as total_patients,
                        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as under_18,
                        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN 1 END) as age_18_30,
                        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN 1 END) as age_31_50,
                        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 50 THEN 1 END) as over_50
                    FROM patients";
            break;
            
        case "appointment_statistics":
            $sql = "SELECT 
                        COUNT(*) as total_appointments,
                        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
                    FROM appointments
                    WHERE appointment_date BETWEEN ? AND ?";
            break;
            
        case "medical_records":
            $sql = "SELECT 
                        mr.*, 
                        p.full_name as patient_name,
                        d.full_name as doctor_name
                    FROM medical_records mr
                    JOIN patients p ON mr.patient_id = p.id
                    JOIN doctors d ON mr.doctor_id = d.id
                    WHERE mr.created_at BETWEEN ? AND ?
                    ORDER BY mr.created_at DESC";
            break;
            
        case "doctor_performance":
            $sql = "SELECT 
                        d.full_name,
                        COUNT(DISTINCT a.patient_id) as total_patients,
                        COUNT(a.id) as total_appointments,
                        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments
                    FROM doctors d
                    LEFT JOIN appointments a ON d.id = a.doctor_id
                    WHERE a.appointment_date BETWEEN ? AND ?
                    GROUP BY d.id, d.full_name";
            break;
    }
    
    // Execute query and store results
    if($stmt = mysqli_prepare($conn, $sql)) {
        if($report_type != "patient_statistics") {
            mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $report_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Generate CSV file
        $filename = $report_type . "_" . date("Y-m-d") . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        if(!empty($report_data)) {
            fputcsv($output, array_keys($report_data[0]));
            
            // Write data
            foreach($report_data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Generate Reports</h2>
                    <div>
                        <a href="../dashboard.php" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="../logout.php" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Report Type</label>
                                    <select name="report_type" class="form-select" required>
                                        <option value="">Select Report Type</option>
                                        <option value="patient_statistics">Patient Statistics</option>
                                        <option value="appointment_statistics">Appointment Statistics</option>
                                        <option value="medical_records">Medical Records</option>
                                        <option value="doctor_performance">Doctor Performance</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Range</label>
                                    <div class="input-group">
                                        <input type="date" name="start_date" class="form-control" required>
                                        <span class="input-group-text">to</span>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="generate_report" class="btn btn-primary">
                                <i class="bi bi-download"></i> Generate Report
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Report Descriptions -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Patient Statistics</h5>
                                <p class="card-text">Generate a comprehensive report of patient demographics including age distribution and total patient count.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Appointment Statistics</h5>
                                <p class="card-text">View detailed statistics about appointments including scheduled, completed, and cancelled appointments.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Medical Records</h5>
                                <p class="card-text">Export all medical records within the specified date range, including patient and doctor information.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Doctor Performance</h5>
                                <p class="card-text">Analyze doctor performance metrics including total patients, appointments, and completion rates.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 