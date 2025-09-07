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

// Fetch all data from ar table
$sql = "SELECT * FROM ar WHERE from_receivable = 1 ORDER BY created_at DESC";
$result = $conn->query($sql);
$records = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { $records[] = $row; }
}
?>

<html>

<head><script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <!-- jsPDF and autotable for PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <title>Receivables Records</title>
    <link rel="icon" href="logo.png" type="img">
</head>

<body class="bg-white">

    <?php include('sidebar.php'); ?>

    <!-- Breadcrumb -->
    <div class="overflow-y-auto h-full px-6">
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Receivables Records</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Accounts Receivable</a>
                /
                <a href="receivables_records.php" class="text-blue-600 hover:text-blue-600">Receivables Records</a>
            </div>
        </div>
    
        <!-- Main Content -->
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

                        <label for="paidDate" class="font-semibold ml-2">Paid Date:</label>
                        <input
                        type="date"
                        id="paidDate"
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

                <!-- Table -->
                <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                    <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                        <i class="far fa-file-alt text-xl"></i>
                        <h2 class="text-2xl font-poppins text-black">Paid Accounts</h2>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full table-auto bg-white mt-4" id="recordsTable">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                    <th class="pl-12 px-4 py-2">Invoice ID</th>
                                    <th class="px-4 py-2">Driver Name</th>
                                    <th class="px-4 py-2">Description</th>
                                    <th class="px-4 py-2">Amount </th>
                                    <th class="px-4 py-2">Paid Date</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-900 text-sm font-light" id="recordsTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Pagination Controls -->
                <div class="mt-4 flex justify-between items-center">
                    <div id="pageStatus" class="text-gray-700 font-bold"></div>
                    <div class="flex">
                        <button
                            id="prevPage"
                            class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500"
                            onclick="prevPage()">Previous</button>
                        <button
                            id="nextPage"
                            class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500"
                            onclick="nextPage()">Next</button>
                    </div>
                </div>
                <div class="mt-6">
                    <canvas id="pdf-viewer" width="600" height="400"></canvas>
                </div>
            </div>
        </div>

<script>
    // Data from PHP
    const records = <?php echo json_encode($records); ?>;
    let filtered = records.slice();
    let PaymentMethod = 'all';
    let currentPage = 1;
    const rowsPerPage = 10;

    function matchesMode(row, mode) {
        if (mode === 'all') return true;
        const val = (row.payment_method || '').toLowerCase();
        return val === mode;
    }

    document.querySelectorAll('.mode-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.mode-tab').forEach(tab => tab.classList.remove('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold'));
            this.classList.add('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold');
            PaymentMethod = this.getAttribute('data-mode');
            filterTable();
        });
    });

    function renderTable(page) {
        const tbody = document.getElementById('recordsTableBody');
        tbody.innerHTML = '';
        let start = (page - 1) * rowsPerPage;
        let end = start + rowsPerPage;
        let paginated = filtered.slice(start, end);
        if (paginated.length === 0) {
            tbody.innerHTML = "<tr><td colspan='7' class='text-center py-4'>No records found</td></tr>";
        } else {
            paginated.forEach(row => {
                const invoiceId = row.invoice_reference;
                const driverName = row.driver_name || '';
                const description = row.description || '';
                const amount = '₱ ' + Number(row.amount_received).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const paymentMethod = row.payment_method || '';
                const paidDate = row.created_at ? (new Date(row.created_at )).toISOString().substring(0,10) : '';
                const rowData = encodeURIComponent(JSON.stringify(row));
                tbody.innerHTML += `<tr class="hover:bg-violet-100">
                    <td class="pl-12 py-2 px-4">${invoiceId}</td>
                    <td class="py-2 px-4">${driverName}</td>
                    <td class="py-2 px-4">${description}</td>
                    <td class="py-2 px-4">${amount}</td>
                    <td class="py-2 px-4">${paidDate}</td>
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
        const paidDate = document.getElementById("paidDate").value;
        filtered = records.filter(row => {
            const invoiceId = (row.invoice_id || '').toString().toLowerCase();
            const driverName = (row.driver_name || '').toString().toLowerCase();
            const description = (row.description || '').toString().toLowerCase();
            const amount = (row.amount || '').toString().toLowerCase();
            const paymentMethod = (row.payment_method || '').toString().toLowerCase();
            const rowDate = row.fully_paid_date ? (new Date(row.fully_paid_date)).toISOString().substring(0,10) : '';
            
            // Check if any field matches the search input
            let matchSearch = invoiceId.includes(searchInput) || 
                             driverName.includes(searchInput) || 
                             description.includes(searchInput) || 
                             amount.includes(searchInput) || 
                             paymentMethod.includes(searchInput);
            
            let matchDate = (!paidDate || rowDate === paidDate);
            let matchMode = matchesMode(row, PaymentMethod);
            
            return matchSearch && matchDate && matchMode;
        });
        currentPage = 1;
        renderTable(currentPage);
    }

    function prevPage() { if (currentPage > 1) { currentPage--; renderTable(currentPage); } }
    function nextPage() { if (currentPage * rowsPerPage < filtered.length) { currentPage++; renderTable(currentPage); } }

    window.onload = () => { filterTable(); };

    // --- Export PDF ---
    function exportPDF() {
        const { jsPDF } = window.jspdf;
        let doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
        let title = "Receivables Records";
        doc.setFontSize(18);
        doc.text(title, 40, 40);
        let headers = [["Invoice ID", "Driver Name", "Description", "Amount", "Payment Method", "Paid Date"]];
        let data = [];
        filtered.forEach(row => {
            data.push([
                row.invoice_id,
                row.driver_name || '',
                row.description || '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.payment_method || '',
                row.fully_paid_date ? (new Date(row.fully_paid_date)).toISOString().substring(0,10) : ''
            ]);
        });
        doc.autoTable({
            head: headers,
            body: data,
            startY: 60,
            theme: 'grid',
            headStyles: { fillColor: [44,62,80] }
        });
        doc.save('receivables_records.pdf');
    }

    // --- Export CSV ---
    function exportCSV() {
        let csvRows = [["Invoice ID", "Driver Name", "Description", "Amount", "Payment Method", "Paid Date"]];
        filtered.forEach(row => {
            csvRows.push([
                row.invoice_id,
                row.driver_name || '',
                row.description || '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.payment_method || '',
                row.fully_paid_date ? (new Date(row.fully_paid_date)).toISOString().substring(0,10) : ''
            ]);
        });
        let csvContent = csvRows.map(e => e.map(v => `"${(v+'').replace(/"/g,'""')}"`).join(",")).join("\n");
        let blob = new Blob([csvContent], { type: 'text/csv' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'receivables_records.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    // --- Export Excel ---
    function exportExcel() {
        let ws_data = [["Invoice ID", "Driver Name", "Description", "Amount", "Payment Method", "Paid Date"]];
        filtered.forEach(row => {
            ws_data.push([
                row.invoice_id,
                row.driver_name || '',
                row.description || '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.payment_method || '',
                row.fully_paid_date ? (new Date(row.fully_paid_date)).toISOString().substring(0,10) : ''
            ]);
        });
        let ws = XLSX.utils.aoa_to_sheet(ws_data);
        let wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Records");
        XLSX.writeFile(wb, "receivables_records.xlsx");
    }
</script>
</body>
</html>