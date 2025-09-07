<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

// Connect to your database and update notifications as read
$servername = "localhost";
$usernameDB = "financial";
$passwordDB = "UbrdRDvrHRAyHiA]";
$dbname = "financial_db";

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Update all notifications as read (you might want to add a 'read' column to your notifications table)
// This is a simple implementation - you might need to adjust based on your database structure
$sql = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>