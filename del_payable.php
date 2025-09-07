<?php
session_start();

// Database connection
$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if ID is provided through POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoice_id'])) {
    $invoice_id = trim($_POST['invoice_id']); // Keep as string, trim whitespace

    // First check if the invoice exists
    $check_sql = "SELECT invoice_id FROM accounts_payable WHERE invoice_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("s", $invoice_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows === 0) {
        $_SESSION['error'] = "Invoice ID {$invoice_id} not found.";
        $stmt_check->close();
        $conn->close();
        header("Location: payables_ia.php");
        exit();
    }
    $stmt_check->close();

    // First, archive the record
    $archive_sql = "
        INSERT INTO archive (
            reference_id,
            account_name,
            requested_department,
            mode_of_payment,
            expense_categories,
            amount,
            description,
            document,
            time_period,
            payment_due,
            bank_name,
            bank_account_number,
            bank_account_name,
            ecash_provider,
            ecash_account_name,
            ecash_account_number,
            rejected_reason
        ) 
        SELECT 
            invoice_id, 
            account_name, 
            department, 
            payment_method, 
            '' as expense_categories, 
            amount, 
            description, 
            document, 
            '' as time_period, 
            payment_due, 
            bank_name, 
            bank_account_number,
            bank_account_name,
            ecash_provider,
            ecash_account_name,
            ecash_account_number, 
            '' as rejected_reason
        FROM accounts_payable 
        WHERE invoice_id = ?
    ";
    
    $archive_stmt = $conn->prepare($archive_sql);
    if ($archive_stmt) {
        $archive_stmt->bind_param("s", $invoice_id);
        $archive_stmt->execute();
        $archive_stmt->close();
    }

    // After archiving, delete from accounts_payable
    $sql = "DELETE FROM accounts_payable WHERE invoice_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $invoice_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Invoice ID {$invoice_id} deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Error preparing delete statement: " . $conn->error;
    }
} else {
    $_SESSION['error'] = "Invalid request.";
}

$conn->close();
header("Location: payables_ia.php");
exit();