<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Overview Metrics
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM account_receivable WHERE status = 'pending'")->fetch_assoc()['total'];
$confirmedThisMonth = $conn->query("
  SELECT COUNT(*) as total 
  FROM account_receivable 
  WHERE status = 'confirmed' 
    AND MONTH(approval_date) = MONTH(CURRENT_DATE)
    AND YEAR(approval_date) = YEAR(CURRENT_DATE)
")->fetch_assoc()['total'];
$totalDue = $conn->query("
  SELECT SUM(amount) as total 
  FROM account_receivable 
  WHERE status = 'pending'
")->fetch_assoc()['total'];

// Build Main Query
$sql = "SELECT * FROM account_receivable WHERE status = 'pending' ORDER BY invoice_id DESC";
$result = $conn->query($sql);

// Get all rows for display
$rows = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Generate invoice code in the format: YYYYMMDD-RANDOM (numeric part only)
function generateInvoiceCode() {
    $date = date('Ymd');
    $rand = mt_rand(1000, 9999);
    return $date . '-' . $rand;
}

// Generate a unique invoice code
$invoiceIdValue = generateInvoiceCode();

// Check if this invoice code already exists
$check_sql = "SELECT COUNT(*) as count FROM account_receivable WHERE invoice_id = 'INV-" . $invoiceIdValue . "'";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

// If it exists, generate a new one
if ($row['count'] > 0) {
    $invoiceIdValue = generateInvoiceCode();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice Confirmation</title>
    <link rel="icon" href="logo.png" type="img">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
    @media (max-width: 1024px) {
      .overview-flex { flex-direction: column !important; }
      .overview-left, .overview-right { width: 100% !important; }
      .overview-cards { flex-direction: column !important; }
      .overview-right { min-width: 0 !important; }
    }
    </style>

</head>

<body class="overflow-hidden bg-white">
    <?php include('sidebar.php'); ?>
    <?php if (isset($_SESSION['success'])): ?>
    <div id="toast-success" class="fixed top-6 right-6 z-50 flex items-center w-full max-w-xs p-4 mb-4 text-green-800 bg-green-100 rounded-lg shadow transition-opacity duration-500" role="alert" style="opacity:1;">
        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
        <div class="ml-3 text-sm font-medium"><?= htmlspecialchars($_SESSION['success']) ?></div>
    </div>
    <script>
        setTimeout(function() {
            var toast = document.getElementById('toast-success');
            if(toast) toast.style.opacity = '0';
        }, 1800);
        setTimeout(function() {
            var toast = document.getElementById('toast-success');
            if(toast) toast.style.display = 'none';
        }, 2200);
    </script>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

<!-- Breadcrumb -->
<div class="overflow-y-auto h-full px-6">
    <div class="flex justify-between items-center px-6 py-6 font-poppins">
        <h1 class="text-2xl">Invoice Confirmation</h1>
        <div class="text-sm">
            <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
            /
            <a class="text-black">Account Receivables</a>
            /
            <a href="receivables_ia.php" class="text-blue-600 hover:text-blue-600">Invoice Confirmation</a>
        </div>
    </div>

    <div class=" ml-6 mr-6 p-4 bg-white border border-gray-200 rounded-xl shadow flex flex-col gap-4">
        <div class="flex items-center gap-2">
            <i class="fas fa-clipboard text-xl text-violet-700 "></i>
            <h2 class="text-xl font-poppins text-black">Overview</h2>
        </div>
        <div class="flex overview-left overview-cards pt-4 pb-4 gap-8">
            <div class="flex-1 rounded-lg bg-purple-50 p-6 flex flex-col items-center justify-center">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-clipboard-list text-xl text-purple-600"></i>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Invoices</h3>
                </div>
                <p class="text-3xl font-bold text-purple-700"><?= $pendingCount ?></p>
            </div>
            <div class="flex-1 rounded-lg bg-blue-50 p-6 flex flex-col items-center justify-center">
                <div class="flex items-center gap-2 mb-2">
                    <h3 class="text-lg font-semibold text-gray-700">Approved This Month</h3>
                </div>
                <p class="text-3xl font-bold text-green-700"><?= $confirmedThisMonth ?></p>
            </div>
            <div class="flex-1 rounded-lg bg-orange-50 p-6 flex flex-col items-center justify-center">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-money-bill-wave text-xl text-red-500"></i>
                    <h3 class="text-lg font-semibold text-gray-700">Total Amount Due</h3>
                </div>
                <p class="text-3xl font-bold text-red-700">₱ <?= number_format($totalDue, 2) ?></p>
            </div>
        </div>
    </div>
 
    <!-- Main Content -->
    <div class="flex-1 bg-white p-6 h-full w-full">
        <div class="w-full">
            <div class="flex items-center justify-between">
                <div class="flex flex-wrap items-center gap-4 mb-4">
                    <div class="flex items-center gap-2">
                        <input
                        type="text"
                        id="searchInput"
                        class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400"
                        placeholder="Search here"
                        onkeyup="filterTable()" />
                    </div>
                    <div class="flex items-center space-x-2">
                        <label for="dueDate" class="font-semibold">Payment Due:</label>
                        <input
                            type="date"
                            id="dueDate"
                            class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm"
                            onchange="filterTable()" />
                    </div>
                </div>
                <button type="button" onclick="openAddReceivableModal()" class="bg-purple-600 text-white px-4 py-2 rounded-md shadow-md hover:bg-purple-700 hover:shadow-lg transition-all duration-200 text-sm font-medium">ADD NEW DETAILS</button>
            </div>

            <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                    <i class="far fa-file-alt text-xl"></i>
                    <h2 class="text-2xl font-poppins text-black">Pending Invoices</h2>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full table-auto bg-white mt-4">
                    <thead>
                        <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                            <th class="pl-12 px-4 py-2">Invoice ID</th>
                            <th class="px-4 py-2">Driver Name</th>
                            <th class="px-4 py-2">Description</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Payment Due</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 text-sm font-light" id="invoiceTableBody">
                        <?php
                        foreach ($rows as $row):
                            $fullyPaidDate= $row['fully_paid_date'] ? date('Y-m-d', strtotime($row['fully_paid_date'])) : '';
                            ?>
                            <tr class="hover:bg-violet-100" 
                                data-invoiceid="<?php echo htmlspecialchars($row['invoice_id']); ?>"
                                data-drivername="<?php echo htmlspecialchars($row['driver_name']); ?>"
                                data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                                data-amount="<?php echo htmlspecialchars($row['amount']); ?>"
                                data-duedate="<?php echo $fullyPaidDate; ?>">
                                <td class='pl-12 px-4 py-2'><?php echo $row['invoice_id'];?></td>
                                <td class='px-4 py-2'><?php echo $row['driver_name'];?></td>
                                <td class='px-4 py-2'><?php echo $row['description'];?></td>
                                <td class='px-4 py-2'>₱<?php echo number_format($row['amount'], 2);?></td>
                                <td class='px-4 py-2'><?php echo $fullyPaidDate;?></td>
                                <td class='pl-3 px-4 '>
                                    <form action='confirm_invoice.php' method='POST' class='inline-block' onsubmit='return confirm("Are you sure you want to confirm this invoice?")'>
                                        <input type='hidden' name='invoice_id' value='<?php echo $row['invoice_id']; ?>'>
                                        <button type='submit' class='font-semibold bg-green-100 text-green-700 px-2 hover:bg-green-500 hover:text-white rounded-full'>confirm</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
    </div>
</div>
<div id="addModal" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-[600px] relative">
        <h2 class="text-lg font-bold text-gray-800 mb-4 text-center">Add Receivables Details</h2>
        <form id="addReceivableForm" action="add_new_receivable_details.php" method="POST" enctype="multipart/form-data" class="grid gap-4 grid-cols-2">
            <div class="mb-4 col-span-2">
                <label class="block text-gray-700 mb-1" for="invoice_id">Invoice ID</label>
                <div class="flex items-center">
                    <span class="bg-gray-200 px-3 py-1 border border-r-0 border-gray-300 rounded-l-md">INV-</span>
                    <input type="text" id="invoice_id" name="invoice_id" 
                        value="<?php echo $invoiceIdValue; ?>" 
                        class="w-full px-2 py-1 border border-gray-300 rounded-r-md" required readonly>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="driver_name">Driver Name</label>
                <input type="text" id="driver_name" name="driver_name" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="description">Description</label>
                <input type="text" id="description" name="description" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="amount">Amount</label>
                <input type="number" id="amount" name="amount" class="w-full px-2 py-1 border border-gray-300 rounded-md" required step="0.01">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="fully_paid_date">Payment Due</label>
                <input type="date" id="fully_paid_date" name="fully_paid_date" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="col-span-2 flex justify-end gap-2 mt-4">
                <button type="submit" class="bg-violet-600 text-white hover:bg-purple-300 hover:text-violet-900 border border-violet-600 px-4 py-2 rounded-md">Save</button>
                <button type="button" onclick="closeAddReceivableModal()" class="bg-violet-100 text-violet-900 hover:bg-purple-300 hover:text-violet-900 border border-violet-900 px-4 py-2 rounded-md">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let table = document.querySelector("table tbody");
let allRows = Array.from(table.querySelectorAll("tr"));
let currentPage = 1;
const rowsPerPage = 10;

function generateInvoiceId() {
    const date = new Date().toISOString().slice(0,10).replace(/-/g,'');
    const rand = Math.floor(1000 + Math.random() * 9000);
    return `${date}-${rand}`;
}

function openAddReceivableModal() {
    // Generate a new invoice ID each time the modal opens
    const newInvoiceId = generateInvoiceId();
    document.getElementById('invoice_id').value = newInvoiceId;
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddReceivableModal() {
    document.getElementById('addModal').classList.add('hidden');
    // Reset form but keep the invoice ID
    const invoiceId = document.getElementById('invoice_id').value;
    document.getElementById('addReceivableForm').reset();
    document.getElementById('invoice_id').value = invoiceId;
}

function filterRows() {
    const searchInput = document.getElementById("searchInput").value.toLowerCase();
    const dueDate = document.getElementById("dueDate").value;
    
    return allRows.filter(row => {
        const invoiceId = row.getAttribute('data-invoiceid').toLowerCase();
        const driverName = row.getAttribute('data-drivername').toLowerCase();
        const description = row.getAttribute('data-desc').toLowerCase();
        const amount = row.getAttribute('data-amount').toLowerCase();
        const rowDate = row.getAttribute('data-duedate');
        
        // Fixed the search logic - removed the extra || operator
        const matchSearch = (
            invoiceId.includes(searchInput) ||
            driverName.includes(searchInput) ||
            description.includes(searchInput) ||
            amount.includes(searchInput)
        );
        
        const matchDate = (!dueDate || rowDate === dueDate);
        return matchSearch && matchDate;
    });
}

function displayData(page) {
    const filteredRows = filterRows();
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const paginatedRows = filteredRows.slice(start, end);
    
    // Hide all rows first
    allRows.forEach(row => row.style.display = 'none');
    
    // Show only the paginated rows
    paginatedRows.forEach(row => row.style.display = 'table-row');
    
    // Update pagination buttons
    document.getElementById("prevPage").disabled = (currentPage === 1);
    document.getElementById("nextPage").disabled = (end >= filteredRows.length);
    
    // Update page status
    const pageStatus = document.getElementById("pageStatus");
    const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
    pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
    
    // If no results found, show message
    if (filteredRows.length === 0) {
        const tbody = document.getElementById("invoiceTableBody");
        if (!tbody.querySelector('.no-results')) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results';
            noResultsRow.innerHTML = `<td colspan="6" class="text-center py-4">No records found</td>`;
            tbody.appendChild(noResultsRow);
        }
    } else {
        const noResults = document.querySelector('.no-results');
        if (noResults) noResults.remove();
    }
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
    const filteredRows = filterRows();
    if (currentPage * rowsPerPage < filteredRows.length) {
        currentPage++;
        displayData(currentPage);
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('addModal');
    if (event.target === modal) {
        closeAddReceivableModal();
    }
});

// Initialize on page load
window.onload = () => {
    allRows = Array.from(document.querySelectorAll("#invoiceTableBody > tr"));
    displayData(currentPage);
    
    // Set today's date as default for the due date field in the form
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('fully_paid_date').value = today;
    
    // Set minimum date to today for the due date field
    document.getElementById('fully_paid_date').min = today;
};
</script>
</body>
</html>