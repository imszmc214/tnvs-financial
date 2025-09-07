<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Payment logic (simulate payable_payment.php inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_id'], $_POST['amount_pay'])) {
    $invoice_id = trim($_POST['invoice_id']); // Keep as string, not integer
    $amount_pay = (float)$_POST['amount_pay'];

    // Get current paid and amount, plus all needed fields for PA
    $stmt = $conn->prepare("SELECT * FROM accounts_payable WHERE invoice_id = ? AND status = 'approved'");
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $ap = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($ap) {
        $new_paid = $ap['amount_paid'] + $amount_pay;
        $amount = $ap['amount'];

        // Update amount_paid but DO NOT DELETE from accounts_payable
        $stmt = $conn->prepare("UPDATE accounts_payable SET amount_paid = ?, updated_at = NOW() WHERE invoice_id = ? AND status = 'approved'");
        $stmt->bind_param("ds", $new_paid, $ap['invoice_id']);
        $stmt->execute();
        $stmt->close();

        // Prepare data for PA table (disbursement request)
        // Use the original invoice_id without adding another prefix
        $reference_id = $invoice_id;
        $expense_categories = "Account Payable";
        $description = "Payment for invoice " . $invoice_id;
        $from_payable = 1;
        $payment_due_date = date('Y-m-d', strtotime($ap['payment_due']));

        // Insert into pa table (let requested_at be set automatically)
        $insert_sql = "INSERT INTO pa (
            reference_id, account_name, requested_department, mode_of_payment, 
            expense_categories, amount, description, payment_due, from_payable, 
            bank_name, bank_account_number, bank_account_name, 
            ecash_provider, ecash_account_name, ecash_account_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "sssssdssissssss",
            $reference_id,
            $ap['account_name'],
            $ap['department'],
            $ap['payment_method'],
            $expense_categories,
            $amount_pay,
            $description,
            $payment_due_date,
            $from_payable,
            $ap['bank_name'],
            $ap['bank_account_number'],
            $ap['bank_account_name'],
            $ap['ecash_provider'],
            $ap['ecash_account_name'],
            $ap['ecash_account_number']
        );
        $insert_stmt->execute();
        $insert_stmt->close();

        // Optionally, mark as 'paid' if fully paid (for easier filtering in UI)
        if ($new_paid >= $amount - 0.00001) {
            $stmt = $conn->prepare("UPDATE accounts_payable SET status = 'paid' WHERE invoice_id = ?");
            $stmt->bind_param("s", $invoice_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: payables.php");
    exit();
}

// Overview metrics
$approvedCount = $conn->query("SELECT COUNT(*) as total FROM accounts_payable WHERE status = 'approved' AND amount_paid < amount")->fetch_assoc()['total'];
$totalDue = $conn->query("SELECT SUM(amount) as total FROM accounts_payable WHERE status = 'approved' AND amount_paid < amount")->fetch_assoc()['total'];
$totalRemaining = $conn->query("SELECT SUM(amount - amount_paid) as total FROM accounts_payable WHERE status = 'approved'")->fetch_assoc()['total'];

// Main Query
$sql = "SELECT * FROM accounts_payable WHERE status = 'approved' ORDER BY approval_date DESC";
$result = $conn->query($sql);

// Aging Summary
$aging_brackets = [
    'Current' => 'DATEDIFF(payment_due, CURRENT_DATE) >= 0',
    '1-30 Days Past Due' => 'DATEDIFF(CURRENT_DATE, payment_due) BETWEEN 1 AND 30',
    '31-60 Days Past Due' => 'DATEDIFF(CURRENT_DATE, payment_due) BETWEEN 31 AND 60',
    '61-90 Days Past Due' => 'DATEDIFF(CURRENT_DATE, payment_due) BETWEEN 61 AND 90',
    'Over 90 Days Past Due' => 'DATEDIFF(CURRENT_DATE, payment_due) > 90'
];
$aging_data = [];
foreach ($aging_brackets as $label => $where) {
    $q = $conn->query("SELECT SUM(amount - amount_paid) as total, COUNT(*) as count FROM accounts_payable WHERE status='approved' AND amount > amount_paid AND $where");
    $row = $q->fetch_assoc();
    $aging_data[$label] = [
        'count' => $row['count'] ?? 0,
        'total' => $row['total'] ?? 0,
    ];
}

// Outstanding Summary
$outstanding_sql = "
    SELECT department, SUM(amount - amount_paid) as outstanding, COUNT(*) as count
    FROM accounts_payable
    WHERE status='approved' AND amount > amount_paid
    GROUP BY department
    ORDER BY outstanding DESC
";
$outstanding_result = $conn->query($outstanding_sql);
$outstanding_data = [];
while ($row = $outstanding_result->fetch_assoc()) {
    $outstanding_data[] = $row;
}
?>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <title>Payables</title>
    <link rel="icon" href="logo.png" type="img">
    <style>
    .tab-active {
      border-bottom: 2px solid #7c3aed;
      color: #7c3aed !important;
      font-weight: bold;
    }
    
    @media (max-width: 1024px) {
      .overview-flex { flex-direction: column !important; }
      .overview-left, .overview-right { width: 100% !important; }
      .overview-cards { flex-direction: column !important; }
      .overview-right { min-width: 0 !important; }
    }
    </style>
</head>
<body class="bg-white">
    <?php include('sidebar.php'); ?>

    <div class="overflow-y-auto h-full px-6">
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Payables</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Accounts Payable</a>
                /
                <a href="payables.php" class="text-blue-600 hover:text-blue-600">Payables</a>
            </div>
        </div>

        <div class=" ml-6 mr-6 p-4 bg-white border border-gray-200 rounded-xl shadow flex flex-col gap-4">
            <!-- Header -->
            <div class="flex items-center gap-2">
                <i class="fas fa-clipboard text-xl text-violet-700 "></i>
                <h2 class="text-xl font-poppins text-black">Overview</h2>
            </div>

            <!-- Stat Cards -->
            <div class="flex overview-left overview-cards pt-4 pb-4 gap-8">
                <!-- Pending -->
                <div class="flex-1 rounded-lg bg-purple-50 p-6 flex flex-col items-center justify-center border border-gray-200">
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg font-semibold text-gray-700">Approved Invoices</h3>
                    </div>
                        <p class="text-3xl font-bold text-purple-700"><?= $approvedCount ?></p>
                </div>

                <!-- Pending by Mode -->
                <div class="flex-1 rounded-lg bg-blue-50 p-6 flex flex-col items-center justify-center">
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg font-semibold text-gray-700">Total Amount Due</h3>
                    </div>
                        <p class="text-3xl font-bold text-green-700">₱ <?= number_format($totalDue ?: 0, 2) ?></p>
                </div>

                <!-- Total Amount Due -->
                <div class="flex-1 rounded-lg bg-orange-50 p-6 flex flex-col items-center justify-center">
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg font-semibold text-gray-700">Total Remaining Balance</h3>
                    </div>
                        <p class="text-3xl font-bold <?= ($totalRemaining > 0) ? 'text-red-600' : 'text-green-600' ?>">₱ <?= number_format($totalRemaining ?: 0, 2) ?></p>
                </div>
            </div>
        </div>        
        
        <div class="flex-1 bg-white p-6 h-full w-full">
            <div class="w-full">
                <!-- Tabs -->
                <div class="mb-6 flex space-x-4 border-b border-gray-200">
                    <button class="tab-btn px-4 py-2 text-gray-700 tab-active" onclick="showTab('invoices')">Approved Invoices</button>
                    <button class="tab-btn px-4 py-2 text-gray-700" onclick="showTab('aging')">Aging Summary</button>
                    <button class="tab-btn px-4 py-2 text-gray-700" onclick="showTab('outstanding')">Outstanding Summary</button>
                </div>

                <!-- Approved Invoices Tab -->
                <div id="tab-invoices">
                    <div class="flex items-center justify-between">
                        <form method="GET" action="payables.php" class="flex flex-wrap items-center gap-4 mb-4">
                            <input type="text" id="searchInput" class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Search here" onkeyup="filterTable()" />
                            <div class="flex items-center space-x-2">
                                <label for="dueDate" class="font-semibold">Payment Due:</label>
                                <input type="date" id="dueDate" class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm" onchange="filterTable()" />
                            </div>
                        </form>
                    </div>

                    <!-- Main content area -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                        <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                            <i class="far fa-file-alt text-xl"></i>
                            <h2 class="text-2xl font-poppins text-black">Approved Invoices</h2>
                        </div>

                        <div class="overflow-x-auto w-full">
                        <table class="w-full table-auto bg-white mt-4">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                    <th class="pl-10 pr-6 py-2">Invoice ID</th>
                                    <th class="px-6 py-2">Department</th>
                                    <th class="px-6 py-2">Account Name</th>
                                    <th class="px-6 py-2">Amount</th>
                                    <th class="px-6 py-2">Amount Paid</th>
                                    <th class="px-6 py-2">Remaining Balance</th>
                                    <th class="px-6 py-2">Fully-Paid Due Date</th>
                                    <th class="px-6 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoiceTable" class="text-gray-900 text-sm font-light ">
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $remaining = $row['amount'] - $row['amount_paid'];
                                        // Only display if there is a remaining balance
                                        if ($remaining > 0.00001) {
                                            $jsonData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                            echo "<tr class='hover:bg-violet-100'>";
                                            // REMOVED THE "INV-" PREFIX - USE THE ORIGINAL INVOICE ID
                                            echo "<td class='pl-10 pr-6 px-2'>{$row['invoice_id']}</td>";
                                            echo "<td class='px-6 py-2'>{$row['department']}</td>";
                                            echo "<td class='px-6 py-2'>{$row['account_name']}</td>";
                                            echo "<td class='px-6 py-2'>₱ " . number_format($row['amount'], 2) . "</td>";
                                            echo "<td class='px-6 py-2'>₱ " . number_format($row['amount_paid'], 2) . "</td>";
                                            $rbClass = ($remaining > 0) ? "text-red-600 font-bold" : "text-green-600 font-bold";
                                            echo "<td class='px-6 py-2 $rbClass'>₱ " . number_format($remaining, 2) . "</td>";
                                            echo "<td class='px-6 py-2'>" . date('Y-m-d', strtotime($row['payment_due'])) . "</td>";
                                            echo "<td class='py-2'>";
                                            echo "<button onclick='openPaymentModal($jsonData)' class='border rounded-full px-2 py-1 text-violet-700 border-violet-600 bg-violet-10 font-semibold'>Post Request</button>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center py-4'>No records found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between items-center">
                        <div id="pageStatus" class="text-gray-700 font-bold"></div>
                        <div class="flex">
                            <button id="prevPage" class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500" onclick="prevPage()">Previous</button>
                            <button id="nextPage" class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500" onclick="nextPage()">Next</button>
                        </div>
                    </div>
                    <div class="mt-6">
                        <canvas id="pdf-viewer" width="600" height="400"></canvas>
                    </div>
                </div>

                <!-- Aging Summary Tab -->
                <div id="tab-aging" class="hidden">
                    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6 mb-6">
                        <div class="flex items-center mb-4 space-x-3 text-purple-700">
                            <i class="far fa-clock text-xl"></i>
                            <h2 class="text-2xl font-poppins text-black">Aging Summary Report</h2>
                        </div>
                        <table class="min-w-full bg-white mt-4">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                    <th class="px-4 py-2">Bracket</th>
                                    <th class="px-4 py-2">Number of Invoices</th>
                                    <th class="px-4 py-2">Outstanding Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aging_data as $label => $ag): ?>
                                <tr>
                                    <td class="px-4 py-2"><?= $label ?></td>
                                    <td class="px-4 py-2"><?= $ag['count'] ?></td>
                                    <td class="px-4 py-2">₱ <?= number_format($ag['total'], 2) ?></td>
                                </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Outstanding Summary Tab -->
                <div id="tab-outstanding" class="hidden">
                    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6 mb-6">
                        <div class="flex items-center mb-4 space-x-3 text-purple-700">
                            <i class="far fa-list-alt text-xl"></i>
                            <h2 class="text-2xl font-poppins text-black">Outstanding Summary Report</h2>
                        </div>
                        <table class="min-w-full bg-white mt-4">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                    <th class="px-4 py-2">Department</th>
                                    <th class="px-4 py-2">Count</th>
                                    <th class="px-4 py-2">Outstanding Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outstanding_data as $out): ?>
                                <tr>
                                    <td class="px-4 py-2"><?= htmlspecialchars($out['department']) ?></td>
                                    <td class="px-4 py-2"><?= $out['count'] ?></td>
                                    <td class="px-4 py-2">₱ <?= number_format($out['outstanding'], 2) ?></td>
                                </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div id="paymentModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-96">
                <h3 class="text-xl font-semibold text-blue-900 mb-4">Payment Details</h3>
                <form method="POST" action="payable_payment.php">
                    <input type="hidden" name="invoice_id" id="invoice_id">
                    <input type="hidden" name="department" id="department">
                    <input type="hidden" name="account_name" id="account_name">
                    <input type="hidden" name="amount" id="amount">
                    <input type="hidden" name="payment_method" id="payment_method">
                    <input type="hidden" name="payment_due" id="payment_due">
                    <input type="hidden" name="document" id="document">
                    <input type="hidden" name="bank_name" id="bank_name">
                    <input type="hidden" name="bank_account_number" id="bank_account_number">
                    <input type="hidden" name="ecash_provider" id="ecash_provider">
                    <input type="hidden" name="ecash_account_name" id="ecash_account_name">
                    <input type="hidden" name="ecash_account_number" id="ecash_account_number">
                    <input type="hidden" name="bank_account_name" id="bank_account_name">
                    <div class="mb-4">
                        <label for="amount_pay" class="block text-gray-700">Amount to Pay</label>
                        <input type="number" name="amount_pay" id="amount_pay" min="1" class="border border-gray-300 rounded-lg w-full py-2 px-4 mt-2" placeholder="Enter amount" required />
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeModal()" class="bg-gray-500 text-white px-4 py-2 rounded mr-2">Cancel</button>
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Submit</button>
                    </div>
                </form>
            </div>
        </div>

    <script>
    // Tabs
    function showTab(tab) {
        document.getElementById('tab-invoices').classList.add('hidden');
        document.getElementById('tab-aging').classList.add('hidden');
        document.getElementById('tab-outstanding').classList.add('hidden');
        document.getElementById('tab-' + tab).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('tab-active'));
        if (tab === 'invoices')
            document.querySelectorAll('.tab-btn')[0].classList.add('tab-active');
        else if (tab === 'aging')
            document.querySelectorAll('.tab-btn')[1].classList.add('tab-active');
        else if (tab === 'outstanding')
            document.querySelectorAll('.tab-btn')[2].classList.add('tab-active');
    }

    // Invoice Table JS
    let table = document.querySelector("#invoiceTable");
    let allRows = Array.from(table.querySelectorAll("tr"));
    let currentPage = 1;
    const rowsPerPage = 10;

    function openPaymentModal(data) {
        document.getElementById("invoice_id").value = data.invoice_id;
        document.getElementById("department").value = data.department;
        document.getElementById("account_name").value = data.account_name;
        document.getElementById("amount").value = data.amount;
        document.getElementById("payment_method").value = data.payment_method;
        document.getElementById("payment_due").value = data.payment_due;
        document.getElementById("document").value = data.document;
        document.getElementById("bank_name").value = data.bank_name;
        document.getElementById("bank_account_number").value = data.bank_account_number;
        document.getElementById("ecash_provider").value = data.ecash_provider;
        document.getElementById("ecash_account_name").value = data.ecash_account_name;
        document.getElementById("ecash_account_number").value = data.ecash_account_number;
        document.getElementById("bank_account_name").value = data.bank_account_name;
        document.getElementById("paymentModal").classList.remove("hidden");
    }

    function closeModal() {
        document.getElementById("paymentModal").classList.add("hidden");
    }

    function displayData(page) {
        table.innerHTML = ""; // Clear table content
        let filteredRows = filterRows(); // Apply filters first
        let start = (page - 1) * rowsPerPage;
        let end = start + rowsPerPage;
        let paginatedRows = filteredRows.slice(start, end);

        if (paginatedRows.length === 0) {
            table.innerHTML = "<tr><td colspan='8' class='text-center py-4'>No records found</td></tr>";
        } else {
            paginatedRows.forEach(row => table.appendChild(row));
        }

        document.getElementById("prevPage").disabled = currentPage === 1;
        document.getElementById("nextPage").disabled = end >= filteredRows.length;
        const pageStatus = document.getElementById("pageStatus");
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
        pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    function filterRows() {
        const searchInput = document.getElementById("searchInput").value.toLowerCase();
        const dueDate = document.getElementById("dueDate").value;
        return allRows.filter(row => {
            const cells = row.children;
            if (cells.length < 7) return false;
            const department = cells[1].textContent.toLowerCase();
            const accountName = cells[2].textContent.toLowerCase();
            const amount = cells[3].textContent.toLowerCase();
            const rowDate = cells[6].textContent.trim();
            const matchSearch = (
                department.includes(searchInput) ||
                accountName.includes(searchInput) ||
                amount.includes(searchInput)
            );
            const matchDate = (!dueDate || rowDate === dueDate);
            return matchSearch && matchDate;
        });
    }

    function filterTable() {
        currentPage = 1;
        displayData(currentPage);
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            displayData(currentPage);
        }
    }

    function nextPage() {
        let filteredRows = filterRows();
        if (currentPage * rowsPerPage < filteredRows.length) {
            currentPage++;
            displayData(currentPage);
        }
    }

    window.onload = () => {
        allRows = Array.from(table.querySelectorAll("tr")); // Refresh for correct count
        displayData(currentPage);
        showTab('invoices');
    };
    </script>
</body>
</html>