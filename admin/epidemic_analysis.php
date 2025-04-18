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

// Get diagnosis trends
$diagnosis_trends = [];
$diagnosis_sql = "SELECT 
                    mr.diagnosis, 
                    mr.icd_code,
                    COUNT(*) as count,
                    DATE_FORMAT(mr.created_at, '%Y-%m-%d') as date
                  FROM medical_records mr
                  WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $diagnosis_sql .= " AND EXISTS (
                        SELECT 1 FROM patients p 
                        WHERE p.id = mr.patient_id 
                        AND p.address LIKE ?
                      )";
}

$diagnosis_sql .= " GROUP BY mr.diagnosis, mr.icd_code, DATE_FORMAT(mr.created_at, '%Y-%m-%d')
                    ORDER BY date, count DESC";

if($stmt = mysqli_prepare($conn, $diagnosis_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        $diagnosis_trends[] = $row;
    }
}

// Get severity distribution
$severity_distribution = [];
$severity_sql = "SELECT 
                    mr.severity,
                    COUNT(*) as count
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
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        $severity_distribution[] = $row;
    }
}

// Get hospitalization trends
$hospitalization_trends = [];
$hospitalization_sql = "SELECT 
                         mr.hospitalization_status,
                         COUNT(*) as count,
                         DATE_FORMAT(mr.created_at, '%Y-%m-%d') as date
                       FROM medical_records mr
                       WHERE mr.created_at BETWEEN ? AND ?";

if(!empty($region)) {
    $hospitalization_sql .= " AND EXISTS (
                             SELECT 1 FROM patients p 
                             WHERE p.id = mr.patient_id 
                             AND p.address LIKE ?
                           )";
}

$hospitalization_sql .= " GROUP BY mr.hospitalization_status, DATE_FORMAT(mr.created_at, '%Y-%m-%d')
                         ORDER BY date, count DESC";

if($stmt = mysqli_prepare($conn, $hospitalization_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        $hospitalization_trends[] = $row;
    }
}

// Get travel history hotspots
$travel_hotspots = [];
$travel_sql = "SELECT 
                 mr.travel_history,
                 COUNT(*) as count
               FROM medical_records mr
               WHERE mr.created_at BETWEEN ? AND ?
               AND mr.travel_history IS NOT NULL
               AND mr.travel_history != ''";

if(!empty($region)) {
    $travel_sql .= " AND EXISTS (
                     SELECT 1 FROM patients p 
                     WHERE p.id = mr.patient_id 
                     AND p.address LIKE ?
                   )";
}

$travel_sql .= " GROUP BY mr.travel_history
                 ORDER BY count DESC
                 LIMIT 10";

if($stmt = mysqli_prepare($conn, $travel_sql)) {
    if(!empty($region)) {
        $region_param = "%$region%";
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $region_param);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        $travel_hotspots[] = $row;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epidemic Trend Analysis - MediChain</title>
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
                    <h2>Epidemic Trend Analysis</h2>
                    <div>
                        <a href="export_epidemic_data.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&region=<?php echo $region; ?>" class="btn btn-success">
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

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-virus"></i> Total Cases
                                </h5>
                                <h2 class="mb-0"><?php echo count($diagnosis_trends); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-hospital"></i> Hospitalized
                                </h5>
                                <h2 class="mb-0">
                                    <?php 
                                    $hospitalized = 0;
                                    foreach($hospitalization_trends as $h) {
                                        if($h['hospitalization_status'] == 'hospitalized' || $h['hospitalization_status'] == 'icu') {
                                            $hospitalized += $h['count'];
                                        }
                                    }
                                    echo $hospitalized;
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-activity"></i> Critical Cases
                                </h5>
                                <h2 class="mb-0">
                                    <?php 
                                    $critical = 0;
                                    foreach($severity_distribution as $s) {
                                        if($s['severity'] == 'critical') {
                                            $critical = $s['count'];
                                            break;
                                        }
                                    }
                                    echo $critical;
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-globe"></i> Travel Hotspots
                                </h5>
                                <h2 class="mb-0"><?php echo count($travel_hotspots); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnosis Trends Chart -->
                <div class="card">
                    <h5 class="card-title">
                        <i class="bi bi-graph-up"></i> Diagnosis Trends
                    </h5>
                    <div class="chart-container">
                        <canvas id="diagnosisTrendsChart"></canvas>
                    </div>
                </div>

                <!-- Severity Distribution -->
                <div class="row">
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
                    <div class="col-md-6">
                        <div class="card">
                            <h5 class="card-title">
                                <i class="bi bi-hospital"></i> Hospitalization Trends
                            </h5>
                            <div class="chart-container">
                                <canvas id="hospitalizationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Diagnoses Table -->
                <div class="card">
                    <h5 class="card-title">
                        <i class="bi bi-table"></i> Top Diagnoses
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Diagnosis</th>
                                    <th>ICD-10 Code</th>
                                    <th>Count</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $diagnosis_counts = [];
                                foreach($diagnosis_trends as $d) {
                                    if(!isset($diagnosis_counts[$d['diagnosis']])) {
                                        $diagnosis_counts[$d['diagnosis']] = [
                                            'count' => 0,
                                            'icd_code' => $d['icd_code']
                                        ];
                                    }
                                    $diagnosis_counts[$d['diagnosis']]['count'] += $d['count'];
                                }
                                
                                arsort($diagnosis_counts);
                                $top_diagnoses = array_slice($diagnosis_counts, 0, 10, true);
                                
                                foreach($top_diagnoses as $diagnosis => $data):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($diagnosis); ?></td>
                                    <td><?php echo htmlspecialchars($data['icd_code']); ?></td>
                                    <td><?php echo $data['count']; ?></td>
                                    <td>
                                        <span class="trend-indicator trend-up">
                                            <i class="bi bi-arrow-up"></i> Increasing
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Travel Hotspots -->
                <div class="card">
                    <h5 class="card-title">
                        <i class="bi bi-globe"></i> Travel Hotspots
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Cases</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($travel_hotspots as $hotspot): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($hotspot['travel_history']); ?></td>
                                    <td><?php echo $hotspot['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare data for charts
        const diagnosisData = <?php echo json_encode($diagnosis_trends); ?>;
        const severityData = <?php echo json_encode($severity_distribution); ?>;
        const hospitalizationData = <?php echo json_encode($hospitalization_trends); ?>;

        // Process diagnosis data for chart
        const diagnosisDates = [...new Set(diagnosisData.map(item => item.date))].sort();
        const diagnosisLabels = [...new Set(diagnosisData.map(item => item.diagnosis))];
        
        const diagnosisDatasets = diagnosisLabels.map(diagnosis => {
            const data = diagnosisDates.map(date => {
                const match = diagnosisData.find(item => item.date === date && item.diagnosis === diagnosis);
                return match ? match.count : 0;
            });
            
            return {
                label: diagnosis,
                data: data,
                borderWidth: 2,
                fill: false
            };
        });

        // Diagnosis Trends Chart
        const diagnosisTrendsChart = new Chart(
            document.getElementById('diagnosisTrendsChart'),
            {
                type: 'line',
                data: {
                    labels: diagnosisDates,
                    datasets: diagnosisDatasets
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

        // Severity Distribution Chart
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
                    maintainAspectRatio: false
                }
            }
        );

        // Hospitalization Trends Chart
        const hospitalizationDates = [...new Set(hospitalizationData.map(item => item.date))].sort();
        const hospitalizationLabels = [...new Set(hospitalizationData.map(item => item.hospitalization_status))];
        
        const hospitalizationDatasets = hospitalizationLabels.map(status => {
            const data = hospitalizationDates.map(date => {
                const match = hospitalizationData.find(item => item.date === date && item.hospitalization_status === status);
                return match ? match.count : 0;
            });
            
            return {
                label: status || 'Unknown',
                data: data,
                borderWidth: 2,
                fill: false
            };
        });

        const hospitalizationChart = new Chart(
            document.getElementById('hospitalizationChart'),
            {
                type: 'line',
                data: {
                    labels: hospitalizationDates,
                    datasets: hospitalizationDatasets
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
    </script>
</body>
</html> 