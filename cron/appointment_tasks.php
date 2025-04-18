<?php
require_once "../config/database.php";
require_once "../utils/notifications.php";

// Set timezone
date_default_timezone_set('Africa/Douala');

/**
 * Send reminders for upcoming appointments
 */
function send_appointment_reminders() {
    global $conn;
    
    // Get appointments for tomorrow
    $tomorrow = date("Y-m-d", strtotime("+1 day"));
    $sql = "SELECT id FROM appointments 
            WHERE appointment_date = ? AND status = 'scheduled'";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $tomorrow);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while($row = mysqli_fetch_assoc($result)){
            send_appointment_reminder($row["id"]);
        }
    }
}

/**
 * Check for available slots and notify waiting list patients
 */
function check_waiting_list() {
    global $conn;
    
    // Get all waiting list entries
    $sql = "SELECT w.*, d.id as doctor_id 
            FROM waiting_list w 
            JOIN doctors d ON w.doctor_id = d.id 
            WHERE w.status = 'waiting'";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while($waiting = mysqli_fetch_assoc($result)){
            // Check if there are any available slots for the preferred date
            $check_sql = "SELECT COUNT(*) as count FROM appointments 
                         WHERE doctor_id = ? 
                         AND appointment_date = ? 
                         AND status != 'cancelled'";
            
            if($stmt = mysqli_prepare($conn, $check_sql)){
                mysqli_stmt_bind_param($stmt, "is", 
                    $waiting["doctor_id"], 
                    $waiting["preferred_date"]
                );
                mysqli_stmt_execute($stmt);
                $slot_result = mysqli_stmt_get_result($stmt);
                $slot_count = mysqli_fetch_assoc($slot_result)["count"];
                
                // If there are fewer appointments than the maximum allowed
                if($slot_count < 16) { // Assuming 8 hours with 30-minute slots
                    // Update waiting list status
                    $update_sql = "UPDATE waiting_list SET status = 'notified' WHERE id = ?";
                    if($stmt = mysqli_prepare($conn, $update_sql)){
                        mysqli_stmt_bind_param($stmt, "i", $waiting["id"]);
                        mysqli_stmt_execute($stmt);
                        
                        // Send notification
                        send_waiting_list_notification($waiting["id"]);
                    }
                }
            }
        }
    }
}

/**
 * Clean up old waiting list entries
 */
function cleanup_waiting_list() {
    global $conn;
    
    // Remove waiting list entries older than 30 days
    $thirty_days_ago = date("Y-m-d", strtotime("-30 days"));
    $sql = "DELETE FROM waiting_list 
            WHERE created_at < ? AND status = 'waiting'";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $thirty_days_ago);
        mysqli_stmt_execute($stmt);
    }
}

// Execute tasks
try {
    send_appointment_reminders();
    check_waiting_list();
    cleanup_waiting_list();
    
    // Log successful execution
    $log_file = __DIR__ . "/../logs/cron.log";
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[$timestamp] Appointment tasks completed successfully\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
} catch (Exception $e) {
    // Log error
    $log_file = __DIR__ . "/../logs/cron.log";
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[$timestamp] Error in appointment tasks: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
} 