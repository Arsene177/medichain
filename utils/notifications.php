<?php
require_once "../config/database.php";

/**
 * Send an email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool Whether the email was sent successfully
 */
function send_email($to, $subject, $message) {
    // TODO: Implement email sending functionality
    // This would require integrating with an email service (e.g., PHPMailer, SendGrid, etc.)
    // For now, we'll just log the attempt
    
    $log_file = __DIR__ . "/../logs/email.log";
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[$timestamp] To: $to\nSubject: $subject\nMessage: $message\n\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    return true;
}

/**
 * Send appointment confirmation email
 * 
 * @param int $appointment_id The ID of the appointment
 * @return bool Whether the email was sent successfully
 */
function send_appointment_confirmation($appointment_id) {
    global $conn;
    
    // Get appointment details
    $sql = "SELECT a.*, p.full_name as patient_name, p.email as patient_email,
                   d.full_name as doctor_name, d.hospital_affiliation
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $appointment = mysqli_fetch_assoc($result);
        
        if($appointment){
            $subject = "Appointment Confirmation - MediChain Cameroon";
            $message = "Dear " . $appointment["patient_name"] . ",\n\n";
            $message .= "Your appointment has been confirmed with the following details:\n\n";
            $message .= "Doctor: " . $appointment["doctor_name"] . "\n";
            $message .= "Hospital: " . $appointment["hospital_affiliation"] . "\n";
            $message .= "Date: " . date("F j, Y", strtotime($appointment["appointment_date"])) . "\n";
            $message .= "Time: " . date("g:i A", strtotime($appointment["appointment_time"])) . "\n\n";
            $message .= "Please arrive 15 minutes before your scheduled appointment time.\n\n";
            $message .= "If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n\n";
            $message .= "Best regards,\nMediChain Cameroon Team";
            
            return send_email($appointment["patient_email"], $subject, $message);
        }
    }
    
    return false;
}

/**
 * Send appointment reminder email
 * 
 * @param int $appointment_id The ID of the appointment
 * @return bool Whether the email was sent successfully
 */
function send_appointment_reminder($appointment_id) {
    global $conn;
    
    // Get appointment details
    $sql = "SELECT a.*, p.full_name as patient_name, p.email as patient_email,
                   d.full_name as doctor_name, d.hospital_affiliation
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $appointment = mysqli_fetch_assoc($result);
        
        if($appointment){
            $subject = "Appointment Reminder - MediChain Cameroon";
            $message = "Dear " . $appointment["patient_name"] . ",\n\n";
            $message .= "This is a reminder for your upcoming appointment:\n\n";
            $message .= "Doctor: " . $appointment["doctor_name"] . "\n";
            $message .= "Hospital: " . $appointment["hospital_affiliation"] . "\n";
            $message .= "Date: " . date("F j, Y", strtotime($appointment["appointment_date"])) . "\n";
            $message .= "Time: " . date("g:i A", strtotime($appointment["appointment_time"])) . "\n\n";
            $message .= "Please arrive 15 minutes before your scheduled appointment time.\n\n";
            $message .= "If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n\n";
            $message .= "Best regards,\nMediChain Cameroon Team";
            
            return send_email($appointment["patient_email"], $subject, $message);
        }
    }
    
    return false;
}

/**
 * Send waiting list notification email
 * 
 * @param int $waiting_list_id The ID of the waiting list entry
 * @return bool Whether the email was sent successfully
 */
function send_waiting_list_notification($waiting_list_id) {
    global $conn;
    
    // Get waiting list details
    $sql = "SELECT w.*, p.full_name as patient_name, p.email as patient_email,
                   d.full_name as doctor_name, d.hospital_affiliation
            FROM waiting_list w
            JOIN patients p ON w.patient_id = p.id
            JOIN doctors d ON w.doctor_id = d.id
            WHERE w.id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $waiting_list_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $waiting = mysqli_fetch_assoc($result);
        
        if($waiting){
            $subject = "Appointment Slot Available - MediChain Cameroon";
            $message = "Dear " . $waiting["patient_name"] . ",\n\n";
            $message .= "A slot has become available for your preferred appointment time:\n\n";
            $message .= "Doctor: " . $waiting["doctor_name"] . "\n";
            $message .= "Hospital: " . $waiting["hospital_affiliation"] . "\n";
            $message .= "Preferred Date: " . date("F j, Y", strtotime($waiting["preferred_date"])) . "\n";
            $message .= "Preferred Time: " . date("g:i A", strtotime($waiting["preferred_time"])) . "\n\n";
            $message .= "Please log in to your account to schedule the appointment:\n";
            $message .= "http://your-domain.com/patient/schedule_appointment.php\n\n";
            $message .= "This slot will be held for 24 hours.\n\n";
            $message .= "Best regards,\nMediChain Cameroon Team";
            
            return send_email($waiting["patient_email"], $subject, $message);
        }
    }
    
    return false;
}

/**
 * Send appointment cancellation email
 * 
 * @param int $appointment_id The ID of the appointment
 * @return bool Whether the email was sent successfully
 */
function send_appointment_cancellation($appointment_id) {
    global $conn;
    
    // Get appointment details
    $sql = "SELECT a.*, p.full_name as patient_name, p.email as patient_email,
                   d.full_name as doctor_name, d.hospital_affiliation
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $appointment = mysqli_fetch_assoc($result);
        
        if($appointment){
            $subject = "Appointment Cancelled - MediChain Cameroon";
            $message = "Dear " . $appointment["patient_name"] . ",\n\n";
            $message .= "Your appointment has been cancelled:\n\n";
            $message .= "Doctor: " . $appointment["doctor_name"] . "\n";
            $message .= "Hospital: " . $appointment["hospital_affiliation"] . "\n";
            $message .= "Date: " . date("F j, Y", strtotime($appointment["appointment_date"])) . "\n";
            $message .= "Time: " . date("g:i A", strtotime($appointment["appointment_time"])) . "\n\n";
            $message .= "If you would like to schedule a new appointment, please visit our website:\n";
            $message .= "http://your-domain.com/patient/schedule_appointment.php\n\n";
            $message .= "Best regards,\nMediChain Cameroon Team";
            
            return send_email($appointment["patient_email"], $subject, $message);
        }
    }
    
    return false;
} 