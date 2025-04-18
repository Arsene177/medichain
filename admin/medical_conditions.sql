-- Create medical_conditions table
CREATE TABLE IF NOT EXISTS medical_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    is_contagious BOOLEAN DEFAULT FALSE,
    incubation_period VARCHAR(100),
    symptoms TEXT,
    treatment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create medical_records table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    condition_id INT NOT NULL,
    diagnosis_date DATE NOT NULL,
    severity ENUM('mild', 'moderate', 'severe', 'critical') NOT NULL,
    status ENUM('active', 'resolved', 'monitoring') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (condition_id) REFERENCES medical_conditions(id)
);

-- Insert some sample medical conditions
INSERT INTO medical_conditions (name, category, description, is_contagious, incubation_period, symptoms, treatment) VALUES
('Hypertension', 'Cardiovascular', 'High blood pressure condition', FALSE, 'N/A', 'Headaches, shortness of breath, nosebleeds', 'Lifestyle changes, medication'),
('Diabetes Type 2', 'Endocrine', 'Chronic condition affecting blood sugar regulation', FALSE, 'N/A', 'Increased thirst, frequent urination, hunger, fatigue', 'Diet control, medication, exercise'),
('COVID-19', 'Infectious Disease', 'Coronavirus disease 2019', TRUE, '2-14 days', 'Fever, cough, shortness of breath, loss of taste/smell', 'Rest, isolation, medical treatment'),
('Asthma', 'Respiratory', 'Chronic respiratory condition', FALSE, 'N/A', 'Wheezing, shortness of breath, chest tightness', 'Inhalers, medication, avoiding triggers'),
('Malaria', 'Infectious Disease', 'Mosquito-borne disease', TRUE, '7-30 days', 'Fever, chills, headache, muscle pain', 'Antimalarial medication, rest, fluids');

-- Insert some sample medical records
INSERT INTO medical_records (patient_id, doctor_id, condition_id, diagnosis_date, severity, status, notes) VALUES
(1, 1, 1, CURDATE(), 'mild', 'active', 'Regular monitoring required'),
(1, 1, 2, CURDATE(), 'moderate', 'monitoring', 'Blood sugar levels need monitoring'),
(2, 1, 3, CURDATE(), 'severe', 'active', 'Isolation required'),
(2, 1, 4, CURDATE(), 'mild', 'resolved', 'Symptoms under control'),
(3, 1, 5, CURDATE(), 'critical', 'active', 'Immediate treatment required'); 