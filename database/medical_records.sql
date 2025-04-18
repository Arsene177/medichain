-- Create symptoms table
CREATE TABLE IF NOT EXISTS symptoms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create diagnoses table
CREATE TABLE IF NOT EXISTS diagnoses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create treatments table
CREATE TABLE IF NOT EXISTS treatments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create medical_records table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date DATE NOT NULL,
    symptoms TEXT NOT NULL,
    diagnosis_id INT,
    treatment_id INT,
    notes TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(user_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id),
    FOREIGN KEY (diagnosis_id) REFERENCES diagnoses(id),
    FOREIGN KEY (treatment_id) REFERENCES treatments(id)
);

-- Insert sample symptoms
INSERT INTO symptoms (name, description) VALUES
('Fever', 'Elevated body temperature above normal range'),
('Headache', 'Pain in the head or neck region'),
('Cough', 'Expulsion of air from the lungs with a sudden sharp sound'),
('Fatigue', 'Extreme tiredness resulting from mental or physical exertion'),
('Nausea', 'Feeling of sickness with an inclination to vomit'),
('Dizziness', 'Sensation of spinning or loss of balance'),
('Shortness of breath', 'Difficulty in breathing'),
('Chest pain', 'Pain in the chest area'),
('Joint pain', 'Pain in the joints'),
('Muscle pain', 'Pain in the muscles');

-- Insert sample diagnoses
INSERT INTO diagnoses (name, description) VALUES
('Common Cold', 'Viral infection of the upper respiratory tract'),
('Influenza', 'Viral infection that attacks the respiratory system'),
('Hypertension', 'High blood pressure'),
('Diabetes', 'Metabolic disorder characterized by high blood sugar'),
('Asthma', 'Chronic respiratory condition'),
('Anemia', 'Deficiency of red blood cells or hemoglobin'),
('Arthritis', 'Inflammation of one or more joints'),
('Gastritis', 'Inflammation of the stomach lining'),
('Bronchitis', 'Inflammation of the bronchial tubes'),
('Migraine', 'Recurrent headache disorder');

-- Insert sample treatments
INSERT INTO treatments (name, description) VALUES
('Rest', 'Taking time to relax and recover'),
('Antibiotics', 'Medication to treat bacterial infections'),
('Pain relievers', 'Medication to reduce pain'),
('Antihistamines', 'Medication to treat allergies'),
('Bronchodilators', 'Medication to open up airways'),
('Insulin', 'Hormone to regulate blood sugar'),
('Physical therapy', 'Treatment to improve physical function'),
('Diet modification', 'Changes to eating habits'),
('Exercise', 'Physical activity for health improvement'),
('Surgery', 'Medical procedure to treat conditions'); 