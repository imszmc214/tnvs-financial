<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['invoice_id'])) {
    $invoice_id = trim($_POST['invoice_id']); // Use as string, trim whitespace

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

    // Fetch payment method and amount
    $fetch_sql = "SELECT payment_method, amount FROM accounts_payable WHERE invoice_id = ?";
    $stmt_fetch = $conn->prepare($fetch_sql);
    $stmt_fetch->bind_param("s", $invoice_id);
    
    if (!$stmt_fetch->execute()) {
        $_SESSION['error'] = "Error fetching invoice details.";
        $stmt_fetch->close();
        $conn->close();
        header("Location: payables_ia.php");
        exit();
    }
    
    $stmt_fetch->bind_result($payment_method, $amount);
    $stmt_fetch->fetch();
    $stmt_fetch->close();

    $payment_modes = ["Cash", "Ecash", "Bank Transfer"];

    if (in_array($payment_method, $payment_modes)) {
        // Journal and ledger logic...
        $insert_journal_sql = "INSERT INTO journal_table (expense_categories, debit_amount, credit_amount, credit_account) 
                               VALUES ('Accounts Payables', ?, 0, NULL)";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("d", $amount);
        $stmt_insert_journal->execute();
        $stmt_insert_journal->close();

        $insert_journal_sql = "INSERT INTO journal_table (expense_categories, debit_amount, credit_amount, credit_account) 
                               VALUES (?, 0, ?, 'Accounts Payables')";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("sd", $payment_method, $amount);
        $stmt_insert_journal->execute();
        $stmt_insert_journal->close();

        $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
                               VALUES ('Miscellaneous Expense', ?, 0, 'Expense', NOW())";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("d", $amount);
        $stmt_insert_journal->execute();
        $stmt_insert_journal->close();

        $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
                               VALUES ('Accounts Payables', 0, ?, 'Liabilities', NOW())";
        $stmt_insert_journal = $conn->prepare($insert_journal_sql);
        $stmt_insert_journal->bind_param("d", $amount);
        $stmt_insert_journal->execute();
        $stmt_insert_journal->close();
    }

    // Only update status and approval_date
    $stmt = $conn->prepare("UPDATE accounts_payable SET status = 'approved', approval_date = NOW() WHERE invoice_id = ?");
    $stmt->bind_param("s", $invoice_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Invoice ID {$invoice_id} approved successfully.";
    } else {
        $_SESSION['error'] = "Failed to confirm Invoice ID {$invoice_id}.";
    }

    $stmt->close();
} else {
    $_SESSION['error'] = "No invoice ID provided.";
}

$conn->close();
header("Location: payables_ia.php");
exit();