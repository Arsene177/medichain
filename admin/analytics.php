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

// Get total records count
$total_records = 0;
$total_sql = "SELECT COUNT(*) as total FROM medical_records mr WHERE mr.created_at BETWEEN ? AND ?";
if(!empty($region)) {
    $total_sql .= " AND EXISTS (
                    SELECT 1 FROM patients p 
                    WHERE p.id = mr.patient_id 
                    AND p.address LIKE ?
                  )";
}

if($stmt = mysqli_prepare($conn, $total_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $total_records = $row['total'];
}

// Get total population count for percentage calculations
$population_sql = "SELECT COUNT(*) as total FROM patients";
$population_result = mysqli_query($conn, $population_sql);
$total_population = mysqli_fetch_assoc($population_result)['total'];

// Get diagnosis statistics
$diagnosis_stats = [];
$diagnosis_sql = "SELECT 
                    mr.diagnosis,
        COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / ?), 2) as percentage
                  FROM medical_records mr
                  WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $diagnosis_sql .= " AND EXISTS (
                        SELECT 1 FROM patients p 
                        WHERE p.id = mr.patient_id 
                        AND p.address LIKE ?
                      )";
}

$diagnosis_sql .= " GROUP BY mr.diagnosis
    ORDER BY count DESC
                    LIMIT 10";

if($stmt = mysqli_prepare($conn, $diagnosis_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "isss", $total_records, $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $total_records, $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $diagnosis_stats[] = $row;
    }
}

// Get severity distribution
$severity_stats = [];
$severity_sql = "SELECT 
                    mr.severity,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / ?), 2) as percentage
                  FROM medical_records mr
                  WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $severity_sql .= " AND EXISTS (
                        SELECT 1 FROM patients p 
                        WHERE p.id = mr.patient_id 
                        AND p.address LIKE ?
                      )";
}

$severity_sql .= " GROUP BY mr.severity
                    ORDER BY count DESC";

if($stmt = mysqli_prepare($conn, $severity_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "isss", $total_records, $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $total_records, $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $severity_stats[] = $row;
    }
}

// Get hospitalization statistics
$hospitalization_stats = [];
$hospitalization_sql = "SELECT 
                         mr.hospitalization_status,
                         COUNT(*) as count,
                         ROUND((COUNT(*) * 100.0 / ?), 2) as percentage
                       FROM medical_records mr
                       WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $hospitalization_sql .= " AND EXISTS (
                             SELECT 1 FROM patients p 
                             WHERE p.id = mr.patient_id 
                             AND p.address LIKE ?
                           )";
}

$hospitalization_sql .= " GROUP BY mr.hospitalization_status
                         ORDER BY count DESC";

if($stmt = mysqli_prepare($conn, $hospitalization_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "isss", $total_records, $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $total_records, $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $hospitalization_stats[] = $row;
    }
}

// Get outcome statistics
$outcome_stats = [];
$outcome_sql = "SELECT 
                  mr.outcome,
                  COUNT(*) as count,
                  ROUND((COUNT(*) * 100.0 / ?), 2) as percentage
                FROM medical_records mr
                WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $outcome_sql .= " AND EXISTS (
                      SELECT 1 FROM patients p 
                      WHERE p.id = mr.patient_id 
                      AND p.address LIKE ?
                    )";
}

$outcome_sql .= " GROUP BY mr.outcome
                  ORDER BY count DESC";

if($stmt = mysqli_prepare($conn, $outcome_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "isss", $total_records, $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $total_records, $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $outcome_stats[] = $row;
    }
}

// Get age group distribution
$age_stats = [];
$age_sql = "SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 51 AND 70 THEN '51-70'
                ELSE 'Over 70'
        END as age_group,
              COUNT(*) as count,
              ROUND((COUNT(*) * 100.0 / ?), 2) as percentage
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
            WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $age_sql .= " AND p.address LIKE ?";
}

$age_sql .= " GROUP BY age_group
              ORDER BY FIELD(age_group, 'Under 18', '18-30', '31-50', '51-70', 'Over 70')";

if($stmt = mysqli_prepare($conn, $age_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "isss", $total_records, $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $total_records, $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $age_stats[] = $row;
    }
}

// Get regions for filter dropdown
$regions = [];
$regions_sql = "SELECT DISTINCT 
                  SUBSTRING_INDEX(SUBSTRING_INDEX(address, ',', -1), ' ', 1) as region
                FROM patients
                WHERE address IS NOT NULL
                AND address != ''
                ORDER BY region";

$regions_result = mysqli_query($conn, $regions_sql);
while($row = mysqli_fetch_assoc($regions_result)) {
    if(!empty($row['region'])) {
        $regions[] = $row['region'];
    }
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Analytics - MediChain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card-title {
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

        .card-title i {
            color: var(--secondary-color);
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
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

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead {
            background-color: var(--primary-color);
            color: white;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .stat-card p {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .percentage-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            margin-left: 0.5rem;
            background-color: #e9ecef;
        }

        .trend-indicator {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .trend-up {
            background-color: #f8d7da;
            color: #721c24;
        }

        .trend-down {
            background-color: #d4edda;
            color: #155724;
        }

        .trend-stable {
            background-color: #fff3cd;
            color: #856404;
        }

        .table-danger {
            background-color: rgba(231, 76, 60, 0.1) !important;
        }
        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-heart-pulse"></i> MediChain
            </a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Medical Analytics</h2>
                    <div>
                        <a href="export_analytics.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&region=<?php echo $region; ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Export Data
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="region" class="form-label">Region</label>
                            <select class="form-select" id="region" name="region">
                                <option value="">All Regions</option>
                                <?php foreach($regions as $r): ?>
                                    <option value="<?php echo $r; ?>" <?php echo $region == $r ? 'selected' : ''; ?>>
                                        <?php echo $r; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <h3><?php echo $total_records; ?></h3>
                            <p>Total Medical Records</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <h3>
                                <?php 
                                $critical_count = 0;
                                foreach($severity_stats as $s) {
                                    if($s['severity'] == 'critical') {
                                        $critical_count = $s['count'];
                                        break;
                                    }
                                }
                                echo $critical_count;
                                ?>
                            </h3>
                            <p>Critical Cases</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <h3>
                                <?php 
                                $hospitalized = 0;
                                foreach($hospitalization_stats as $h) {
                                    if($h['hospitalization_status'] == 'hospitalized' || $h['hospitalization_status'] == 'icu') {
                                        $hospitalized += $h['count'];
                                    }
                                }
                                echo $hospitalized;
                                ?>
                            </h3>
                            <p>Hospitalized Patients</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <h3>
                                <?php 
                                $recovered = 0;
                                foreach($outcome_stats as $o) {
                                    if($o['outcome'] == 'recovered') {
                                        $recovered = $o['count'];
                                        break;
                                    }
                                }
                                echo $recovered;
                                ?>
                            </h3>
                            <p>Recovered Patients</p>
                        </div>
                    </div>
                </div>

                <!-- Add this section after the Summary Stats section -->
                <div class="card mb-4">
                    <h5 class="card-title">
                        <i class="bi bi-virus"></i> Epidemic Analysis
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Disease</th>
                                    <th>Affected Patients</th>
                                    <th>Population %</th>
                                    <th>Total Cases</th>
                                    <th>Severe Cases</th>
                                    <th>Hospitalized</th>
                                    <th>Recovered</th>
                                    <th>Deceased</th>
                                    <th>Duration (Days)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($epidemic_stats as $epidemic): ?>
                                <tr class="<?php echo $epidemic['population_percentage'] >= 10 ? 'table-danger' : ''; ?>">
                                    <td><?php echo htmlspecialchars($epidemic['diagnosis']); ?></td>
                                    <td><?php echo $epidemic['affected_patients']; ?></td>
                                    <td>
                                        <span class="percentage-badge <?php echo $epidemic['population_percentage'] >= 10 ? 'bg-danger text-white' : ''; ?>">
                                            <?php echo $epidemic['population_percentage']; ?>%
                                        </span>
                                    </td>
                                    <td><?php echo $epidemic['total_cases']; ?></td>
                                    <td><?php echo $epidemic['severe_cases']; ?></td>
                                    <td><?php echo $epidemic['hospitalized_cases']; ?></td>
                                    <td><?php echo $epidemic['recovered_cases']; ?></td>
                                    <td><?php echo $epidemic['deceased_cases']; ?></td>
                                    <td><?php echo $epidemic['outbreak_duration_days']; ?></td>
                                    <td>
                                        <?php if($epidemic['population_percentage'] >= 10): ?>
                                            <span class="badge bg-danger">Epidemic</span>
                                        <?php elseif($epidemic['population_percentage'] >= 5): ?>
                                            <span class="badge bg-warning">Potential Epidemic</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Controlled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add this section for epidemic trends -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-graph-up"></i> Epidemic Trends
                            </h5>
                            <div class="chart-container">
                                <canvas id="epidemicTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-pie-chart"></i> Population Impact
                            </h5>
                            <div class="chart-container">
                                <canvas id="populationImpactChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnosis Distribution -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-pie-chart"></i> Top Diagnoses
                            </h5>
                            <div class="chart-container">
                                <canvas id="diagnosisChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-pie-chart"></i> Severity Distribution
                            </h5>
                            <div class="chart-container">
                                <canvas id="severityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hospitalization and Outcome -->
                        <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-pie-chart"></i> Hospitalization Status
                            </h5>
                            <div class="chart-container">
                                <canvas id="hospitalizationChart"></canvas>
                            </div>
                        </div>
                    </div>
                            <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-pie-chart"></i> Treatment Outcomes
                            </h5>
                                <div class="chart-container">
                                <canvas id="outcomeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Age Distribution -->
                <div class="card">
                    <h5 class="card-title">
                        <i class="bi bi-bar-chart"></i> Age Distribution
                    </h5>
                    <div class="chart-container">
                        <canvas id="ageChart"></canvas>
                    </div>
                </div>

                <!-- Detailed Statistics Tables -->
                <div class="row">
                            <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-table"></i> Diagnosis Statistics
                            </h5>
                                <div class="table-responsive">
                                <table class="table table-striped">
                                        <thead>
                                            <tr>
                                            <th>Diagnosis</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach($diagnosis_stats as $diagnosis): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($diagnosis['diagnosis']); ?></td>
                                            <td><?php echo $diagnosis['count']; ?></td>
                                            <td>
                                                <span class="percentage-badge">
                                                    <?php echo $diagnosis['percentage']; ?>%
                                                </span>
                                            </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-table"></i> Severity Statistics
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Severity</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($severity_stats as $severity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($severity['severity'] ?: 'Unknown'); ?></td>
                                            <td><?php echo $severity['count']; ?></td>
                                            <td>
                                                <span class="percentage-badge">
                                                    <?php echo $severity['percentage']; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                    </div>
                                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare data for charts
        const diagnosisData = <?php echo json_encode($diagnosis_stats); ?>;
        const severityData = <?php echo json_encode($severity_stats); ?>;
        const hospitalizationData = <?php echo json_encode($hospitalization_stats); ?>;
        const outcomeData = <?php echo json_encode($outcome_stats); ?>;
        const ageData = <?php echo json_encode($age_stats); ?>;
        const epidemicData = <?php echo json_encode($epidemic_stats); ?>;

        // Diagnosis Chart
        const diagnosisChart = new Chart(
            document.getElementById('diagnosisChart'),
            {
                type: 'pie',
                data: {
                    labels: diagnosisData.map(item => item.diagnosis),
                    datasets: [{
                        data: diagnosisData.map(item => item.count),
                        backgroundColor: [
                            '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e74c3c',
                            '#1abc9c', '#34495e', '#16a085', '#27ae60', '#2980b9'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // Severity Chart
        const severityChart = new Chart(
            document.getElementById('severityChart'),
            {
                type: 'pie',
                data: {
                    labels: severityData.map(item => item.severity || 'Unknown'),
                    datasets: [{
                        data: severityData.map(item => item.count),
                        backgroundColor: [
                            '#2ecc71', // mild
                            '#f1c40f', // moderate
                            '#e67e22', // severe
                            '#e74c3c', // critical
                            '#95a5a6'  // unknown
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // Hospitalization Chart
        const hospitalizationChart = new Chart(
            document.getElementById('hospitalizationChart'),
            {
                type: 'pie',
                data: {
                    labels: hospitalizationData.map(item => item.hospitalization_status || 'Unknown'),
                    datasets: [{
                        data: hospitalizationData.map(item => item.count),
                        backgroundColor: [
                            '#2ecc71', // outpatient
                            '#3498db', // hospitalized
                            '#e74c3c', // icu
                            '#f1c40f', // discharged
                            '#95a5a6'  // unknown
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // Outcome Chart
        const outcomeChart = new Chart(
            document.getElementById('outcomeChart'),
            {
            type: 'pie',
            data: {
                    labels: outcomeData.map(item => item.outcome || 'Unknown'),
                datasets: [{
                        data: outcomeData.map(item => item.count),
                        backgroundColor: [
                            '#2ecc71', // recovered
                            '#3498db', // improving
                            '#f1c40f', // stable
                            '#e67e22', // deteriorating
                            '#e74c3c', // deceased
                            '#95a5a6'  // unknown
                        ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // Age Distribution Chart
        const ageChart = new Chart(
            document.getElementById('ageChart'),
            {
                type: 'bar',
                data: {
                    labels: ageData.map(item => item.age_group),
                    datasets: [{
                        label: 'Number of Patients',
                        data: ageData.map(item => item.count),
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );

        // Epidemic Trend Chart
        const epidemicTrendChart = new Chart(
            document.getElementById('epidemicTrendChart'),
            {
                type: 'line',
                data: {
                    labels: epidemicData.map(item => item.diagnosis),
                    datasets: [{
                        label: 'Population Percentage',
                        data: epidemicData.map(item => item.population_percentage),
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Population Percentage (%)'
                            }
                        }
                    },
                    plugins: {
                        annotation: {
                            annotations: {
                                epidemicLine: {
                                    type: 'line',
                                    yMin: 10,
                                    yMax: 10,
                                    borderColor: 'rgb(255, 0, 0)',
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    label: {
                                        content: 'Epidemic Threshold (10%)',
                                        enabled: true
                                    }
                                }
                            }
                        }
                    }
                }
            }
        );

        // Population Impact Chart
        const populationImpactChart = new Chart(
            document.getElementById('populationImpactChart'),
            {
                type: 'doughnut',
                data: {
                    labels: ['Affected Population', 'Unaffected Population'],
                    datasets: [{
                        data: [
                            <?php 
                            $total_affected = array_sum(array_column($epidemic_stats, 'affected_patients'));
                            echo $total_affected . ',' . ($total_population - $total_affected);
                            ?>
                        ],
                        backgroundColor: ['#e74c3c', '#2ecc71']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );
    </script>
</body>
</html> 