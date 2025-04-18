<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$doctor_id = $_SESSION["id"];
$success = $error = "";

// Get patient information if patient_id is provided
$patient = null;
if(isset($_GET["patient_id"])) {
    $patient_sql = "SELECT p.*, u.email FROM patients p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.id = ?";
    if($stmt = mysqli_prepare($conn, $patient_sql)){
        mysqli_stmt_bind_param($stmt, "i", $_GET["patient_id"]);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $patient = mysqli_fetch_assoc($result);
    }
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate required fields
    if(empty($_POST["chief_complaint"]) || empty($_POST["diagnosis"]) || empty($_POST["treatment"])){
        $error = "Please fill in all required fields.";
    } else {
        // Handle file upload for lab results
        $lab_file_path = null;
        if(isset($_FILES["lab_file"]) && $_FILES["lab_file"]["error"] == 0) {
            $allowed_types = ["application/pdf", "image/jpeg", "image/png", "image/jpg"];
            $file_type = $_FILES["lab_file"]["type"];
            
            if(in_array($file_type, $allowed_types)) {
                $upload_dir = "../uploads/lab_results/";
                
                // Create directory if it doesn't exist
                if(!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = time() . "_" . basename($_FILES["lab_file"]["name"]);
                $target_file = $upload_dir . $file_name;
                
                if(move_uploaded_file($_FILES["lab_file"]["tmp_name"], $target_file)) {
                    $lab_file_path = "uploads/lab_results/" . $file_name;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "Sorry, only PDF, JPEG, PNG & JPG files are allowed.";
            }
        }
        
        // Get doctor information for signature
        $doctor_sql = "SELECT full_name, hospital, phone_number FROM doctors WHERE user_id = ?";
        if($stmt = mysqli_prepare($conn, $doctor_sql)){
            mysqli_stmt_bind_param($stmt, "i", $doctor_id);
            mysqli_stmt_execute($stmt);
            $doctor_result = mysqli_stmt_get_result($stmt);
            $doctor_info = mysqli_fetch_assoc($doctor_result);
        }
        
        // Create content string from form data
        $content = "Chief Complaint: " . $_POST["chief_complaint"] . "\n\n";
        $content .= "Symptoms: " . $_POST["symptoms"] . "\n\n";
        $content .= "Diagnosis: " . $_POST["diagnosis"] . "\n\n";
        $content .= "Treatment: " . $_POST["treatment"] . "\n\n";
        if(!empty($_POST["vital_signs"])) {
            $content .= "Vital Signs: " . json_encode($_POST["vital_signs"]) . "\n\n";
        }
        if(!empty($_POST["lab_results"])) {
            $content .= "Lab Results: " . $_POST["lab_results"] . "\n\n";
        }
        if(!empty($_POST["medications"])) {
            $content .= "Medications: " . $_POST["medications"] . "\n\n";
        }
        if(!empty($_POST["follow_up_notes"])) {
            $content .= "Follow-up Notes: " . $_POST["follow_up_notes"] . "\n\n";
        }
        
        // Create doctor signature
        $signature = $doctor_info["full_name"] . " | " . 
                    $doctor_info["hospital"] . " | " . 
                    $doctor_info["phone_number"];
        
        // Insert medical record
        $sql = "INSERT INTO medical_records (
                patient_id, doctor_id, entry_type, content, doctor_signature, created_at
                ) VALUES (?, ?, 'regular', ?, ?, NOW())";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "iiss", 
                $_GET["patient_id"],
                $doctor_id,
                $content,
                $signature
            );
            
            if(mysqli_stmt_execute($stmt)){
                $success = "Medical record added successfully.";
                // Redirect to patient view page after successful addition
                header("location: view_patient.php?id=" . $_GET["patient_id"]);
                exit;
            } else {
                $error = "Something went wrong. Please try again later. Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medical Record - MediChain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-color: #e9ecef;
        }

        body {
            background: var(--light-bg);
            color: var(--primary-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar {
            background: var(--primary-color);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }

        .form-section:hover {
            transform: translateY(-2px);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--secondary-color);
        }

        .required-field::after {
            content: " *";
            color: var(--accent-color);
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background: var(--secondary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #95a5a6;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .patient-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .patient-info h4 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .patient-info p {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .patient-info strong {
            color: var(--secondary-color);
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .vital-sign-input {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .vital-sign-input label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .vital-sign-input input {
            border: none;
            background: transparent;
            width: 100%;
            padding: 0.5rem;
            font-size: 1.1rem;
        }

        .vital-sign-input input:focus {
            outline: none;
        }

        @media (max-width: 768px) {
            .form-section {
                padding: 20px;
            }

            .vital-signs-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-heart-pulse"></i> MediChain
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Add Medical Record</h2>
                    <a href="view_patient.php?id=<?php echo $_GET["patient_id"]; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Patient
                    </a>
            </div>

                <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?patient_id=" . $_GET["patient_id"]); ?>" method="post" enctype="multipart/form-data">
                    <!-- Patient Information -->
            <div class="patient-info">
                        <h4><i class="bi bi-person-circle"></i> Patient Information</h4>
                <div class="row">
                    <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($patient["full_name"]); ?></p>
                                <p><strong>Medical Record ID:</strong> <?php echo htmlspecialchars($patient["medical_record_id"]); ?></p>
                    </div>
                    <div class="col-md-6">
                                <p><strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($patient["date_of_birth"])); ?></p>
                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient["gender"]); ?></p>
                    </div>
                </div>
            </div>
            
                    <!-- Chief Complaint -->
                <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-clipboard2-pulse"></i> Chief Complaint
                        </h4>
                        <div class="mb-3">
                            <label for="chief_complaint" class="form-label required-field">Primary Complaint</label>
                            <textarea class="form-control" id="chief_complaint" name="chief_complaint" rows="3" required
                                    placeholder="Describe the patient's primary complaint..."></textarea>
                        </div>
                        </div>
                        
                    <!-- Vital Signs -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-activity"></i> Vital Signs
                        </h4>
                        <div class="vital-signs-grid">
                            <div class="vital-sign-input">
                                <label for="temperature">Temperature (°C)</label>
                                <input type="number" step="0.1" id="temperature" name="vital_signs[temperature]" placeholder="36.5">
                            </div>
                            <div class="vital-sign-input">
                                <label for="blood_pressure">Blood Pressure (mmHg)</label>
                                <input type="text" id="blood_pressure" name="vital_signs[blood_pressure]" placeholder="120/80">
                            </div>
                            <div class="vital-sign-input">
                                <label for="heart_rate">Heart Rate (bpm)</label>
                                <input type="number" id="heart_rate" name="vital_signs[heart_rate]" placeholder="72">
                            </div>
                            <div class="vital-sign-input">
                                <label for="respiratory_rate">Respiratory Rate (bpm)</label>
                                <input type="number" id="respiratory_rate" name="vital_signs[respiratory_rate]" placeholder="16">
                        </div>
                    </div>
                </div>

                    <!-- Symptoms -->
                <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-list-check"></i> Symptoms
                        </h4>
                    <div class="mb-3">
                            <label for="symptoms" class="form-label required-field">Detailed Symptoms</label>
                            <textarea class="form-control" id="symptoms" name="symptoms" rows="4" required
                                    placeholder="List all symptoms in detail..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Diagnosis -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-file-medical"></i> Diagnosis
                        </h4>
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label required-field">Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required
                                    placeholder="Enter the diagnosis..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="icd_code" class="form-label">ICD-10 Code</label>
                            <input type="text" class="form-control" id="icd_code" name="icd_code" 
                                   placeholder="e.g., A15.3 - Tuberculosis of lung">
                        </div>
                        <div class="mb-3">
                            <label for="severity" class="form-label">Severity</label>
                            <select class="form-select" id="severity" name="severity">
                                <option value="">Select severity</option>
                                <option value="mild">Mild</option>
                                <option value="moderate">Moderate</option>
                                <option value="severe">Severe</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="hospitalization_status" class="form-label">Hospitalization Status</label>
                            <select class="form-select" id="hospitalization_status" name="hospitalization_status">
                                <option value="">Select status</option>
                                <option value="outpatient">Outpatient</option>
                                <option value="hospitalized">Hospitalized</option>
                                <option value="icu">ICU</option>
                                <option value="discharged">Discharged</option>
                            </select>
                        </div>
                    </div>

                    <!-- Treatment -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-clipboard2-check"></i> Treatment Plan
                        </h4>
                        <div class="mb-3">
                            <label for="treatment" class="form-label required-field">Treatment Details</label>
                            <textarea class="form-control" id="treatment" name="treatment" rows="4" required
                                    placeholder="Describe the treatment plan in detail..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="outcome" class="form-label">Treatment Outcome</label>
                            <select class="form-select" id="outcome" name="outcome">
                                <option value="">Select outcome</option>
                                <option value="recovered">Recovered</option>
                                <option value="improving">Improving</option>
                                <option value="stable">Stable</option>
                                <option value="deteriorating">Deteriorating</option>
                                <option value="deceased">Deceased</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="recovery_time" class="form-label">Recovery Time (days)</label>
                            <input type="number" class="form-control" id="recovery_time" name="recovery_time" 
                                   placeholder="Number of days to recovery">
                        </div>
                        <div class="mb-3">
                            <label for="complications" class="form-label">Complications</label>
                            <textarea class="form-control" id="complications" name="complications" rows="2" 
                                    placeholder="List any complications..."></textarea>
                    </div>
                </div>

                    <!-- Epidemiological Information -->
                <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-graph-up"></i> Epidemiological Information
                        </h4>
                        <div class="mb-3">
                            <label for="travel_history" class="form-label">Recent Travel History</label>
                            <textarea class="form-control" id="travel_history" name="travel_history" rows="2" 
                                    placeholder="List recent travel locations and dates..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="exposure_source" class="form-label">Potential Exposure Source</label>
                            <textarea class="form-control" id="exposure_source" name="exposure_source" rows="2" 
                                    placeholder="Describe potential exposure sources..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="contact_tracing" class="form-label">Contact Tracing Information</label>
                            <textarea class="form-control" id="contact_tracing" name="contact_tracing" rows="3" 
                                    placeholder="List contacts and their status..."></textarea>
                        </div>
                    </div>

                    <!-- Medications -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-capsule"></i> Medications
                        </h4>
                        <div class="mb-3">
                            <label for="medications" class="form-label">Prescribed Medications</label>
                            <textarea class="form-control" id="medications" name="medications" rows="3" 
                                    placeholder="Include medication name, dosage, frequency, and duration..."></textarea>
                    </div>
                </div>

                    <!-- Lab Results -->
                <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-microscope"></i> Laboratory Results
                        </h4>
                        <div class="mb-3">
                            <label for="lab_results" class="form-label">Test Results</label>
                            <textarea class="form-control" id="lab_results" name="lab_results" rows="3" 
                                    placeholder="Include relevant test results and values..."></textarea>
                        </div>
                    <div class="mb-3">
                            <label for="lab_file" class="form-label">Upload Lab Results File</label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="lab_file" name="lab_file" accept=".pdf,.jpg,.jpeg,.png">
                                <label class="input-group-text" for="lab_file">Choose File</label>
                            </div>
                            <div class="form-text">Accepted formats: PDF, JPEG, PNG (Max size: 5MB)</div>
                        </div>
                    </div>

                    <!-- Follow-up -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="bi bi-calendar-check"></i> Follow-up Plan
                        </h4>
                        <div class="mb-3">
                            <label for="follow_up_notes" class="form-label">Follow-up Instructions</label>
                            <textarea class="form-control" id="follow_up_notes" name="follow_up_notes" rows="3" 
                                    placeholder="Include follow-up date and specific instructions..."></textarea>
                    </div>
                </div>

                    <div class="form-section">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Medical Record
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 