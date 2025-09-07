<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate the full invoice ID
    $invoice_id = "INV-" . $_POST['invoice_id'];
    $driver_name = $_POST["driver_name"];
    $description = $_POST["description"];
    $amount = $_POST["amount"];
    $fully_paid_date = $_POST["fully_paid_date"];
    
    // Insert into account_receivable table
    $sql = "INSERT INTO account_receivable (invoice_id, driver_name, description, amount, fully_paid_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    // CORRECTED: Changed "i" to "s" for invoice_id (string, not integer)
    $stmt->bind_param("sssds", $invoice_id, $driver_name, $description, $amount, $fully_paid_date);

    if ($stmt->execute()) {
        $notificationMessage = "Added new receivable request with Invoice ID: $invoice_id";
        $notificationQuery = "INSERT INTO notifications (message) VALUES ('$notificationMessage')";
        $conn->query($notificationQuery);
        
        $_SESSION['success'] = "Receivable details added successfully!";
        header("Location: receivables_ia.php");
        exit();
    } else {
        // More detailed error message
        $_SESSION['error'] = "Error: " . $stmt->error;
        header("Location: receivables_ia.php");
        exit();
    }
    
    $stmt->close();
    $conn->close();
}