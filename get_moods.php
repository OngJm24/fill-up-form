<?php
session_start();

// Simple Admin Authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('Forbidden');
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "simple-forms";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Connection failed');
}

if (isset($_GET['id'])) {
    $submission_id = (int)$_GET['id'];
    $sql = "SELECT mood, photo_path, reason FROM mood_photos WHERE submission_id = $submission_id";
    $result = $conn->query($sql);

    $moods = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $moods[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($moods);
} else {
    header('HTTP/1.1 400 Bad Request');
    exit('Missing ID');
}

$conn->close();
?>
