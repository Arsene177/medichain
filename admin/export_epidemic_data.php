<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../index.php");
    exit;
}

// Get date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$region = isset($_GET['region']) ? $_GET['region'] : '';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="epidemic_data_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'Date',
    'Diagnosis',
    'ICD-10 Code',
    'Severity',
    'Hospitalization Status',
    'Travel History',
    'Region',
    'Patient Age',
    'Patient Gender',
    'Complications',
    'Treatment Outcome',
    'Recovery Time (days)'
]);

// Get medical records data
$sql = "SELECT 
            mr.created_at,
            mr.diagnosis,
            mr.icd_code,
            mr.severity,
            mr.hospitalization_status,
            mr.travel_history,
            mr.complications,
            mr.treatment_outcome,
            mr.recovery_time,
            p.address,
            p.date_of_birth,
            p.gender
        FROM medical_records mr
        JOIN patients p ON mr.patient_id = p.id
        WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $sql .= " AND p.address LIKE ?";
}

$sql .= " ORDER BY mr.created_at DESC";

if($stmt = mysqli_prepare($conn, $sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        // Extract region from address
        $address_parts = explode(',', $row['address']);
        $region = end($address_parts);
        $region = trim($region);
        
        // Calculate age
        $dob = new DateTime($row['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        
        // Write row to CSV
        fputcsv($output, [
            $row['created_at'],
            $row['diagnosis'],
            $row['icd_code'],
            $row['severity'],
            $row['hospitalization_status'],
            $row['travel_history'],
            $region,
            $age,
            $row['gender'],
            $row['complications'],
            $row['treatment_outcome'],
            $row['recovery_time']
        ]);
    }
}

// Close the output stream
fclose($output);
exit;
?> 