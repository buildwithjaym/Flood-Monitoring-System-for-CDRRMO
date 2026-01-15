<?php
session_start();

// Only allow logged-in admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "babala_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query all readings (you can later add WHERE for date ranges if needed)
$sql = "SELECT id, sensor_value, status, created_at 
        FROM water_readings 
        ORDER BY id ASC";
$result = $conn->query($sql);

// Set headers so the browser downloads this as a CSV file
$filename = "babala_baha_flood_report_" . date("Ymd_His") . ".csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=".$filename);

// Open output stream
$output = fopen("php://output", "w");

// CSV header row
fputcsv($output, array(
    "ID",
    "Sensor Value",
    "Status",
    "Description",
    "Recorded At"
));

// Write data rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status = strtoupper($row['status']);
        $description = "";

        if ($status === "NORMAL") {
            $description = "Water level within safe range.";
        } elseif ($status === "ALERT") {
            $description = "Water level elevated. Prepare for possible action.";
        } elseif ($status === "CRITICAL") {
            $description = "Water level critical. Immediate attention required.";
        } else {
            $description = "Unknown status.";
        }

        fputcsv($output, array(
            $row['id'],
            $row['sensor_value'],
            $status,
            $description,
            $row['created_at']
        ));
    }
} else {
    // If no data, still output one line so the file isn't empty
    fputcsv($output, array("No data available", "", "", "", ""));
}

fclose($output);
$conn->close();
exit;
?>
