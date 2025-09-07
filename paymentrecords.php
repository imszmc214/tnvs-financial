<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page if not logged in
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

// Build base query
$sql = "SELECT * FROM collections ORDER BY payment_date DESC"; 
$result = $conn->query($sql);
$records = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { 
        $records[] = $row; 
    }
}
$conn->close();
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
    <title>Payment Records</title>
     <link rel="icon" href="logo.png" type="img">
</head>

<body class="bg-white">
    <?php include('sidebar.php'); ?>

    <!-- Breadcrumb -->
    <div class="overflow-y-auto h-full px-6">
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Payment Records</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Collection</a>
                /
                <a href="paymentrecords.php" class="text-blue-600 hover:text-blue-600">Payment Records</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 bg-white p-6 h-full w-full">
            <div class="w-full">
                 <!-- Mode of Payment Tabs (JS) -->
                <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300 mb-4">
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full border-b-4 border-yellow-400 text-yellow-600 font-semibold" data-mode="all">ALL</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="cash">CASH</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="credit">CREDIT</button>
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
                        <h2 class="text-2xl font-poppins text-black">Passenger Payments</h2>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full table-auto bg-white mt-4" id="recordsTable">
                            <thead>
                                <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                <th class="pl-12 px-4 py-2">Payment ID</th>
                                <th class="px-4 py-2">Passenger Name</th>
                                <th class="px-4 py-2">Ticket Number</th>
                                <th class="px-4 py-2">Payment Date</th>
                                <th class="px-4 py-2">Amount Paid</th>
                                <th class="px-4 py-2">Payment Method</th>
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
    console.log('Records loaded:', records); // Debug line
    let filtered = records.slice();
    let modeOfPayment = 'all';
    let currentPage = 1;
    const rowsPerPage = 10;

    function matchesMode(row, mode) {
        if (mode === 'all') return true;
        const val = (row.mode_of_payment || '').toLowerCase();
        if (mode === 'credit') return val.includes('credit') || val.includes('bank');
        return val === mode;
    }

    document.querySelectorAll('.mode-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.mode-tab').forEach(tab => {
                tab.classList.remove('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold');
                tab.classList.add('text-gray-900', 'hover:text-yellow-500', 'hover:border-b-2', 'hover:border-yellow-300');
            });
            this.classList.add('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold');
            this.classList.remove('text-gray-900', 'hover:text-yellow-500', 'hover:border-b-2', 'hover:border-yellow-300');
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
            tbody.innerHTML = "<tr><td colspan='6' class='text-center py-4'>No records found</td></tr>";
        } else {
            paginated.forEach(row => {
                const paymentId = row.payment_id;
                const passengerName = row.passenger_name || '';
                const ticketNumber = row.ticket_number || '';
                const paymentDate = row.payment_date ? (new Date(row.payment_date)).toISOString().substring(0,10) : '';
                const amount = '₱ ' + Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const mop = row.mode_of_payment || '';
                
                tbody.innerHTML += `<tr class="hover:bg-violet-100">
                    <td class="pl-12 py-2 px-4">${paymentId}</td>
                    <td class="py-2 px-4">${passengerName}</td>
                    <td class="py-2 px-4">${ticketNumber}</td>
                    <td class="py-2 px-4">${paymentDate}</td>
                    <td class="py-2 px-4">${amount}</td>
                    <td class="py-2 px-4">${mop}</td>
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

        filtered = records.filter(row => {
            if (!matchesMode(row, modeOfPayment)) return false;
            
            const paymentId = (row.payment_id || '').toString().toLowerCase();
            const passengerName = (row.passenger_name || '').toLowerCase();
            const ticketNumber = (row.ticket_number || '').toString().toLowerCase();
            const mop = (row.mode_of_payment || '').toLowerCase();
            const amount = (row.amount || '').toString().toLowerCase();
            const rowDate = row.payment_date ? (new Date(row.payment_date)).toISOString().substring(0,10) : '';
            
            let matchSearch = paymentId.includes(searchInput) || 
                             passengerName.includes(searchInput) || 
                             ticketNumber.includes(searchInput) || 
                             mop.includes(searchInput) ||
                             amount.includes(searchInput) ||
                             rowDate.includes(searchInput);
            return matchSearch;
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
        let title = "Payment Records";
        doc.setFontSize(18);
        doc.text(title, 40, 40);
        let headers = [["Payment ID", "Passenger Name", "Ticket Number", "Payment Date", "Amount Paid", "Mode of Payment"]];
        let data = [];
        filtered.forEach(row => {
            data.push([
                row.payment_id,
                row.passenger_name || '',
                row.ticket_number || '',
                row.payment_date ? (new Date(row.payment_date)).toISOString().substring(0,10) : '',
                '₱ ' + Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.mode_of_payment || '',
            ]);
        });
        doc.autoTable({
            head: headers,
            body: data,
            startY: 60,
            theme: 'grid',
            headStyles: { fillColor: [44,62,80] }
        });
        doc.save('paymentrecords.pdf');
    }

    // --- Export CSV ---
    function exportCSV() {
        let csvRows = [["Payment ID", "Passenger Name", "Ticket Number", "Payment Date", "Amount Paid", "Mode of Payment"]];
        filtered.forEach(row => {
            csvRows.push([
                row.payment_id,
                row.passenger_name || '',
                row.ticket_number || '',
                row.payment_date ? (new Date(row.payment_date)).toISOString().substring(0,10) : '',
                Number(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                row.mode_of_payment || '',
            ]);
        });
        let csvContent = csvRows.map(e => e.map(v => `"${(v+'').replace(/"/g,'""')}"`).join(",")).join("\n");
        let blob = new Blob([csvContent], { type: 'text/csv' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'paymentrecords.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    // --- Export Excel ---
    function exportExcel() {
        let ws_data = [["Payment ID", "Passenger Name", "Ticket Number", "Payment Date", "Amount Paid", "Mode of Payment"]];
        filtered.forEach(row => {
            ws_data.push([
                row.payment_id,
                row.passenger_name || '',
                row.ticket_number || '',
                row.payment_date ? (new Date(row.payment_date)).toISOString().substring(0,10) : '',
                Number(row.amount),
                row.mode_of_payment || '',
            ]);
        });
        let ws = XLSX.utils.aoa_to_sheet(ws_data);
        let wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Records");
        XLSX.writeFile(wb, "paymentrecords.xlsx");
    }

    // Initialize the table when page loads
    window.onload = () => { 
        filterTable(); 
    };
</script>

</body>

</html>