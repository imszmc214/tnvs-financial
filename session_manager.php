<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start the session only if it's not already started
}

// Function to check if the user is logged in
function is_user_logged_in($username) {
    return isset($_SESSION['users_username']) && $_SESSION['users_username'] === $username;
}

// Function to mark the user as logged in
function log_user_in($username, $conn) {
    // Example: Update the user status in the database (if applicable)
    // You might have a column like 'is_logged_in' or similar in your 'userss' table
    $stmt = $conn->prepare("UPDATE userss SET is_logged_in = 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
}

// Function to log user out
function log_user_out($username, $conn) {
    // Example: Update the user status in the database (if applicable)
    // You might have a column like 'is_logged_in' or similar in your 'userss' table
    $stmt = $conn->prepare("UPDATE userss SET is_logged_in = 0 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
}
?>
