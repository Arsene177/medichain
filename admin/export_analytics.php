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

// Get total population count for percentage calculations
$population_sql = "SELECT COUNT(*) as total FROM patients";
$population_result = mysqli_query($conn, $population_sql);
$total_population = mysqli_fetch_assoc($population_result)['total'];

// Get epidemic statistics
$epidemic_stats = [];
$epidemic_sql = "SELECT 
    mr.diagnosis,
    COUNT(DISTINCT mr.patient_id) as affected_patients,
    ROUND((COUNT(DISTINCT mr.patient_id) * 100.0 / ?), 2) as population_percentage,
    COUNT(*) as total_cases,
    COUNT(CASE WHEN mr.severity IN ('severe', 'critical') THEN 1 END) as severe_cases,
    COUNT(CASE WHEN mr.hospitalization_status IN ('hospitalized', 'icu') THEN 1 END) as hospitalized_cases,
    COUNT(CASE WHEN mr.outcome = 'recovered' THEN 1 END) as recovered_cases,
    COUNT(CASE WHEN mr.outcome = 'deceased' THEN 1 END) as deceased_cases,
    MIN(mr.created_at) as first_case,
    MAX(mr.created_at) as latest_case,
    DATEDIFF(MAX(mr.created_at), MIN(mr.created_at)) as outbreak_duration_days
FROM medical_records mr
WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $epidemic_sql .= " AND EXISTS (
        SELECT 1 FROM patients p 
        WHERE p.id = mr.patient_id 
        AND p.address LIKE ?
    )";
}

$epidemic_sql .= " GROUP BY mr.diagnosis
    HAVING affected_patients > 0
    ORDER BY affected_patients DESC";

if($stmt = mysqli_prepare($conn, $epidemic_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "isss", $total_population, $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $total_population, $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $epidemic_stats[] = $row;
    }
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="epidemic_analysis_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel display
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
fputcsv($output, [
    'Disease',
    'Affected Patients',
    'Population Percentage',
    'Total Cases',
    'Severe Cases',
    'Hospitalized Cases',
    'Recovered Cases',
    'Deceased Cases',
    'First Case Date',
    'Latest Case Date',
    'Outbreak Duration (Days)',
    'Status'
]);

// Write data rows
foreach($epidemic_stats as $epidemic) {
    $status = 'Controlled';
    if($epidemic['population_percentage'] >= 10) {
        $status = 'Epidemic';
    } elseif($epidemic['population_percentage'] >= 5) {
        $status = 'Potential Epidemic';
    }

    fputcsv($output, [
        $epidemic['diagnosis'],
        $epidemic['affected_patients'],
        $epidemic['population_percentage'] . '%',
        $epidemic['total_cases'],
        $epidemic['severe_cases'],
        $epidemic['hospitalized_cases'],
        $epidemic['recovered_cases'],
        $epidemic['deceased_cases'],
        $epidemic['first_case'],
        $epidemic['latest_case'],
        $epidemic['outbreak_duration_days'],
        $status
    ]);
}

// Close the output stream
fclose($output);
exit; 