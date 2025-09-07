<?php
session_start();

// Check if user is logged in
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

// Fetch all data from dr table for JS client-side filtering
$sql = "SELECT * FROM dr WHERE status = 'disbursed' AND amount > 0 ORDER BY id DESC";
$result = $conn->query($sql);
$records = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { $records[] = $row; }
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
    <title>Payables Records</title>
    <link rel="icon" href="logo.png" type="img">
</head>
<body class="bg-white">
    <?php include('sidebar.php'); ?>

    <!-- Breadcrumb -->
    <div class="overflow-y-auto h-full px-6">
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Payable Records</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Accounts Payable</a>
                /
                <a href="payables_records.php" class="text-blue-600 hover:text-blue-600">Payable Records</a>
            </div>
        </div>
    
        <!-- Main Content -->
        <div class="flex-1 bg-white p-6 h-full w-full">
            <div class="w-full">
                <!-- Mode of Payment Tabs (JS) -->
                <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300 mb-4">
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full border-b-4 border-yellow-400 text-yellow-600 font-semibold" data-mode="all">ALL</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="cash">CASH</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="bank">BANK</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="ecash">ECASH</button>
                </div>
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
                                    <th class="px-4 py-2">Department</th>
                                    <th class="px-4 py-2">Account Name</th>
                                    <th class="px-4 py-2">Amount</th>
                                    <th class="px-4 py-2">Mode of Payment</th>
                                    <th class="px-4 py-2">Paid Date</th>
                                    <th class="px-4 py-2"></th>
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

    <div id="detailsModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
            <h2 class="text-xl font-bold mb-4">Invoice Details</h2>
            <div id="modalContent" class="text-gray-800">
                <!-- Details will be inserted here -->
            </div>
            <button onclick="closeModal()" class="mt-4 bg-red-500 text-white px-4 py-2 rounded">Close</button>
        </div>
    </div>

    <script>
    // Data from PHP
    const records = <?php echo json_encode($records); ?>;
    let filtered = records.slice();
    let modeOfPayment = 'all';
    let currentPage = 1;
    const rowsPerPage = 10;

    function matchesMode(row, mode) {
        if (mode === 'all') return true;
        const val = (row.mode_of_payment || '').toLowerCase();
        if (mode === 'bank') return val.includes('bank');
        return val === mode;
    }

    document.querySelectorAll('.mode-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.mode-tab').forEach(tab => tab.classList.remove('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold'));
            this.classList.add('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold');
            modeOfPayment = this.getAttribute('data-mode');
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
                const invoiceId = "INV-" + row.reference_id;
                const dept = row.requested_department || '';
                const acct = row.account_name || '';
                const amt = '₱ ' + Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const mop = row.mode_of_payment || '';
                const paidDate = row.disbursed_at ? (new Date(row.disbursed_at)).toISOString().substring(0,10) : '';
                const rowData = encodeURIComponent(JSON.stringify(row));
                tbody.innerHTML += `<tr class="hover:bg-violet-100">
                    <td class="pl-12 py-2 px-4">${invoiceId}</td>
                    <td class="py-2 px-4">${dept}</td>
                    <td class="py-2 px-4">${acct}</td>
                    <td class="py-2 px-4">${amt}</td>
                    <td class="py-2 px-4">${mop}</td>
                    <td class="py-2 px-4">${paidDate}</td>
                    <td class="py-2 px-4">
                        <button onclick='openPaymentModal("${rowData}")' class='rounded-full px-2 py-1 text-violet-700 border border-violet-600 bg-violet-10  font-semibold'>View Details</button>
                    </td>
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
            if (!matchesMode(row, modeOfPayment)) return false;
            const department = (row.requested_department || '').toLowerCase();
            const account = (row.account_name || '').toLowerCase();
            const mop = (row.mode_of_payment || '').toLowerCase();
            const rowDate = row.disbursed_at ? (new Date(row.disbursed_at)).toISOString().substring(0,10) : '';
            let matchSearch = department.includes(searchInput) || account.includes(searchInput) || mop.includes(searchInput);
            let matchDate = (!paidDate || rowDate === paidDate);
            return matchSearch && matchDate;
        });
        currentPage = 1;
        renderTable(currentPage);
    }

    function prevPage() { if (currentPage > 1) { currentPage--; renderTable(currentPage); } }
    function nextPage() { if (currentPage * rowsPerPage < filtered.length) { currentPage++; renderTable(currentPage); } }

    window.onload = () => { filterTable(); };

    function openPaymentModal(rowDataStr) {
        const rowData = JSON.parse(decodeURIComponent(rowDataStr));
        const mop = (rowData.mode_of_payment || '').toLowerCase();
        let extraDetails = ``;
        if (mop === "ecash") {
            extraDetails = `
                <p><strong>Ecash Provider:</strong> ${rowData.ecash_provider || ''}</p>
                <p><strong>Ecash Account Name:</strong> ${rowData.ecash_account_name || ''}</p>
                <p><strong>Ecash Account Number:</strong> ${rowData.ecash_account_number || ''}</p>
            `;
        } else if (mop.includes("bank")) {
            extraDetails = `
                <p><strong>Bank Name:</strong> ${rowData.bank_name || ''}</p>
                <p><strong>Bank Account Name:</strong> ${rowData.bank_account_name || ''}</p>
                <p><strong>Bank Account Number:</strong> ${rowData.bank_account_number || ''}</p>
            `;
        } 
        document.getElementById("modalContent").innerHTML = `
            <p><strong>Invoice ID:</strong> INV-${rowData.reference_id}</p>
            <p><strong>Department:</strong> ${rowData.requested_department}</p>
            <p><strong>Account Name:</strong> ${rowData.account_name}</p>
            <p><strong>Payment Method:</strong> ${rowData.mode_of_payment || ''}</p>
            <p><strong>Amount Paid:</strong> ₱${parseFloat(rowData.amount).toFixed(2)}</p>
            <p><strong>Payment Due:</strong> ${rowData.payment_due || ''}</p>
            <p><strong>Paid Date:</strong> ${rowData.disbursed_at ? (new Date(rowData.disbursed_at)).toISOString().substring(0,10) : ''}</p>
            ${extraDetails}
            ${rowData.document ? `<p><strong>Document:</strong> <a href="view_pdf.php?file=${encodeURIComponent(rowData.document)}" target="_blank" class="text-blue-500 underline">View Document</a></p>` : ''}
        `;
        document.getElementById("detailsModal").classList.remove("hidden");
    }
    function closeModal() {
        document.getElementById("detailsModal").classList.add("hidden");
    }

    // --- Export PDF ---
    function exportPDF() {
        const { jsPDF } = window.jspdf;
        let doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
        let title = "Payables Records";
        doc.setFontSize(18);
        doc.text(title, 40, 40);
        let headers = [["Invoice ID", "Department", "Account Name", "Amount", "Mode of Payment", "Paid Date"]];
        let data = [];
        filtered.forEach(row => {
            data.push([
                "INV-" + row.reference_id,
                row.requested_department || '',
                row.account_name || '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.mode_of_payment || '',
                row.disbursed_at ? (new Date(row.disbursed_at)).toISOString().substring(0,10) : ''
            ]);
        });
        doc.autoTable({
            head: headers,
            body: data,
            startY: 60,
            theme: 'grid',
            headStyles: { fillColor: [44,62,80] }
        });
        doc.save('payables_records.pdf');
    }

    // --- Export CSV ---
    function exportCSV() {
        let csvRows = [["Invoice ID", "Department", "Account Name", "Amount", "Mode of Payment", "Paid Date"]];
        filtered.forEach(row => {
            csvRows.push([
                "INV-" + row.reference_id,
                row.requested_department || '',
                row.account_name || '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.mode_of_payment || '',
                row.disbursed_at ? (new Date(row.disbursed_at)).toISOString().substring(0,10) : ''
            ]);
        });
        let csvContent = csvRows.map(e => e.map(v => `"${(v+'').replace(/"/g,'""')}"`).join(",")).join("\n");
        let blob = new Blob([csvContent], { type: 'text/csv' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'payables_records.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    // --- Export Excel ---
    function exportExcel() {
        let ws_data = [["Invoice ID", "Department", "Account Name", "Amount", "Mode of Payment", "Paid Date"]];
        filtered.forEach(row => {
            ws_data.push([
                "INV-" + row.reference_id,
                row.requested_department || '',
                row.account_name || '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.mode_of_payment || '',
                row.disbursed_at ? (new Date(row.disbursed_at)).toISOString().substring(0,10) : ''
            ]);
        });
        let ws = XLSX.utils.aoa_to_sheet(ws_data);
        let wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Records");
        XLSX.writeFile(wb, "payables_records.xlsx");
    }
    </script>
</body>
</html>