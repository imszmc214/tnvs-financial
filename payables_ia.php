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

// Overview Metrics
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM accounts_payable WHERE status = 'pending'")->fetch_assoc()['total'];
$approvedThisMonth = $conn->query("
  SELECT COUNT(*) as total 
  FROM accounts_payable 
  WHERE status = 'approved' 
    AND MONTH(approval_date) = MONTH(CURRENT_DATE)
    AND YEAR(approval_date) = YEAR(CURRENT_DATE)
")->fetch_assoc()['total'];
$totalDue = $conn->query("
  SELECT SUM(amount) as total 
  FROM accounts_payable 
  WHERE status = 'pending'
")->fetch_assoc()['total'];

// Build Main Query
$sql = "SELECT * FROM accounts_payable WHERE status = 'pending'";
$sql .= " ORDER BY invoice_id DESC";
$result = $conn->query($sql);

// Get all rows for display
$rows = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Generate a new invoice code (numeric part only)
function generateRandomInvoiceCode() {
    // Check if we need to add a prefix or if it's already included
    $date = date('Ymd');
    $rand = mt_rand(1000, 9999);
    
    // Return just the numeric part without any prefix
    // The prefix should be added in payables.php when moving the invoice
    return $date . $rand;
}
$latestInvoiceCode = generateRandomInvoiceCode();
?>
<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <title>Invoice Approval</title>
    <link rel="icon" href="logo.png" type="img">
    <style>
    @media (max-width: 1024px) {
      .overview-flex { flex-direction: column !important; }
      .overview-left, .overview-right { width: 100% !important; }
      .overview-cards { flex-direction: column !important; }
      .overview-right { min-width: 0 !important; }
    }
    </style>
    <script>
        var nextInvoiceId = "<?= $latestInvoiceCode ?>";
    </script>
</head>
<body class="bg-white overflow-hidden">

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

<div class="overflow-y-auto h-full px-6">
    <div class="flex justify-between items-center px-6 py-6 font-poppins">
        <h1 class="text-2xl">Invoice Approval</h1>
        <div class="text-sm">
            <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
            /
            <a class="text-black">Accounts Payable</a>
            /
            <a href="payables_ia.php" class="text-blue-600 hover:text-blue-600">Invoice Approval</a>
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
                <p class="text-3xl font-bold text-green-700"><?= $approvedThisMonth ?></p>
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

    <div class="flex-1 bg-white p-6 h-full w-full">
        <div class="w-full">
            <div class="flex items-center justify-between">
                <form method="GET" action="payables_ia.php" class="flex flex-wrap items-center gap-4 mb-4">
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
                </form>
            </div>
            <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                    <i class="far fa-file-alt text-xl"></i>
                    <h2 class="text-2xl font-poppins text-black">Pending Invoices</h2>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full table-auto bg-white mt-4">
                    <thead>
                        <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                            <th class="pl-12 px-4 py-2">Invoice ID</th>
                            <th class="px-4 py-2">Department</th>
                            <th class="px-4 py-2">Account Name</th>
                            <th class="px-4 py-2">Mode of Payment</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Description</th>
                            <th class="px-4 py-2">Payment Due</th>
                            <th class="px-4 py-2">Invoice</th>
                            <th class="px-4 py-2">Action</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 text-sm font-light text-center" id="invoiceTableBody">
                        <?php
                        foreach ($rows as $row):
                            $paymentDue = $row['payment_due'] ? date('Y-m-d', strtotime($row['payment_due'])) : '';
                            ?>
                            <tr class="hover:bg-violet-100" 
                                data-mode="<?php echo strtolower($row['payment_method']); ?>"
                                data-invoiceid="<?php echo htmlspecialchars($row['invoice_id']); ?>"
                                data-acct="<?php echo htmlspecialchars($row['account_name']); ?>"
                                data-dept="<?php echo htmlspecialchars($row['department']); ?>"
                                data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                                data-duedate="<?php echo $paymentDue; ?>">
                                <td class='py-1 px-4'><?php echo $row['invoice_id'];?></td>
                                <td class='py-1 px-4'><?php echo $row['department'];?></td>
                                <td class='py-1 px-4'><?php echo $row['account_name'];?></td>
                                <td class='py-1 px-4'><?php echo $row['payment_method'];?></td>
                                <td class='py-1 px-4'>₱<?php echo number_format($row['amount'], 2);?></td>
                                <td class='py-1 px-4'><?php echo $row['description'] ?? '';?></td>
                                <td class='py-1 px-4'><?php echo $paymentDue;?></td>
                                <td class='py-1 px-4 text-center'>
                                    <?php
                                    if ($row['document']) {
                                        echo "<a href='view_pdf.php?file=".urlencode($row['document'])."' target='_blank' class='font-semibold text-blue-700 px-2 py-1 rounded hover:text-purple-600'>View File</a>";
                                    } else {
                                        echo "<span class='text-gray-400 italic'>No document available</span>";
                                    }
                                    ?>
                                </td>
                                <td class='pt-3 px-4 text-center'>
                                    <form action='confirm_payable.php' method='POST' class='inline-block' onsubmit='return confirm("Are you sure you want to confirm this invoice?")'>
                                        <input type='hidden' name='invoice_id' value='<?php echo $row['invoice_id']; ?>'>
                                        <button type='submit' class='text-green-500 px-2 py-1 rounded'><i class='fas fa-check'></i></button>
                                    </form>
                                    <form action='del_payable.php' method='POST' class='inline-block' onsubmit='return confirm("Are you sure you want to delete this invoice?")'>
                                        <input type='hidden' name='invoice_id' value='<?php echo $row['invoice_id']; ?>'>
                                        <button type='submit' class='text-red-500 px-2 py-1 rounded'><i class='fas fa-trash'></i></button>
                                    </form>
                                </td>
                                <td class='px-4 py-1 text-center'>
                                    <?php $jsonData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                    <button onclick='openDetailsModal(<?php echo $jsonData; ?>)' class='border rounded-full px-2 py-1 text-violet-700 border-violet-600 bg-violet-10  font-semibold'>View Details</button>
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
        <h2 class="text-lg font-bold text-gray-800 mb-4 text-center">Add Payable Details</h2>
        <form id="addPayableForm" action="add_new_payable_details.php" method="POST" enctype="multipart/form-data" class="grid gap-4 grid-cols-2">
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="invoice_id">Invoice ID</label>
                <input type="text" id="invoice_id" name="invoice_id" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
                <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                <select name="department" id="department" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                    <option value="" disabled selected>Select Department</option>
                    <option value="Administrative">Administrative</option>
                    <option value="Core-1">Core-1</option>
                    <option value="Core-2">Core-2</option>
                    <option value="Human Resource-1">Human Resource-1</option>
                    <option value="Human Resource-2">Human Resource-2</option>
                    <option value="Human Resource-3">Human Resource-3</option>
                    <option value="Human Resource-4">Human Resource-4</option>
                    <option value="Logistic-1">Logistic-1</option>
                    <option value="Logistic-2">Logistic-2</option>
                    <option value="Financial">Financials</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="account_name">Account Name</label>
                <input type="text" id="account_name" name="account_name" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
                <label for="payment_method" class="block text-sm font-medium text-gray-700">Mode of Payment</label>
                <select name="payment_method" id="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md" required onchange="toggleBankFields(), toggleEcashFields()">
                    <option value="" disabled selected>Select Mode</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Ecash">Ecash</option>
                    <option value="Cash">Cash</option>
                </select>
            </div>
            <div id="bankFields" class="hidden  col-span-2">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1" for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name" class="w-full px-2 py-1 border border-gray-300 rounded-md" >
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1" for="bank_account_name">Bank Account Name</label>
                    <input type="text" id="bank_account_name" name="bank_account_name" class="w-full px-2 py-1 border border-gray-300 rounded-md">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1" for="bank_account_number">Bank Account Number</label>
                    <input type="number" id="bank_account_number" name="bank_account_number" class="w-full px-2 py-1 border border-gray-300 rounded-md">
                </div>
            </div>
            <div id="ecashFields" class="hidden  col-span-2">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1" for="ecash_provider">Ecash Provider</label>
                    <input type="text" id="ecash_provider" name="ecash_provider" class="w-full px-2 py-1 border border-gray-300 rounded-md" >
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1" for="ecash_account_name">Ecash Account Name</label>
                    <input type="text" id="ecash_account_name" name="ecash_account_name" class="w-full px-2 py-1 border border-gray-300 rounded-md">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1" for="ecash_account_number">Ecash Account Number</label>
                    <input type="number" id="ecash_account_number" name="ecash_account_number" class="w-full px-2 py-1 border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="amount">Amount</label>
                <input type="number" id="amount" name="amount" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="description">Description</label>
                <input type="text" id="description" name="description" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="document">Document</label>
                <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="w-full px-2 py-1 border border-gray-300 rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 mb-1" for="payment_due">Payment Due</label>
                <input type="date" id="payment_due" name="payment_due" class="w-full px-2 py-1 border border-gray-300 rounded-md" required>
            </div>
            <div class="col-span-2 flex justify-end gap-2 mt-4">
                <button type="submit" class="bg-violet-600 text-white hover:bg-purple-300 hover:text-violet-900 border border-violet-600 px-4 py-2 rounded-md">Save</button>
                <button type="button" onclick="closeAddReceivableModal()" class="bg-violet-100 text-violet-900 hover:bg-purple-300 hover:text-violet-900 border border-violet-900 px-4 py-2 rounded-md">Cancel</button>
            </div>
        </form>
    </div>
</div>
<!-- Details Modal -->
<div id="detailsModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-[400px] relative">
    <h2 class="text-lg font-bold text-gray-800 mb-4 text-center">Invoice Details</h2>
    <div id="modalContent" class="text-gray-800"></div>
    <button onclick="closeDetailsModal()" class="mt-4 bg-violet-600 text-white px-4 py-2 rounded hover:bg-violet-700 w-full">Close</button>
  </div>
</div>

<script>
    let table = document.querySelector("table tbody");
    let allRows = Array.from(table.querySelectorAll("tr"));
    let currentPage = 1;
    const rowsPerPage = 10;

    let currentInvoiceId = null;

    function openAddModal() {
        currentInvoiceId = nextInvoiceId;
        document.getElementById('invoice_id').value = currentInvoiceId;
        document.getElementById('addModal').classList.remove('hidden');
    }

    document.getElementById('addPayableForm').addEventListener('submit', function(event) {
        event.preventDefault();
        const form = this;
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.redirected) {
                window.location = response.url;
            } else {
                alert('Error submitting the form.');
            }
        }).catch(() => {
            alert('Network error.');
        });
    });

    function closeAddReceivableModal() {
        document.getElementById('addModal').classList.add('hidden');
        currentInvoiceId = null;
    }

    function toggleBankFields() {
        const paymentMethod = document.getElementById("payment_method").value;
        const bankFields = document.getElementById("bankFields");
        const bankInputs = bankFields.querySelectorAll("input");
        if (paymentMethod === "Bank Transfer") {
            bankFields.classList.remove("hidden");
            bankInputs.forEach(input => input.required = true);
        } else {
            bankFields.classList.add("hidden");
            bankInputs.forEach(input => input.required = false);
            bankInputs.forEach(input => input.value = "");
        }
    }

    function toggleEcashFields() {
        const paymentMethod = document.getElementById("payment_method").value;
        const ecashFields = document.getElementById("ecashFields");
        const ecashInputs = ecashFields.querySelectorAll("input");
        if (paymentMethod === "Ecash") {
            ecashFields.classList.remove("hidden");
            ecashInputs.forEach(input => input.required = true);
        } else {
            ecashFields.classList.add("hidden");
            ecashInputs.forEach(input => input.required = false);
            ecashInputs.forEach(input => input.value = "");
        }
    }

    document.getElementById("payment_method").addEventListener("change", function() {
        toggleBankFields();
        toggleEcashFields();
    });

    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
    }

    function openDetailsModal(data) {
        let html = `
            <p><strong>Invoice ID:</strong> ${data.invoice_id}</p>
            <p><strong>Department:</strong> ${data.department}</p>
            <p><strong>Account Name:</strong> ${data.account_name}</p>
            <p><strong>Payment Method:</strong> ${data.payment_method}</p>
            <p><strong>Amount:</strong> ₱ ${parseFloat(data.amount).toLocaleString()}</p>
            <p><strong>Description:</strong> ${data.description}</p>
            <p><strong>Payment Due:</strong> ${data.payment_due}</p>
        `;
        if (data.payment_method === 'Bank Transfer') {
            html += `
                <p><strong>Bank Name:</strong> ${data.bank_name || ''}</p>
                <p><strong>Bank Account Name:</strong> ${data.bank_account_name || ''}</p>
                <p><strong>Bank Account Number:</strong> ${data.bank_account_number || ''}</p>
            `;
        } else if (data.payment_method === 'Ecash') {
            html += `
                <p><strong>Ecash Provider:</strong> ${data.ecash_provider || ''}</p>
                <p><strong>Ecash Account Name:</strong> ${data.ecash_account_name || ''}</p>
                <p><strong>Ecash Account Number:</strong> ${data.ecash_account_number || ''}</p>
            `;
        }
        if (data.document) {
            html += `<p><strong>Document:</strong> <a href="view_pdf.php?file=${encodeURIComponent(data.document)}" target="_blank" class="text-blue-500 underline">View File</a></p>`;
        }
        document.getElementById('modalContent').innerHTML = html;
        document.getElementById('detailsModal').classList.remove('hidden');
    }

    function filterRows() {
        const searchInput = document.getElementById("searchInput").value.toLowerCase();
        const dueDate = document.getElementById("dueDate").value;
        return allRows.filter(row => {
            const invoiceId = row.getAttribute('data-invoiceid').toLowerCase();
            const accountName = row.getAttribute('data-acct').toLowerCase();
            const department = row.getAttribute('data-dept').toLowerCase();
            const description = row.getAttribute('data-desc').toLowerCase();
            const rowDate = row.getAttribute('data-duedate');
            
            const matchSearch = (
                invoiceId.includes(searchInput) ||
                accountName.includes(searchInput) ||
                department.includes(searchInput) ||
                description.includes(searchInput)
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
        
        document.getElementById("prevPage").disabled = currentPage === 1;
        document.getElementById("nextPage").disabled = end >= filteredRows.length;
        
        const pageStatus = document.getElementById("pageStatus");
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
        pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
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

    window.onload = () => {
        allRows = Array.from(document.querySelectorAll("#invoiceTableBody > tr"));
        displayData(currentPage);
    };
</script>
</body>
</html>