<?php
session_start();

$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Receive all possible POST fields (hidden in modal)
$invoice_id = $_POST['invoice_id'] ?? '';
$account_name = $_POST['account_name'] ?? '';
$department = $_POST['department'] ?? '';
$amount_pay = floatval($_POST['amount_pay'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? '';
$payment_due = $_POST['payment_due'] ?? '';
$document = $_POST['document'] ?? '';
$bank_name = $_POST['bank_name'] ?? '';
$bank_account_number = $_POST['bank_account_number'] ?? '';
$bank_account_name = $_POST['bank_account_name'] ?? '';
$ecash_provider = $_POST['ecash_provider'] ?? '';
$ecash_account_name = $_POST['ecash_account_name'] ?? '';
$ecash_account_number = $_POST['ecash_account_number'] ?? '';

if ($amount_pay <= 0) {
    echo "Invalid payment amount.";
    exit();
}

// Fetch current amount and amount_paid + fallback for missing fields
$stmt = $conn->prepare("SELECT amount, amount_paid, department, account_name, payment_method, payment_due, document, bank_name, bank_account_number, bank_account_name, ecash_provider, ecash_account_name, ecash_account_number FROM accounts_payable WHERE invoice_id = ?");
$stmt->bind_param("s", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo "Invoice not found.";
    exit();
}

$current_amount = floatval($row['amount']);
$current_paid = floatval($row['amount_paid']);

if ($amount_pay > ($current_amount - $current_paid)) {
    echo "Payment exceeds the remaining balance.";
    exit();
}

// Fallback for missing fields from DB values
if (empty($department)) $department = $row['department'];
if (empty($account_name)) $account_name = $row['account_name'];
if (empty($payment_method)) $payment_method = $row['payment_method'];
if (empty($payment_due)) $payment_due = $row['payment_due'];
if (empty($document)) $document = $row['document'];
if (empty($bank_name)) $bank_name = $row['bank_name'];
if (empty($bank_account_number)) $bank_account_number = $row['bank_account_number'];
if (empty($bank_account_name)) $bank_account_name = $row['bank_account_name'];
if (empty($ecash_provider)) $ecash_provider = $row['ecash_provider'];
if (empty($ecash_account_name)) $ecash_account_name = $row['ecash_account_name'];
if (empty($ecash_account_number)) $ecash_account_number = $row['ecash_account_number'];

$new_paid = $current_paid + $amount_pay;

// Update accounts_payable
$update_sql = "UPDATE accounts_payable SET amount_paid = ? WHERE invoice_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ds", $new_paid, $invoice_id);

if ($update_stmt->execute()) {
    // Prepare insert for pa table
    $insert_sql = "INSERT INTO pa (
        reference_id, account_name, requested_department, mode_of_payment, 
        expense_categories, amount, description, document, payment_due, 
        bank_name, from_payable, bank_account_number, bank_account_name, 
        ecash_provider, ecash_account_name, ecash_account_number
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $insert_stmt = $conn->prepare($insert_sql);
    $description = "Payment for invoice " . $invoice_id;
    $category = "Account Payable";
    $reference_id = str_replace("INV-", "PA-", $invoice_id);
    $from_payable = 1;

    $insert_stmt->bind_param(
        "sssssdssssisssss",
        $reference_id,
        $account_name,
        $department,
        $payment_method,
        $category,
        $amount_pay,
        $description,
        $document,
        $payment_due,
        $bank_name,
        $from_payable,
        $bank_account_number,
        $bank_account_name,
        $ecash_provider,
        $ecash_account_name,
        $ecash_account_number
    );

    if ($insert_stmt->execute()) {
        // Notification
        $notificationMessage = "Added new payable amount request with Invoice ID: $reference_id";
        $notificationQuery = "INSERT INTO notifications (message) VALUES (?)";
        $notifStmt = $conn->prepare($notificationQuery);
        $notifStmt->bind_param("s", $notificationMessage);
        $notifStmt->execute();
        $notifStmt->close();

        $insert_stmt->close();
        $update_stmt->close();
        $stmt->close();
        $conn->close();
        header("Location: payables.php");
        exit();
    } else {
        echo "Error inserting into `pa` table: " . $insert_stmt->error;
    }
} else {
    echo "Error updating payment.";
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>