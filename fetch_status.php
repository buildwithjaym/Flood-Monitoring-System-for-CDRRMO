<?php
$conn = new mysqli("localhost", "root", "", "babala_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB error"]);
    exit;
}

$sql = "SELECT sensor_value, status, created_at FROM water_readings ORDER BY id DESC LIMIT 1";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode([
        "sensor_value" => (int)$row['sensor_value'],
        "status" => strtoupper($row['status']),
        "created_at" => $row['created_at']
    ]);
} else {
    echo json_encode(["sensor_value" => 0, "status" => "NO DATA", "created_at" => ""]);
}

$conn->close();
?>
