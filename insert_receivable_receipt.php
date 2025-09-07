<?php
$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

// Get form data
$receipt_id = $_POST['receipt_id'];
$driver_name = $_POST['driver_name'];
$amount_receive = $_POST['amount_receive'];
$payment_date = $_POST['payment_date'];
$invoice_id = $_POST['invoice_id'];
$payment_method = $_POST['payment_method'];

// Insert into database
$sql = "INSERT INTO receivable_receipt (receipt_id, driver_name, amount_receive, payment_date, invoice_id, payment_method)
        VALUES ('$receipt_id', '$driver_name', '$amount_receive', '$payment_date', '$invoice_id', '$payment_method')";

$notificationMessage = "Added new receivable receipt request with Invoice ID: $receipt_id";
$notificationQuery = "INSERT INTO notifications (message) VALUES ('$notificationMessage')";
$conn->query($notificationQuery);

mysqli_query($conn, $sql);
header("Location: receivables_receipts.php");
exit();
