CREATE DATABASE IF NOT EXISTS medical_system_db;
USE medical_system_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('patient', 'medical_staff', 'admin') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Medical Centers Table
CREATE TABLE IF NOT EXISTS medical_centers (
    center_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,  
    longitude DECIMAL(11, 8) NOT NULL, 
    contact_number VARCHAR(20)
);

-- 3. Doctors Table
CREATE TABLE IF NOT EXISTS doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, 
    center_id INT NOT NULL, 
    specialization VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (center_id) REFERENCES medical_centers(center_id) ON DELETE CASCADE
);

-- 4. Availability Table
CREATE TABLE IF NOT EXISTS availability (
    availability_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_booked BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
);

-- 5. Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL, 
    doctor_id INT NOT NULL,
    availability_id INT NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    FOREIGN KEY (availability_id) REFERENCES availability(availability_id)
);

-- 6. Contact Messages
CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(150),
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'resolved') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- INSERT SAMPLE DATA --

-- Note: Passwords are all 'password123' (hashed using standard PHP password_hash)
INSERT IGNORE INTO users (user_id, email, password_hash, role, first_name, last_name) VALUES
(1, 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Super', 'Admin'),
(2, 'dr.smith@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medical_staff', 'John', 'Smith'),
(3, 'patient@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Jane', 'Doe');

INSERT IGNORE INTO medical_centers (center_id, name, address, city, latitude, longitude) VALUES
(1, 'National Hospital Kandy', 'William Gopallawa Mawatha', 'Kandy', 7.288200, 80.633500),
(2, 'Asiri Hospital Kandy', 'Peradeniya Road', 'Kandy', 7.276400, 80.612700),
(3, 'Suwasewana Hospital', 'Yatinuwara Veediya', 'Kandy', 7.291700, 80.637500),
(4, 'Lakeside Adventist Hospital', 'Wariyapola Sri Sumangala Mawatha', 'Kandy', 7.291300, 80.644100);

INSERT IGNORE INTO doctors (doctor_id, user_id, center_id, specialization) VALUES
(1, 2, 1, 'Cardiology');

INSERT IGNORE INTO availability (availability_id, doctor_id, date, start_time, end_time) VALUES
(1, 1, CURDATE(), '09:00:00', '10:00:00'),
(2, 1, CURDATE(), '10:00:00', '11:00:00');
