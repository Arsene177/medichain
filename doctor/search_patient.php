<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor"){
    header("location: ../index.php");
    exit;
}

$doctor_id = $_SESSION["id"];
$search_results = array();
$search_query = "";
$error = "";
$debug_info = "";

// Handle search
if($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["q"])){
    $search_query = trim($_GET["q"]);
    
    if(!empty($search_query)){
        // First, try an exact match for medical_record_id
        $exact_sql = "SELECT p.*, u.username 
                      FROM patients p 
                      JOIN users u ON p.user_id = u.id 
                      WHERE p.medical_record_id = ?";
        
        if($stmt = mysqli_prepare($conn, $exact_sql)){
            mysqli_stmt_bind_param($stmt, "s", $search_query);
            
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                if(mysqli_num_rows($result) > 0) {
                    // Found an exact match
                    while($row = mysqli_fetch_assoc($result)){
                        $search_results[] = $row;
                    }
                } else {
                    // If no exact match, try a broader search
                    $search_sql = "SELECT p.*, u.username 
                                  FROM patients p 
                                  JOIN users u ON p.user_id = u.id 
                                  WHERE p.medical_record_id LIKE ? 
                                  OR p.full_name LIKE ? 
                                  OR p.phone_number LIKE ? 
                                  OR p.email LIKE ?
                                  ORDER BY p.full_name";
                    
                    if($stmt = mysqli_prepare($conn, $search_sql)){
                        $search_param = "%{$search_query}%";
                        mysqli_stmt_bind_param($stmt, "ssss", 
                            $search_param,
                            $search_param,
                            $search_param,
                            $search_param
                        );
                        
                        if(mysqli_stmt_execute($stmt)){
                            $result = mysqli_stmt_get_result($stmt);
                            while($row = mysqli_fetch_assoc($result)){
                                $search_results[] = $row;
                            }
                        } else {
                            $error = "Error performing search: " . mysqli_error($conn);
                        }
                    }
                }
            } else {
                $error = "Error executing query: " . mysqli_error($conn);
            }
        } else {
            $error = "Error preparing statement: " . mysqli_error($conn);
        }
        
        // Debug information
        $debug_info = "Search query: " . $search_query . "<br>";
        $debug_info .= "Number of results: " . count($search_results) . "<br>";
        
        // Check if the database connection is working
        $test_sql = "SELECT COUNT(*) as count FROM patients";
        $test_result = mysqli_query($conn, $test_sql);
        if($test_result) {
            $row = mysqli_fetch_assoc($test_result);
            $debug_info .= "Total patients in database: " . $row['count'] . "<br>";
        } else {
            $debug_info .= "Database error: " . mysqli_error($conn) . "<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Patient - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .search-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .search-card:hover {
            transform: translateY(-5px);
        }
        .patient-info {
            font-size: 0.9rem;
            color: #666;
        }
        .debug-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Search Patient</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" 
                                           placeholder="Search by name, medical record ID, or phone number" 
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Search Results -->
                <?php if(!empty($search_query)): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Search Results</h5>
                            <?php if(empty($search_results)): ?>
                                <p class="text-muted">No patients found matching your search.</p>
                            <?php else: ?>
                                <?php foreach($search_results as $patient): ?>
                                    <div class="card search-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h5 class="card-title mb-1">
                                                        <?php echo htmlspecialchars($patient["full_name"]); ?>
                                                    </h5>
                                                    <p class="patient-info mb-1">
                                                        Medical Record ID: <?php echo htmlspecialchars($patient["medical_record_id"]); ?>
                                                    </p>
                                                    <p class="patient-info mb-1">
                                                        Phone: <?php echo htmlspecialchars($patient["phone_number"]); ?> |
                                                        Email: <?php echo htmlspecialchars($patient["email"]); ?>
                                                    </p>
                                                    <p class="patient-info mb-0">
                                                        Gender: <?php echo $patient["gender"] == "M" ? "Male" : ($patient["gender"] == "F" ? "Female" : "Other"); ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <a href="view_patient.php?id=<?php echo $patient["id"]; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Debug Information (only visible to admins) -->
                            <?php if(isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                                <div class="debug-info mt-4">
                                    <h6>Debug Information:</h6>
                                    <?php echo $debug_info; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 