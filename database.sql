-- Drop existing tables in correct order
DROP TABLE IF EXISTS patient_symptoms;
DROP TABLE IF EXISTS condition_symptoms;
DROP TABLE IF EXISTS symptoms;
DROP TABLE IF EXISTS medical_conditions;
DROP TABLE IF EXISTS access_logs;
DROP TABLE IF EXISTS record_access;
DROP TABLE IF EXISTS waiting_list;
DROP TABLE IF EXISTS access_control;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS medical_records;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS users;

-- Create database
CREATE DATABASE IF NOT EXISTS medichain;
USE medichain;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'patient') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create doctors table
CREATE TABLE IF NOT EXISTS doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    hospital VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    license_number VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('M', 'F', 'Other') NOT NULL,
    address TEXT NOT NULL,
    emergency_contact VARCHAR(20) NOT NULL,
    id_type VARCHAR(50) NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    years_of_experience INT NOT NULL,
    qualification VARCHAR(100) NOT NULL,
    certification VARCHAR(100) NOT NULL,
    consultation_fee DECIMAL(10,2) NOT NULL,
    license_doc VARCHAR(255) NOT NULL,
    id_doc VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    rejection_reason TEXT,
    suspension_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('M', 'F', 'Other') NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    medical_record_id VARCHAR(20) UNIQUE NOT NULL,
    registered_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (registered_by) REFERENCES doctors(user_id)
);

-- Create medical_records table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    entry_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    entry_type ENUM('regular', 'emergency', 'prescription') NOT NULL,
    content TEXT NOT NULL,
    doctor_signature TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    condition_id INT,
    severity ENUM('mild', 'moderate', 'severe', 'critical') NOT NULL,
    onset_date DATE,
    recovery_date DATE,
    treatment_status ENUM('ongoing', 'completed', 'recurring') NOT NULL,
    transmission_method ENUM('airborne', 'contact', 'waterborne', 'foodborne', 'vector', 'none'),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (condition_id) REFERENCES medical_conditions(id)
);

-- Create appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id)
);

-- Create access_logs table
CREATE TABLE IF NOT EXISTS access_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT NOT NULL,
    accessed_by INT NOT NULL,
    access_type ENUM('view', 'edit') NOT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (record_id) REFERENCES medical_records(id),
    FOREIGN KEY (accessed_by) REFERENCES users(id)
);

-- Create record_access table
CREATE TABLE IF NOT EXISTS record_access (
    id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT NOT NULL,
    doctor_id INT NOT NULL,
    access_level ENUM('read', 'write') NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (record_id) REFERENCES medical_records(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (granted_by) REFERENCES users(id)
);

-- Create waiting_list table
CREATE TABLE IF NOT EXISTS waiting_list (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('waiting', 'notified', 'scheduled', 'expired') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Create access_control table
CREATE TABLE access_control (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    status ENUM('allowed', 'suspended') DEFAULT 'allowed',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id),
    UNIQUE KEY unique_access (patient_id, doctor_id)
);

-- Create medical_conditions table
CREATE TABLE IF NOT EXISTS medical_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category ENUM('infectious', 'chronic', 'acute', 'other') NOT NULL,
    description TEXT,
    is_contagious BOOLEAN DEFAULT FALSE,
    incubation_period VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create symptoms table
CREATE TABLE IF NOT EXISTS symptoms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create condition_symptoms table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS condition_symptoms (
    condition_id INT,
    symptom_id INT,
    PRIMARY KEY (condition_id, symptom_id),
    FOREIGN KEY (condition_id) REFERENCES medical_conditions(id),
    FOREIGN KEY (symptom_id) REFERENCES symptoms(id)
);

-- Create patient_symptoms table
CREATE TABLE IF NOT EXISTS patient_symptoms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT,
    symptom_id INT,
    severity ENUM('mild', 'moderate', 'severe', 'critical'),
    onset_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (record_id) REFERENCES medical_records(id),
    FOREIGN KEY (symptom_id) REFERENCES symptoms(id)
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('appointment', 'record', 'system', 'other') NOT NULL,
    reference_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('doctor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('doctor2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('patient1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient'),
('patient2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient');

INSERT INTO doctors (user_id, full_name, specialization, hospital, phone_number, license_number, 
                    email, date_of_birth, gender, address, emergency_contact, id_type, id_number,
                    years_of_experience, qualification, certification, consultation_fee, 
                    license_doc, id_doc, status) 
VALUES 
(2, 'Dr. John Smith', 'Cardiologist', 'Central Hospital', '+237 233 42 11 11', 'MD123456',
 'john.smith@example.com', '1980-01-01', 'M', '123 Medical Street, Yaounde', '+237 233 42 11 12',
 'National ID', 'NID123456', 15, 'MD, Cardiology', 'Board Certified Cardiologist', 5000.00,
 'license1.pdf', 'id1.pdf', 'pending'),
(3, 'Dr. Sarah Johnson', 'Pediatrician', 'Children\'s Hospital', '+237 233 42 11 13', 'MD123457',
 'sarah.johnson@example.com', '1985-01-01', 'F', '456 Health Avenue, Douala', '+237 233 42 11 14',
 'Passport', 'PASS123456', 10, 'MD, Pediatrics', 'Board Certified Pediatrician', 4500.00,
 'license2.pdf', 'id2.pdf', 'pending');

INSERT INTO patients (user_id, full_name, date_of_birth, gender, phone_number, email, address, medical_record_id, registered_by) VALUES
(4, 'Alice Johnson', '1990-05-15', 'F', '+237 111222333', 'alice@example.com', '123 Main St, Douala', 'MR001', 1),
(5, 'Bob Wilson', '1985-08-20', 'M', '+237 777888999', 'bob@example.com', '456 Park Ave, Yaounde', 'MR002', 2),
(6, 'Sarah Mbah', '1995-03-10', 'F', '+237 666777888', 'sarah@example.com', '789 Health St, Bamenda', 'MR003', 1);

INSERT INTO medical_records (patient_id, doctor_id, entry_type, content, doctor_signature) VALUES
(1, 1, 'regular', 'Initial checkup completed. Patient in good health.', 'Dr. John Doe, Cardiologist'),
(2, 2, 'prescription', 'Prescribed antibiotics for infection.', 'Dr. Jane Smith, Pediatrician');

INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, notes) VALUES
(1, 1, '2024-03-25', '09:00:00', 'scheduled', 'Regular checkup'),
(1, 1, '2024-03-26', '14:30:00', 'scheduled', 'Follow-up visit'),
(2, 2, '2024-03-27', '10:00:00', 'scheduled', 'Initial consultation');

INSERT INTO access_logs (record_id, accessed_by, access_type, ip_address) VALUES
(1, 2, 'view', '192.168.1.1'),
(2, 3, 'view', '192.168.1.2');

INSERT INTO record_access (record_id, doctor_id, access_level, granted_by) VALUES
(1, 2, 'read', 1),
(2, 1, 'read', 1);

INSERT INTO waiting_list (patient_id, doctor_id, preferred_date, preferred_time, reason) VALUES
(1, 2, '2024-03-25', '09:00:00', 'Consultation for new symptoms'),
(2, 1, '2024-03-26', '11:00:00', 'Follow-up checkup');

-- Insert sample users (including the new patient)
INSERT INTO users (id, username, password, role) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'john.doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
(3, 'jane.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
(4, 'alice.johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient'),
(5, 'bob.wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient'),
(6, 'sarah.mbah', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient');

-- Insert sample medical conditions
INSERT INTO medical_conditions (name, category, description, is_contagious, incubation_period) VALUES
('Malaria', 'infectious', 'A mosquito-borne infectious disease', TRUE, '7-30 days'),
('Typhoid Fever', 'infectious', 'A bacterial infection spread through contaminated food and water', TRUE, '6-30 days'),
('Hypertension', 'chronic', 'High blood pressure condition', FALSE, NULL),
('Diabetes', 'chronic', 'A group of metabolic disorders', FALSE, NULL),
('Pneumonia', 'acute', 'Lung inflammation caused by infection', TRUE, '1-3 days'),
('Appendicitis', 'acute', 'Inflammation of the appendix', FALSE, NULL),
('Common Cold', 'infectious', 'Viral infection of the upper respiratory tract', TRUE, '1-3 days'),
('Tuberculosis', 'infectious', 'Bacterial infection primarily affecting the lungs', TRUE, '2-12 weeks'),
('Asthma', 'chronic', 'Chronic respiratory condition', FALSE, NULL),
('Gastroenteritis', 'acute', 'Inflammation of the gastrointestinal tract', TRUE, '1-3 days');

-- Insert sample symptoms
INSERT INTO symptoms (name, description) VALUES
('Fever', 'Elevated body temperature'),
('Headache', 'Pain in the head'),
('Cough', 'Expulsion of air from lungs with sound'),
('Fatigue', 'Extreme tiredness'),
('Nausea', 'Feeling of sickness with inclination to vomit'),
('Vomiting', 'Forceful expulsion of stomach contents'),
('Diarrhea', 'Frequent loose or liquid bowel movements'),
('Body Pain', 'Generalized pain in the body'),
('Loss of Appetite', 'Reduced desire to eat'),
('Shortness of Breath', 'Difficulty in breathing'),
('Chest Pain', 'Pain in the chest area'),
('Joint Pain', 'Pain in the joints'),
('Skin Rash', 'Change in skin appearance'),
('Dizziness', 'Sensation of spinning or lightheadedness'),
('Sore Throat', 'Pain or irritation in the throat');

-- Link common symptoms to conditions
INSERT INTO condition_symptoms (condition_id, symptom_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), -- Malaria symptoms
(2, 1), (2, 2), (2, 4), (2, 5), (2, 6), (2, 7), -- Typhoid symptoms
(3, 2), (3, 4), (3, 10), (3, 11), -- Hypertension symptoms
(4, 4), (4, 9), (4, 10), -- Diabetes symptoms
(5, 1), (5, 3), (5, 10), (5, 11), -- Pneumonia symptoms
(6, 5), (6, 7), (6, 8), -- Appendicitis symptoms
(7, 1), (7, 2), (7, 3), (7, 14), (7, 15), -- Common Cold symptoms
(8, 1), (8, 3), (8, 4), (8, 9), -- Tuberculosis symptoms
(9, 3), (9, 10), (9, 12), -- Asthma symptoms
(10, 5), (10, 6), (10, 7), (10, 8); -- Gastroenteritis symptoms 