<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../index.php");
    exit;
}

$success = $error = "";
$users = array();

// Get all users with their roles
$users_sql = "SELECT u.*, 
              CASE 
                WHEN u.role = 'doctor' THEN d.full_name 
                WHEN u.role = 'patient' THEN p.full_name 
                ELSE u.username 
              END as display_name,
              CASE 
                WHEN u.role = 'doctor' THEN d.hospital 
                ELSE NULL 
              END as additional_info
              FROM users u 
              LEFT JOIN doctors d ON u.id = d.user_id 
              LEFT JOIN patients p ON u.id = p.user_id 
              ORDER BY u.created_at DESC";

if($stmt = mysqli_prepare($conn, $users_sql)){
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $users[] = $row;
    }
}

// Handle user status update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["user_id"]) && isset($_POST["action"])){
    $user_id = $_POST["user_id"];
    $action = $_POST["action"];
    
    if($action == "delete"){
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Delete role-specific data first
            $role_sql = "SELECT role FROM users WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $role_sql)){
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                
                if($user["role"] == "doctor"){
                    mysqli_query($conn, "DELETE FROM doctors WHERE user_id = $user_id");
                } elseif($user["role"] == "patient"){
                    mysqli_query($conn, "DELETE FROM patients WHERE user_id = $user_id");
                }
            }
            
            // Delete user
            mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
            
            mysqli_commit($conn);
            $success = "User deleted successfully.";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .user-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .user-card:hover {
            transform: translateY(-5px);
        }
        .role-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>User Management</h2>
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

                <!-- User Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <p class="card-text display-4"><?php echo count($users); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Doctors</h5>
                                <p class="card-text display-4">
                                    <?php echo count(array_filter($users, function($user) { return $user["role"] == "doctor"; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Patients</h5>
                                <p class="card-text display-4">
                                    <?php echo count(array_filter($users, function($user) { return $user["role"] == "patient"; })); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users List -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">All Users</h5>
                        <?php if(empty($users)): ?>
                            <p class="text-muted">No users found.</p>
                        <?php else: ?>
                            <?php foreach($users as $user): ?>
                                <div class="card user-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($user["display_name"]); ?>
                                                </h5>
                                                <p class="card-text text-muted mb-1">
                                                    Username: <?php echo htmlspecialchars($user["username"]); ?>
                                                </p>
                                                <?php if($user["additional_info"]): ?>
                                                    <p class="card-text text-muted mb-1">
                                                        <?php echo htmlspecialchars($user["additional_info"]); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        Joined: <?php echo date("F j, Y", strtotime($user["created_at"])); ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php 
                                                    echo $user["role"] == "admin" ? "danger" : 
                                                        ($user["role"] == "doctor" ? "primary" : "success"); 
                                                ?> role-badge">
                                                    <?php echo ucfirst($user["role"]); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-trash"></i> Delete User
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 