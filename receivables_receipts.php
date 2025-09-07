<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 
$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// First, update any existing AR records that don't have a description but should
$update_sql = "UPDATE ar 
               INNER JOIN account_receivable ON ar.invoice_reference = account_receivable.invoice_id 
               SET ar.description = CONCAT('Payment for INV ', ar.invoice_reference, ' - ', account_receivable.description)
               WHERE ar.from_receivable = 1 AND (ar.description IS NULL OR ar.description = '')";
$conn->query($update_sql);

// Get all AR receipts (from receivables) with description
$sql = "SELECT ar.*, ar.description as receipt_description, 
               ar.created_at, 
               account_receivable.description as invoice_description,
               account_receivable.amount_paid,
               account_receivable.amount
        FROM ar 
        LEFT JOIN account_receivable ON ar.invoice_reference = account_receivable.invoice_id
        WHERE ar.from_receivable = 1 
        ORDER BY ar.created_at DESC";
$result = $conn->query($sql);

// Replace the row processing logic with this:
$receipts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { 
        // Generate unique receipt ID if not already set or if it's the same as invoice ID
        if (empty($row['receipt_id']) || $row['receipt_id'] === $row['invoice_reference']) {
            // Generate new receipt ID
            $receipt_prefix = "RCPT-";
            $timestamp = time();
            $random = rand(1000, 9999);
            $new_receipt_id = $receipt_prefix . $timestamp . '-' . $random;
            
            // Update the database with the generated receipt ID
            $update_sql = "UPDATE ar SET receipt_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $new_receipt_id, $row['id']);
            $stmt->execute();
            $stmt->close();
            
            $row['receipt_id'] = $new_receipt_id;
        }
        
        // Ensure the description is set (use invoice description if receipt description is empty)
        if (empty($row['receipt_description']) && !empty($row['invoice_description'])) {
            $formatted_description = "Payment for INV " . $row['invoice_reference'] . " - " . $row['invoice_description'];
            $update_desc_sql = "UPDATE ar SET description = ? WHERE id = ?";
            $stmt = $conn->prepare($update_desc_sql);
            $stmt->bind_param("si", $formatted_description, $row['id']);
            $stmt->execute();
            $stmt->close();
            
            $row['receipt_description'] = $formatted_description;
        }
        
        // Check if THIS SPECIFIC receipt is collected (not based on invoice status)
        $row['is_collected'] = ($row['status'] === 'collected');
        
        $receipts[] = $row; 
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
    <title>Receivables Receipts</title>
    <link rel="icon" href="logo.png" type="img">
</head>

<body class="bg-white">
    <?php include('sidebar.php'); ?>

    <div class="overflow-y-auto h-full px-6">
        <!-- Breadcrumb -->
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Receivables Receipts</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Collection</a>
                /
                <a href="receivables_receipts.php" class="text-blue-600 hover:text-blue-600">Receivables Receipts</a>
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

                        <label for="dateReceived" class="font-semibold ml-2">Date Received:</label>
                        <input
                        type="date"
                        id="dateReceived"
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
                        <h2 class="text-2xl font-poppins text-black">Receivables Receipts</h2>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full table-auto bg-white mt-4">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                    <th class="pl-12 w-1/6 py-2">Receipt ID</th>
                                    <th class="py-2">Driver Name</th>
                                    <th class="py-2">Description</th>
                                    <th class="pl-12 py-2">Amount Received</th>
                                    <th class="py-2">Date Received</th>
                                    <th class="py-2">Invoice Reference</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Action</th>
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

    <!-- Collect Receipt Modal -->
    <div id="collectModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-96">
            <h3 class="text-xl font-semibold text-blue-900 mb-4">Collect Receipt</h3>
            <form method="POST" action="collect_receipt.php">
                <input type="hidden" name="receipt_id" id="collect_receipt_id">
                <input type="hidden" name="invoice_id" id="collect_invoice_id">
                <input type="hidden" name="amount_receive" id="collect_amount_receive">
                <div class="mb-4">
                    <p class="text-gray-700">Are you sure you want to collect this receipt?</p>
                    <p class="font-semibold mt-2">Receipt ID: <span id="collect_receipt_id_display"></span></p>
                    <p class="font-semibold">Amount: ₱ <span id="collect_amount_display"></span></p>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeCollectModal()" class="bg-gray-500 text-white px-4 py-2 rounded mr-2">Cancel</button>
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Collect</button>
                </div>
            </form>
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
        tbody.innerHTML = "<tr><td colspan='8' class='text-center py-4'>No records found</td></tr>";
    } else {
        paginated.forEach(row => {
            const receiptId = row.receipt_id || '';
            const driverName = row.driver_name || '';
            const description = row.receipt_description || row.description || '';
            const amount = Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const createdAt = row.created_at ? (new Date(row.created_at)).toISOString().substring(0,10) : '';
            const invoiceRef = row.invoice_reference || '';
            const isCollected = row.is_collected || false;
            
            let statusBadge = isCollected ? 
                '<span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Collected</span>' :
                '<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded">Pending</span>';
            
            let actionButton = isCollected ? 
                '<button class="bg-gray-300 text-gray-600 px-3 py-1 rounded text-xs cursor-not-allowed" disabled>Collected</button>' :
                `<button onclick="openCollectModal('${receiptId}', '${invoiceRef}', ${row.amount_received})" class="bg-green-500 text-white px-3 py-1 rounded text-xs hover:bg-green-600">Collect</button>`;
            
            tbody.innerHTML += `<tr class="hover:bg-violet-100">
                <td class="pl-12 py-2">${receiptId}</td>
                <td class="py-2">${driverName}</td>
                <td class="py-2">${description}</td>
                <td class="pl-12 py-2">₱ ${amount}</td>
                <td class="py-2">${createdAt}</td>
                <td class="py-2">${invoiceRef}</td>
                <td class="py-2">${statusBadge}</td>
                <td class="py-2">${actionButton}</td>
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
    const dateReceived = document.getElementById("dateReceived").value;
    
    filtered = receipts.filter(row => {
        const driverName = (row.driver_name || '').toLowerCase();
        const description = (row.receipt_description || row.description || '').toLowerCase();
        const receiptId = (row.receipt_id || '').toLowerCase();
        const invoiceRef = (row.invoice_reference || '').toLowerCase();
        const rowDate = row.created_at ? (new Date(row.created_at)).toISOString().substring(0,10) : '';
        
        let matchSearch = driverName.includes(searchInput) || 
                         description.includes(searchInput) || 
                         receiptId.includes(searchInput) || 
                         invoiceRef.includes(searchInput);
        let matchDate = (!dateReceived || rowDate === dateReceived);
        
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

function openCollectModal(receiptId, invoiceId, amount) {
    document.getElementById("collect_receipt_id").value = receiptId;
    document.getElementById("collect_invoice_id").value = invoiceId;
    document.getElementById("collect_amount_receive").value = amount;
    document.getElementById("collect_receipt_id_display").textContent = receiptId;
    document.getElementById("collect_amount_display").textContent = amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById("collectModal").classList.remove("hidden");
}

function closeCollectModal() {
    document.getElementById("collectModal").classList.add("hidden");
}

// --- Export PDF ---
function exportPDF() {
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
    let title = "Receivables Receipts";
    doc.setFontSize(18);
    doc.text(title, 40, 40);
    
    let headers = [["Receipt ID", "Driver Name", "Description", "Amount Received", "Date Received", "Invoice Reference", "Status"]];
    let data = [];
    
    filtered.forEach(row => {
        const status = row.is_collected ? "Collected" : "Pending";
        data.push([
            row.receipt_id || '',
            row.driver_name || '',
            row.receipt_description || row.description || '',
            '₱ ' + Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
            row.created_at ? (new Date(row.created_at)).toISOString().substring(0,10) : '',
            row.invoice_reference || '',
            status
        ]);
    });
    
    doc.autoTable({
        head: headers,
        body: data,
        startY: 60,
        theme: 'grid',
        headStyles: { fillColor: [44,62,80] }
    });
    
    doc.save('receivables_receipts.pdf');
}

// --- Export CSV ---
function exportCSV() {
    let csvRows = [["Receipt ID","Driver Name","Description","Amount Received","Date Received","Invoice Reference","Status"]];
    
    filtered.forEach(row => {
        const status = row.is_collected ? "Collected" : "Pending";
        csvRows.push([
            row.receipt_id || '',
            row.driver_name || '',
            row.receipt_description || row.description || '',
            Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
            row.created_at ? (new Date(row.created_at)).toISOString().substring(0,10) : '',
            row.invoice_reference || '',
            status
        ]);
    });
    
    let csvContent = csvRows.map(e => e.map(v => `"${(v+'').replace(/"/g,'""')}"`).join(",")).join("\n");
    let blob = new Blob([csvContent], { type: 'text/csv' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'receivables_receipts.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// --- Export Excel ---
function exportExcel() {
    let ws_data = [["Receipt ID","Driver Name","Description","Amount Received","Date Received","Invoice Reference","Status"]];
    
    filtered.forEach(row => {
        const status = row.is_collected ? "Collected" : "Pending";
        ws_data.push([
            row.receipt_id || '',
            row.driver_name || '',
            row.receipt_description || row.description || '',
            Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}),
            row.created_at ? (new Date(row.created_at)).toISOString().substring(0,10) : '',
            row.invoice_reference || '',
            status
        ]);
    });
    
    let ws = XLSX.utils.aoa_to_sheet(ws_data);
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Receipts");
    XLSX.writeFile(wb, "receivables_receipts.xlsx");
}

window.onload = () => { 
    filterTable(); 
};
</script>
</body>
</html>