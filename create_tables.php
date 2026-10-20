<?php
// create_tables.php - COMPATIBLE WITH YOUR ACTUAL DATABASE
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'cemsindv';

// Create connection
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database created or already exists<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($database);

// Drop tables if they exist
$conn->query("DROP TABLE IF EXISTS registrations");
$conn->query("DROP TABLE IF EXISTS events");
$conn->query("DROP TABLE IF EXISTS users");

// Create users table (YOUR ACTUAL SCHEMA)
$sql = "CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    organization VARCHAR(100),
    status VARCHAR(50) DEFAULT 'active',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    die("Error creating users table: " . $conn->error);
}

// Create events table (YOUR ACTUAL SCHEMA)
$sql = "CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type VARCHAR(50) DEFAULT 'workshop',
    date DATE NOT NULL,
    reg_close_date DATE,
    time TIME,
    location VARCHAR(255),
    organizer_id INT,
    max_participants INT,
    fee VARCHAR(10) DEFAULT 'free',
    fee_amount DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    contact_person VARCHAR(100),
    contact_no VARCHAR(20),
    remarks TEXT,
    image_path VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "Events table created successfully<br>";
} else {
    die("Error creating events table: " . $conn->error);
}

// Create registrations table
$sql = "CREATE TABLE registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'registered',
    payment_method VARCHAR(50),
    comments TEXT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (event_id, user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "Registrations table created successfully<br>";
} else {
    die("Error creating registrations table: " . $conn->error);
}

// Insert sample users
$hashed_password = password_hash('password123', PASSWORD_DEFAULT);

$sql = "INSERT IGNORE INTO users (username, password, email, name, role, organization, phone) VALUES
('student', ?, 'student@example.com', 'Student User', 'student', 'University Malaysia Sabah', '012-3456789'),
('staff', ?, 'staff@example.com', 'Staff User', 'staff', 'University Malaysia Sabah', '012-9876543'),
('public', ?, 'public@example.com', 'Public User', 'public', 'Public Organization', '012-1112233')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $hashed_password, $hashed_password, $hashed_password);

if ($stmt->execute()) {
    echo "Sample users created successfully<br>";
} else {
    echo "Error creating sample users: " . $conn->error . "<br>";
}

$conn->close();

echo "<h2>✅ Database setup completed!</h2>";
echo "<p><strong>Demo accounts (password: password123):</strong></p>";
echo "<ul>";
echo "<li>student@example.com (username: student)</li>";
echo "<li>staff@example.com (username: staff)</li>";
echo "<li>public@example.com (username: public)</li>";
echo "</ul>";
?>