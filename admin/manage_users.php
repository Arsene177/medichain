<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../index.php");
    exit;
}

// Handle user actions
if(isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    switch($action) {
        case 'activate':
            // For doctors, update their status in doctors table
            $sql = "UPDATE doctors SET status = 'approved' WHERE user_id = ?";
            break;
        case 'deactivate':
            // For doctors, update their status in doctors table
            $sql = "UPDATE doctors SET status = 'suspended' WHERE user_id = ?";
            break;
        case 'suspend_doctor':
            // Update doctors table
            $sql = "UPDATE doctors SET status = 'suspended' WHERE user_id = ?";
            break;
        case 'reactivate_doctor':
            // Update doctors table
            $sql = "UPDATE doctors SET status = 'approved' WHERE user_id = ?";
            break;
        case 'suspend_user':
            // Get the user's role
            $role_sql = "SELECT role FROM users WHERE id = ?";
            $role_stmt = mysqli_prepare($conn, $role_sql);
            mysqli_stmt_bind_param($role_stmt, "i", $user_id);
            mysqli_stmt_execute($role_stmt);
            $role_result = mysqli_stmt_get_result($role_stmt);
            $user_role = mysqli_fetch_assoc($role_result)['role'];
            
            // Get suspension reason
            $suspension_reason = isset($_POST['suspension_reason']) ? $_POST['suspension_reason'] : '';
            
            if($user_role == 'doctor') {
                // Update doctors table
                $sql = "UPDATE doctors SET status = 'suspended', suspension_reason = ? WHERE user_id = ?";
            } else if($user_role == 'patient') {
                // For patients, we'll update their status in a new column in the users table
                // First, check if the column exists, if not, add it
                $check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
                if(mysqli_num_rows($check_column) == 0) {
                    mysqli_query($conn, "ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
                }
                
                $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
            }
            break;
        case 'reactivate_user':
            // Get the user's role
            $role_sql = "SELECT role FROM users WHERE id = ?";
            $role_stmt = mysqli_prepare($conn, $role_sql);
            mysqli_stmt_bind_param($role_stmt, "i", $user_id);
            mysqli_stmt_execute($role_stmt);
            $role_result = mysqli_stmt_get_result($role_stmt);
            $user_role = mysqli_fetch_assoc($role_result)['role'];
            
            if($user_role == 'doctor') {
                // Update doctors table
                $sql = "UPDATE doctors SET status = 'approved', suspension_reason = NULL WHERE user_id = ?";
            } else if($user_role == 'patient') {
                // Update users table
                $sql = "UPDATE users SET status = 'active' WHERE id = ?";
            }
            break;
        case 'delete':
            $sql = "DELETE FROM users WHERE id = ?";
            break;
        case 'reset_password':
            // Generate a random password
            $new_password = bin2hex(random_bytes(4)); // 8 characters
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            break;
        case 'change_role':
            $new_role = $_POST['new_role'];
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            break;
        case 'change_status':
            $new_status = $_POST['new_status'];
            
            // Get the user's role
            $role_sql = "SELECT role FROM users WHERE id = ?";
            $role_stmt = mysqli_prepare($conn, $role_sql);
            mysqli_stmt_bind_param($role_stmt, "i", $user_id);
            mysqli_stmt_execute($role_stmt);
            $role_result = mysqli_stmt_get_result($role_stmt);
            $user_role = mysqli_fetch_assoc($role_result)['role'];
            
            if($user_role == 'doctor') {
                // Update doctors table
                $sql = "UPDATE doctors SET status = ? WHERE user_id = ?";
            } else if($user_role == 'patient') {
                // For patients, we'll update their status in the users table
                // First, check if the column exists, if not, add it
                $check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
                if(mysqli_num_rows($check_column) == 0) {
                    mysqli_query($conn, "ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
                }
                
                $sql = "UPDATE users SET status = ? WHERE id = ?";
            }
            break;
    }

    if(isset($sql)) {
        $stmt = mysqli_prepare($conn, $sql);
        if($action == 'reset_password') {
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
        } elseif($action == 'change_role') {
            mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id);
        } elseif($action == 'suspend_user' && isset($suspension_reason) && $user_role == 'doctor') {
            mysqli_stmt_bind_param($stmt, "si", $suspension_reason, $user_id);
        } elseif($action == 'change_status') {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $user_id);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
        }
        
        if(mysqli_stmt_execute($stmt)) {
            if($action == 'reset_password') {
                $_SESSION['success_msg'] = "Password reset successful. New password: " . $new_password;
            } else {
                $_SESSION['success_msg'] = "Action completed successfully.";
            }
        } else {
            $_SESSION['error_msg'] = "Error performing action.";
        }
    }
    
    header("location: manage_users.php");
    exit;
}

// Get users with their roles and status
$users_sql = "SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.created_at,
    u.last_login,
    CASE 
        WHEN u.role = 'doctor' THEN d.full_name
        WHEN u.role = 'patient' THEN p.full_name
        ELSE NULL
    END as full_name,
    CASE 
        WHEN u.role = 'doctor' THEN d.hospital
        ELSE NULL
    END as hospital,
    CASE
        WHEN u.role = 'doctor' THEN d.status
        WHEN u.role = 'admin' THEN 'active'
        WHEN u.role = 'patient' THEN COALESCE(u.status, 'active')
        ELSE 'active'
    END as status,
    CASE
        WHEN u.role = 'doctor' THEN d.rejection_reason
        ELSE NULL
    END as suspension_reason
FROM users u
LEFT JOIN doctors d ON u.id = d.user_id
LEFT JOIN patients p ON u.id = p.user_id
ORDER BY u.created_at DESC";

$users_result = mysqli_query($conn, $users_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - MediChain</title>
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

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead {
            background-color: var(--primary-color);
            color: white;
        }

        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0 0.25rem;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .status-select {
            cursor: pointer;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 0.875rem;
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
        }
        
        .status-select option[value="pending"] {
            background-color: #ffc107;
            color: #000;
        }
        
        .status-select option[value="approved"], 
        .status-select option[value="active"] {
            background-color: #28a745;
            color: #fff;
        }
        
        .status-select option[value="rejected"] {
            background-color: #dc3545;
            color: #fff;
        }
        
        .status-select option[value="suspended"] {
            background-color: #fd7e14;
            color: #fff;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User Management</h2>
            <a href="add_user.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add New User
            </a>
        </div>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success_msg'];
                unset($_SESSION['success_msg']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error_msg'];
                unset($_SESSION['error_msg']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Hospital</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user['role'] == 'admin' ? 'danger' : 
                                        ($user['role'] == 'doctor' ? 'primary' : 'success'); 
                                ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['hospital'] ?? 'N/A'); ?></td>
                            <td>
                                <form method="POST" class="d-inline status-form" id="statusForm<?php echo $user['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="previous_status" value="<?php echo $user['status']; ?>">
                                    <select name="new_status" class="form-select form-select-sm status-select" 
                                            id="statusSelect<?php echo $user['id']; ?>"
                                            style="width: auto; display: inline-block; min-width: 120px;">
                                        <?php if($user['role'] == 'doctor'): ?>
                                            <option value="pending" <?php echo $user['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $user['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $user['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <?php else: ?>
                                            <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <?php endif; ?>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if($user['role'] == 'doctor'): ?>
                                        <?php if($user['status'] == 'active' || $user['status'] == 'approved'): ?>
                                            <button type="button" class="btn btn-warning btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#suspendModal<?php echo $user['id']; ?>"
                                                    title="Suspend User">
                                                <i class="bi bi-pause-circle"></i>
                                            </button>
                                        <?php elseif($user['status'] == 'suspended'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="reactivate_user">
                                                <button type="submit" class="btn btn-success btn-action" title="Reactivate User">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php elseif($user['role'] == 'patient'): ?>
                                        <?php if($user['status'] == 'active'): ?>
                                            <button type="button" class="btn btn-warning btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#suspendModal<?php echo $user['id']; ?>"
                                                    title="Suspend User">
                                                <i class="bi bi-pause-circle"></i>
                                            </button>
                                        <?php elseif($user['status'] == 'suspended'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="reactivate_user">
                                                <button type="submit" class="btn btn-success btn-action" title="Reactivate User">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <button type="submit" class="btn btn-info btn-action" title="Reset Password">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-primary btn-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#roleModal<?php echo $user['id']; ?>"
                                            title="Change Role">
                                        <i class="bi bi-person-gear"></i>
                                    </button>

                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger btn-action" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Role Change Modal -->
                                <div class="modal fade" id="roleModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Change User Role</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <div class="mb-3">
                                                        <label class="form-label">Select New Role</label>
                                                        <select name="new_role" class="form-select" required>
                                                            <option value="patient" <?php echo $user['role'] == 'patient' ? 'selected' : ''; ?>>Patient</option>
                                                            <option value="doctor" <?php echo $user['role'] == 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Change Role</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Suspend User Modal -->
                                <div class="modal fade" id="suspendModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Suspend User</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="suspend_user">
                                                    <div class="mb-3">
                                                        <label class="form-label">Reason for Suspension</label>
                                                        <textarea name="suspension_reason" class="form-control" rows="3" required></textarea>
                                                    </div>
                                                    <div class="alert alert-warning">
                                                        <i class="bi bi-exclamation-triangle"></i> 
                                                        This action will prevent the user from accessing their account until reactivated.
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-warning">Suspend User</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add event listener to all status dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select');
            
            statusSelects.forEach(select => {
                // Add visual feedback when changed
                select.addEventListener('change', function() {
                    const form = this.closest('form');
                    if (form) {
                        // Store the selected value in localStorage to maintain it after page refresh
                        const userId = this.id.replace('statusSelect', '');
                        const selectedValue = this.value;
                        localStorage.setItem('userStatus_' + userId, selectedValue);
                        
                        // Add a loading indicator
                        this.disabled = true;
                        this.style.opacity = '0.7';
                        
                        // Submit the form
                        form.submit();
                    }
                });
                
                // Style the select based on its value
                const updateSelectStyle = function() {
                    const value = select.value;
                    select.className = 'form-select form-select-sm status-select';
                    
                    if (value === 'pending') {
                        select.style.backgroundColor = '#fff3cd';
                        select.style.color = '#856404';
                        select.style.borderColor = '#ffeeba';
                    } else if (value === 'approved' || value === 'active') {
                        select.style.backgroundColor = '#d4edda';
                        select.style.color = '#155724';
                        select.style.borderColor = '#c3e6cb';
                    } else if (value === 'rejected') {
                        select.style.backgroundColor = '#f8d7da';
                        select.style.color = '#721c24';
                        select.style.borderColor = '#f5c6cb';
                    } else if (value === 'suspended') {
                        select.style.backgroundColor = '#fff3e0';
                        select.style.color = '#e65100';
                        select.style.borderColor = '#ffe0b2';
                    }
                };
                
                // Apply initial styling
                updateSelectStyle();
                
                // Check if there's a stored value for this user
                const userId = select.id.replace('statusSelect', '');
                const storedValue = localStorage.getItem('userStatus_' + userId);
                if (storedValue && storedValue !== select.value) {
                    select.value = storedValue;
                    updateSelectStyle();
                }
            });
        });
    </script>
</body>
</html> 