<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Confirm invoice (update status)
if (isset($_POST['invoice_id'])) {
    // CORRECTED: invoice_id is a string, not an integer
    $invoice_id = $_POST['invoice_id']; // Remove intval() since invoice_id is a string

    $fetch_sql = "SELECT amount, payment_method FROM account_receivable WHERE invoice_id = ?";
    $stmt_fetch = $conn->prepare($fetch_sql);
    $stmt_fetch->bind_param("s", $invoice_id); // Changed "i" to "s"
    $stmt_fetch->execute();
    $stmt_fetch->bind_result($amount, $payment_method);
    $stmt_fetch->fetch();
    $stmt_fetch->close();

    $payment_modes = ["Cash", "Credit"];

    if (in_array($payment_method, $payment_modes)) {
        // ✅ Insert new debit entry for the expense category
        $insert_journal_sql = "INSERT INTO journal_table (expense_categories, debit_amount, credit_amount, credit_account) 
                                   VALUES ('Accounts Receivables', 0, ?, NULL)";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("d", $amount);
        $stmt_insert_journal->execute();

        // ✅ Insert new credit entry (for the specific payment mode)
        $insert_journal_sql = "INSERT INTO journal_table (expense_categories, debit_amount, credit_amount, credit_account) 
                                   VALUES (?, ?, 0, 'Accounts Receivables')";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("sd", $payment_method, $amount);
        $stmt_insert_journal->execute();

        // Insert a new debit entry for 'Accounts Receivable' under Revenue
        $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
VALUES ('Accounts Receivable', ?, 0, 'Revenue', NOW())";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("d", $amount);
        $stmt_insert_journal->execute();

        // Insert a new credit entry for 'Boundary' under Assets
        $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
VALUES ('Boundary', 0, ?, 'Assets', NOW())";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("d", $amount);
        $stmt_insert_journal->execute();
    }

    $stmt = $conn->prepare("UPDATE account_receivable SET status = 'confirmed', approval_date = NOW() WHERE invoice_id = ?");
    $stmt->bind_param("s", $invoice_id); // Changed "i" to "s"

    if ($stmt->execute()) {
        $_SESSION['success'] = "Invoice ID $invoice_id confirmed successfully.";
    } else {
        $_SESSION['error'] = "Failed to confirm Invoice ID $invoice_id.";
    }

    $stmt->close();
}

$conn->close();

// Redirect back to the page
header("Location: receivables_ia.php?page=iareceivables");
exit();