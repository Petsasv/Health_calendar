<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Change in production
define('DB_PASS', '');      // Change in production
define('DB_NAME', 'health_calendar');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
} else {
    die("Error creating database: " . $conn->error);
}

// Create users table with additional health-related fields
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    age INT,
    sex ENUM('male', 'female', 'other'),
    location VARCHAR(100),
    conditions TEXT,
    health_data JSON,
    last_health_update TIMESTAMP NULL,
    preferences JSON
)";

if (!$conn->query($sql)) {
    die("Error creating users table: " . $conn->error);
}

// Add location column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'location'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN location VARCHAR(100) AFTER sex";
    if (!$conn->query($sql)) {
        die("Error adding location column: " . $conn->error);
    }
}

// Add health_data column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'health_data'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN health_data JSON AFTER conditions";
    if (!$conn->query($sql)) {
        die("Error adding health_data column: " . $conn->error);
    }
}

// Add last_health_update column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'last_health_update'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN last_health_update TIMESTAMP NULL AFTER health_data";
    if (!$conn->query($sql)) {
        die("Error adding last_health_update column: " . $conn->error);
    }
}

// Add location_lat and location_lng columns if they don't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'location_lat'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN location_lat DECIMAL(10,8) AFTER location";
    if (!$conn->query($sql)) {
        die("Error adding location_lat column: " . $conn->error);
    }
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'location_lng'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN location_lng DECIMAL(11,8) AFTER location_lat";
    if (!$conn->query($sql)) {
        die("Error adding location_lng column: " . $conn->error);
    }
}

// Add location_country column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'location_country'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN location_country VARCHAR(2) AFTER location_lng";
    if (!$conn->query($sql)) {
        die("Error adding location_country column: " . $conn->error);
    }
}

// Create reminders table
$sql = "CREATE TABLE IF NOT EXISTS reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    frequency VARCHAR(50),
    last_completed DATE,
    last_reminder TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (!$conn->query($sql)) {
    die("Error creating reminders table: " . $conn->error);
}

// Create health_logs table for tracking health metrics
$sql = "CREATE TABLE IF NOT EXISTS health_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    metric_type VARCHAR(50) NOT NULL,
    value FLOAT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (!$conn->query($sql)) {
    die("Error creating health_logs table: " . $conn->error);
}

// Create preventive_care table
$sql = "CREATE TABLE IF NOT EXISTS preventive_care (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    care_type VARCHAR(100) NOT NULL,
    last_completed DATE,
    next_due_date DATE,
    status ENUM('completed', 'pending', 'overdue') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (!$conn->query($sql)) {
    die("Error creating preventive_care table: " . $conn->error);
}

// Function to get database connection
function getDBConnection() {
    global $conn;
    return $conn;
}
?> 