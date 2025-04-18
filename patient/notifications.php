<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a patient
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient"){
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION["id"];
$success_message = $error_message = "";

// Mark notification as read if requested
if(isset($_GET["mark_read"]) && is_numeric($_GET["mark_read"])){
    $notification_id = intval($_GET["mark_read"]);
    
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
    
    if($update_stmt = mysqli_prepare($conn, $update_sql)){
        mysqli_stmt_bind_param($update_stmt, "ii", $notification_id, $user_id);
        
        if(mysqli_stmt_execute($update_stmt)){
            $success_message = "Notification marked as read.";
        } else {
            $error_message = "Error updating notification: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    }
}

// Mark all notifications as read if requested
if(isset($_GET["mark_all_read"]) && $_GET["mark_all_read"] == 1){
    $update_all_sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    
    if($update_all_stmt = mysqli_prepare($conn, $update_all_sql)){
        mysqli_stmt_bind_param($update_all_stmt, "i", $user_id);
        
        if(mysqli_stmt_execute($update_all_stmt)){
            $success_message = "All notifications marked as read.";
        } else {
            $error_message = "Error updating notifications: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_all_stmt);
    }
}

// Get all notifications for this user
$notifications_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$notifications_stmt = mysqli_prepare($conn, $notifications_sql);
mysqli_stmt_bind_param($notifications_stmt, "i", $user_id);
mysqli_stmt_execute($notifications_stmt);
$notifications_result = mysqli_stmt_get_result($notifications_stmt);
$notifications = [];
while($row = mysqli_fetch_assoc($notifications_result)){
    $notifications[] = $row;
}

// Get unread count
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$unread_stmt = mysqli_prepare($conn, $unread_sql);
mysqli_stmt_bind_param($unread_stmt, "i", $user_id);
mysqli_stmt_execute($unread_stmt);
$unread_result = mysqli_stmt_get_result($unread_stmt);
$unread_row = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_row['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - MediChain Cameroon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
        }
        .notifications-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin: 2rem auto;
            max-width: 1000px;
        }
        .notification-item {
            border-left: 4px solid #0083B0;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            padding: 1rem;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .notification-item.unread {
            border-left-color: #dc3545;
            background-color: #fff;
        }
        .notification-item.appointment {
            border-left-color: #28a745;
        }
        .notification-item.record {
            border-left-color: #17a2b8;
        }
        .notification-item.system {
            border-left-color: #6c757d;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .nav-link {
            color: #0083B0;
        }
        .nav-link:hover {
            color: #006d94;
        }
        .nav-link.active {
            color: #006d94;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="notifications-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Notifications <?php if($unread_count > 0): ?><span class="badge bg-danger"><?php echo $unread_count; ?></span><?php endif; ?></h1>
                <div>
                    <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav nav-pills mb-4">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link" href="appointments.php">Appointments</a>
                <a class="nav-link active" href="notifications.php">Notifications</a>
                <a class="nav-link" href="medical_records.php">Medical Records</a>
            </nav>

            <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
                
            <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Your Notifications</h5>
                <?php if($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-sm btn-outline-primary">Mark All as Read</a>
                <?php endif; ?>
            </div>

            <?php if(empty($notifications)): ?>
            <div class="alert alert-info">
                You don't have any notifications.
            </div>
            <?php else: ?>
            <div class="list-group">
                <?php foreach($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                            <small class="notification-time">
                                <?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?>
                            </small>
                        </div>
                        <?php if(!$notification['is_read']): ?>
                        <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-check2"></i> Mark as Read
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 