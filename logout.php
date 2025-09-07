<?php
session_start();
include 'session_manager.php'; // Include your session manager

// Database connection
// $servername = "127.0.0.1:3308"; 
$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Clear the user's session
if (isset($_SESSION['users_username'])) {
    log_user_out($_SESSION['users_username'], $conn); // Log user out in the database
}

session_unset(); // Remove all session variables
session_destroy(); // Destroy the session
header("Location: login.php"); // Redirect to login page
exit();
?>
