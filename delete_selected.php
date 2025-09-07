<?php

$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; ;

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if any IDs were selected
    if (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
        // Sanitize input
        $selected_ids = $_POST['selected_ids'];

        // Prepare placeholders for the SQL IN clause
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

        // Prepare the SQL DELETE query
        $sql = "DELETE FROM dr WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Bind parameters dynamically
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);

            // Execute the query
            if ($stmt->execute()) {
                // Redirect back to the table page with a success message
                header("Location: disbursedrecords.php?status=success");
                exit();
            } else {
                // Error while executing the query
                echo "Error deleting records: " . $stmt->error;
            }
        } else {
            // Error preparing the query
            echo "Error preparing the delete query: " . $conn->error;
        }
    } else {
        // No IDs were selected
        header("Location: disbursedrecords.php?status=no_selection");
        exit();
    }
} else {
    // If the script is accessed without a POST request
    header("Location: disbursedrecords.php");
    exit();
}
?>
