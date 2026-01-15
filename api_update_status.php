<?php
// Simple API for ESP to log water level status (NO API KEY VERSION)

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo "Only POST allowed";
    exit;
}

// Required parameters
if (!isset($_POST['sensor_value']) || !isset($_POST['status'])) {
    http_response_code(400); // Bad Request
    echo "Missing parameters";
    exit;
}

$sensor_value = (int) $_POST['sensor_value'];
$status       = strtoupper(trim($_POST['status']));

// Validate status
if ($status !== 'NORMAL' && $status !== 'ALERT' && $status !== 'CRITICAL') {
    http_response_code(400);
    echo "Invalid status";
    exit;
}

// Connect to DB
$conn = new mysqli("localhost", "root", "", "babala_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo "DB connection failed: " . $conn->connect_error;
    exit;
}

// Insert record
$sql = "INSERT INTO water_readings (sensor_value, status) VALUES (?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo "Prepare failed: " . $conn->error;
    $conn->close();
    exit;
}

$stmt->bind_param("is", $sensor_value, $status);
$ok = $stmt->execute();

if ($ok) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Insert failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
