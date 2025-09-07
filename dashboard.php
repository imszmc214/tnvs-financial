<?php
session_start();
include 'session_manager.php';
$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['user_role'];

// Fetch Total Revenue
$sql_revenue = "SELECT SUM(credit_amount) AS total_revenue FROM ledger_table WHERE credit_account = 'Revenue'";
$result_revenue = $conn->query($sql_revenue);
$total_revenue = ($result_revenue->num_rows > 0) ? $result_revenue->fetch_assoc()['total_revenue'] : 0;

// Fetch Total Expenses
$sql_expenses = "SELECT SUM(debit_amount) AS total_expenses FROM ledger_table WHERE credit_account = 'Expense'";
$result_expenses = $conn->query($sql_expenses);
$total_expenses = ($result_expenses->num_rows > 0) ? $result_expenses->fetch_assoc()['total_expenses'] : 0;

// Calculate Net Income
$net_income = $total_revenue - $total_expenses;
?>

<html>

<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Dashboard</title>
    <link rel="icon" href="logo.png" type="img">
</head>

<body class="bg-blue-100 overflow-hidden">
    <?php include('sidebar.php'); ?>
<div class="flex-1 flex flex-col overflow-y-auto h-full px-6">
      <!-- Page Path with Title -->
      <div class="flex justify-between items-center px-6 py-6 font-poppins">
        <h1 class="text-2xl">DashBoard</h1>
        <div class="text-sm pr-6">
            <a href="dashboard.php?page=dashboard" class="text-blue-600">Home</a><span class="text-black">/Dashboard</span>
        </div>
      </div>

    <div class="flex-1 px-10 font-poppins">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white p-4 rounded-2xl border border-gray-300 cursor-default">
                <div class="flex items-center space-x-4">
                    <div class="text-purple-700 text-3xl"><i class="fas fa-money-bill-wave"></i></div>
                    <div>
                        <h2 class="text-lg font-poppins">TOTAL REVENUE</h2>
                        <p class="text-2xl font-bold text-blue-600 mb-2">₱<?= number_format($total_revenue, 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-gray-300 cursor-default">
                <div class="flex items-center space-x-4">
                    <div class="text-purple-700 text-3xl"><i class="fas fa-receipt"></i></div>
                    <div>
                        <h2 class="text-lg font-poppins">TOTAL EXPENSES</h2>
                        <p class="text-2xl font-bold text-red-600 mb-2">₱<?= number_format($total_expenses, 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-gray-300 cursor-default">
                <div class="flex items-center space-x-4">
                    <div class="text-purple-700 text-3xl"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <h2 class="text-lg font-poppins">NET INCOME</h2>
                        <p class="text-2xl font-bold text-green-600 mb-2">₱<?= number_format($net_income, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <br>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 ">
            <!-- Container for 2/3 of the width -->
            <div class="col-span-1 md:col-span-2 bg-white rounded-lg s border border-gray-300 ">
                <?php include('monthly_sales.php'); ?>
            </div>

            <!-- Container for 1/3 of the width -->
            <div class="col-span-1 bg-white rounded-lg border border-gray-300 ">
                <?php include('growth.php'); ?>
            </div>
        </div>
    </div>
    <div class="mt-6">
        <canvas id="pdf-viewer" width="600" height="400"></canvas>
    </div>
</div>
</body>

</html>