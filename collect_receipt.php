<?php
session_start();

// Check if the user is logged in
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

// Get data from POST if this is a collection request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'], $_POST['invoice_id'], $_POST['amount_receive'])) {
    $receipt_id = $_POST['receipt_id'];
    $invoice_id = $_POST['invoice_id'];
    $amount_receive = floatval($_POST['amount_receive']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update the amount_paid in account_receivable by adding the amount_receive
        $updateReceivableQuery = "UPDATE account_receivable 
                                 SET amount_paid = amount_paid + ?, updated_at = NOW()
                                 WHERE invoice_id = ?";
        $stmt = $conn->prepare($updateReceivableQuery);
        $stmt->bind_param("ds", $amount_receive, $invoice_id);
        $stmt->execute();
        $stmt->close();

        // 2. Check if the invoice is now fully paid
        $checkPaidQuery = "SELECT amount, amount_paid FROM account_receivable WHERE invoice_id = ?";
        $stmt = $conn->prepare($checkPaidQuery);
        $stmt->bind_param("s", $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $stmt->close();

        $status = 'partial';
        if ($invoice && ($invoice['amount_paid'] >= $invoice['amount'])) {
            $status = 'paid';
            
            // Update status to 'paid' if fully paid
            $updateStatusQuery = "UPDATE account_receivable SET status = 'paid' WHERE invoice_id = ?";
            $stmt = $conn->prepare($updateStatusQuery);
            $stmt->bind_param("s", $invoice_id);
            $stmt->execute();
            $stmt->close();
        }

        // 3. Update ONLY the specific AR receipt to mark as collected
        $updateARQuery = "UPDATE ar SET status = 'collected', collected_at = NOW() 
                         WHERE receipt_id = ? AND invoice_reference = ?";
        $stmt = $conn->prepare($updateARQuery);
        $stmt->bind_param("ss", $receipt_id, $invoice_id);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        die("Error processing collection: " . $e->getMessage());
    }

    // Redirect back to the receipts page
    header("Location: receivables_receipts.php");
    exit();
}

// Fetch and display collected receipts
$receiptQuery = "SELECT ar.*, account_receivable.invoice_id 
                 FROM ar 
                 INNER JOIN account_receivable ON ar.invoice_reference = account_receivable.invoice_id
                 WHERE ar.status = 'collected' 
                 AND ar.from_receivable = 1 
                 ORDER BY ar.collected_at DESC";
$receiptResult = mysqli_query($conn, $receiptQuery);

$receipts = [];
if ($receiptResult && mysqli_num_rows($receiptResult) > 0) {
    while ($receipt = mysqli_fetch_assoc($receiptResult)) {
        $receipts[] = $receipt;
    }
}
?>

<html>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <!-- jsPDF and autotable for PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <title>Collected Receipts</title>
    <link rel="icon" href="logo1.png" type="img">
    <style>
    .tab-active {
      border-bottom: 2px solid #7c3aed;
      color: #7c3aed !important;
      font-weight: bold;
    }
    </style>
</head>

<body class="bg-white">
    <?php include('sidebar.php'); ?>

    <div class="overflow-y-auto h-full px-6">
        <!-- Breadcrumb -->
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Collected Receipts</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Collection</a>
                /
                <a href="collect_receipt.php" class="text-blue-600 hover:text-blue-600">Collected Receipts</a>
            </div>
        </div>

        <div class="flex-1 bg-white p-6 h-full w-full">
            <div class="w-full">
                <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
                    <!-- Left: Search + Date -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <input
                        type="text"
                        id="searchInput"
                        class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400"
                        placeholder="Search here"
                        onkeyup="filterTable()" />

                        <label for="dateCollected" class="font-semibold ml-2">Date Collected:</label>
                        <input
                        type="date"
                        id="dateCollected"
                        class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm"
                        onchange="filterTable()" />
                    </div>

                    <!-- Right: Export Buttons -->
                    <div class="flex items-center gap-2">
                        <button onclick="exportPDF()" class="bg-red-500 text-white px-3 py-2 rounded-lg flex items-center gap-2 hover:bg-red-700" title="Export PDF">
                        <i class="fas fa-file-pdf"></i>
                        </button>
                        <button onclick="exportCSV()" class="bg-green-500 text-white px-3 py-2 rounded-lg flex items-center gap-2 hover:bg-green-700" title="Export CSV">
                        <i class="fas fa-file-csv"></i>
                        </button>
                        <button onclick="exportExcel()" class="bg-blue-500 text-white px-3 py-2 rounded-lg flex items-center gap-2 hover:bg-blue-700" title="Export Excel">
                        <i class="fas fa-file-excel"></i>
                        </button>
                    </div>
                </div>

                <!-- Main content area -->
                <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                    <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                        <i class="far fa-file-alt text-xl"></i>
                        <h2 class="text-2xl font-poppins text-black">Collected Receipts</h2>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full table-auto bg-white mt-4">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                    <th class="pl-12 w-1/6 py-2">Receipt ID</th>
                                    <th class="py-2">Driver Name</th>
                                    <th class="py-2">Description</th>
                                    <th class="pl-12 py-2">Amount Received</th>
                                    <th class="py-2">Date Collected</th>
                                    <th class="py-2">Invoice Reference</th>
                                </tr>
                            </thead>
                            <tbody id="receiptsTableBody" class="text-gray-900 text-sm font-light">
                                <!-- Populated by JS -->
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

<script>
const receipts = <?php echo json_encode($receipts); ?>;
let filtered = receipts.slice();
let currentPage = 1;
const rowsPerPage = 10;

function renderTable(page) {
    const tbody = document.getElementById('receiptsTableBody');
    tbody.innerHTML = '';
    let start = (page - 1) * rowsPerPage;
    let end = start + rowsPerPage;
    let paginated = filtered.slice(start, end);
    
    if (paginated.length === 0) {
        tbody.innerHTML = "<tr><td colspan='6' class='text-center py-4'>No records found</td></tr>";
    } else {
        paginated.forEach(row => {
            const receiptId = row.receipt_id || '';
            const driverName = row.driver_name || '';
            const description = row.description || '';
            const amount = Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const collectedAt = row.collected_at ? (new Date(row.collected_at)).toISOString().substring(0,10) : '';
            const invoiceRef = row.invoice_reference || '';
            
            tbody.innerHTML += `<tr class="hover:bg-violet-100">
                <td class="pl-12 py-2">${receiptId}</td>
                <td class="py-2">${driverName}</td>
                <td class="py-2">${description}</td>
                <td class="pl-12 py-2">₱ ${amount}</td>
                <td class="py-2">${collectedAt}</td>
                <td class="py-2">${invoiceRef}</td>
            </tr>`;
        });
    }
    
    document.getElementById("prevPage").disabled = currentPage === 1;
    document.getElementById("nextPage").disabled = end >= filtered.length;
    const pageStatus = document.getElementById("pageStatus");
    const totalPages = Math.max(1, Math.ceil(filtered.length / rowsPerPage));
    pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
}

function filterTable() {
    const searchInput = document.getElementById("searchInput").value.toLowerCase();
    const dateCollected = document.getElementById("dateCollected").value;
    
    filtered = receipts.filter(row => {
        const driverName = (row.driver_name || '').toLowerCase();
        const description = (row.description || '').toLowerCase();
        const receiptId = (row.receipt_id || '').toLowerCase();
        const invoiceRef = (row.invoice_reference || '').toLowerCase();
        const rowDate = row.collected_at ? (new Date(row.collected_at)).toISOString().substring(0,10) : '';
        
        let matchSearch = driverName.includes(searchInput) || 
                         description.includes(searchInput) || 
                         receiptId.includes(searchInput) || 
                         invoiceRef.includes(searchInput);
        let matchDate = (!dateCollected || rowDate === dateCollected);
        
        return matchSearch && matchDate;
    });
    
    currentPage = 1;
    renderTable(currentPage);
}

function prevPage() { 
    if (currentPage > 1) { 
        currentPage--; 
        renderTable(currentPage); 
    } 
}

function nextPage() { 
    if (currentPage * rowsPerPage < filtered.length) { 
        currentPage++; 
        renderTable(currentPage); 
    } 
}

// --- Export PDF ---
function exportPDF() {
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
    let title = "Collected Receipts";
    doc.setFontSize(18);
    doc.text(title, 40, 40);
    
    let headers = [["Receipt ID", "Driver Name", "Description", "Amount Received", "Date Collected", "Invoice Reference"]];
    let data = [];
    
    filtered.forEach(row => {
        data.push([
            row.receipt_id || '',
            row.driver_name || '',
            row.description || '',
            '₱ ' + Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
            row.collected_at ? (new Date(row.collected_at)).toISOString().substring(0,10) : '',
            row.invoice_reference || ''
        ]);
    });
    
    doc.autoTable({
        head: headers,
        body: data,
        startY: 60,
        theme: 'grid',
        headStyles: { fillColor: [44,62,80] }
    });
    
    doc.save('collected_receipts.pdf');
}

// --- Export CSV ---
function exportCSV() {
    let csvRows = [["Receipt ID","Driver Name","Description","Amount Received","Date Collected","Invoice Reference"]];
    
    filtered.forEach(row => {
        csvRows.push([
            row.receipt_id || '',
            row.driver_name || '',
            row.description || '',
            Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
            row.collected_at ? (new Date(row.collected_at)).toISOString().substring(0,10) : '',
            row.invoice_reference || ''
        ]);
    });
    
    let csvContent = csvRows.map(e => e.map(v => `"${(v+'').replace(/"/g,'""')}"`).join(",")).join("\n");
    let blob = new Blob([csvContent], { type: 'text/csv' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'collected_receipts.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// --- Export Excel ---
function exportExcel() {
    let ws_data = [["Receipt ID","Driver Name","Description","Amount Received","Date Collected","Invoice Reference"]];
    
    filtered.forEach(row => {
        ws_data.push([
            row.receipt_id || '',
            row.driver_name || '',
            row.description || '',
            Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}),
            row.collected_at ? (new Date(row.collected_at)).toISOString().substring(0,10) : '',
            row.invoice_reference || ''
        ]);
    });
    
    let ws = XLSX.utils.aoa_to_sheet(ws_data);
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Collected Receipts");
    XLSX.writeFile(wb, "collected_receipts.xlsx");
}

window.onload = () => { 
    filterTable(); 
};
</script>
</body>
</html>