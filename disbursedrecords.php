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
$conn->query("ALTER TABLE dr ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_ids'])) {
    $ids = $_POST['archive_ids'];
    $ids = array_map('intval', $ids);
    $ids_str = implode(',', $ids);
    $conn->query("UPDATE dr SET archived = 1 WHERE id IN ($ids_str)");
    echo json_encode(['success' => true]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    $archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $paidDate = isset($_GET['paidDate']) ? $conn->real_escape_string($_GET['paidDate']) : '';
    $dueDate = isset($_GET['dueDate']) ? $conn->real_escape_string($_GET['dueDate']) : '';
    $mode = isset($_GET['mode']) ? $conn->real_escape_string($_GET['mode']) : 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $rowsPerPage = 10;
    $offset = ($page - 1) * $rowsPerPage;

    $where = "WHERE archived = $archived";
    if ($search) {
        $where .= " AND (reference_id LIKE '%$search%' OR account_name LIKE '%$search%' OR requested_department LIKE '%$search%' OR expense_categories LIKE '%$search%')";
    }
    if ($paidDate) {
        $where .= " AND DATE(disbursed_at) = '$paidDate'";
    }
    if ($dueDate) {
        $where .= " AND DATE(payment_due) = '$dueDate'";
    }
    if ($mode !== 'all') {
        if ($mode === 'bank') {
            $where .= " AND (mode_of_payment LIKE '%bank%')";
        } else {
            $where .= " AND (LOWER(mode_of_payment) = '$mode')";
        }
    }
    $count_sql = "SELECT COUNT(*) as total FROM dr $where";
    $result_count = $conn->query($count_sql);
    $total = $result_count->fetch_assoc()['total'];
    $sql = "SELECT * FROM dr $where ORDER BY id DESC LIMIT $offset, $rowsPerPage";
    $result = $conn->query($sql);
    $rows = [];
    while($row = $result->fetch_assoc()) $rows[] = $row;

    echo json_encode([
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $rowsPerPage)
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Disbursed Records</title>
    <link rel="icon" href="logo.png" type="img">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="bg-white">
<?php include('sidebar.php'); ?>
<div class="overflow-y-auto h-full px-6">
    <div class="flex justify-between items-center px-6 py-6 font-poppins">
        <h1 class="text-2xl">Disbursed Records</h1>
        <div class="text-sm">
            <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a> /
            <a class="text-black">Disbursement</a> /
            <a href="disbursedrecords.php" class="text-blue-600 hover:text-blue-600">Disbursed Records</a>
        </div>
    </div>
    <div class="flex-1 bg-white p-6 h-full w-full">
        <div class="w-full">
            <!-- Flex row for MOP tabs left, active/archive right -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300">
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full border-b-4 border-yellow-400 text-yellow-600 font-semibold" data-mode="all">ALL</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="cash">CASH</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="bank">BANK</button>
                    <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="ecash">ECASH</button>
                </div>
                <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300">
                    <button id="tab-active" type="button" class="tab-btn px-4 py-2 rounded-t-full border-b-4 border-violet-400 text-violet-600 font-semibold" data-archived="0">Active</button>
                    <button id="tab-archived" type="button" class="tab-btn px-4 py-2 rounded-t-full text-gray-900 hover:text-violet-500 hover:border-b-2 hover:border-violet-300" data-archived="1">Archived</button>
                </div>
            </div>
            <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="text" id="searchInput" class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Search here" />
                    <label for="paidDate" class="font-semibold ml-2">Paid Date:</label>
                    <input type="date" id="paidDate" class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm" />
                    <label for="dueDate" class="font-semibold ml-2">Due Date:</label>
                    <input type="date" id="dueDate" class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm" />
                    <button id="archive-btn" type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded focus:outline-none transition-all duration-300">
                        <i class="fas fa-archive"></i> Archive
                    </button>
                </div>
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
            <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                    <i class="far fa-file-alt text-xl"></i>
                    <h2 class="text-2xl font-poppins text-black">Disbursement Records</h2>
                </div>
                <form id="archiveForm">
                <div class="overflow-x-auto w-full transition-all duration-500">
                    <table class="w-full table-auto bg-white mt-4" id="recordsTable">
                        <thead>
                            <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                                <th class="px-4 py-2 w-10"></th>
                                <th class="pl-2 pr-6 py-2">Reference ID</th>
                                <th class="px-6 py-2">Account Name</th>
                                <th class="px-6 py-2">Department</th>
                                <th class="px-6 py-2">Payment Mode</th>
                                <th class="px-6 py-2">Expense Categories</th>
                                <th class="px-6 py-2">Amount</th>
                                <th class="px-6 py-2">Payment Due</th>
                                <th class="px-6 py-2">Disbursed At</th>
                            </tr>
                        </thead>
                        <tbody id="recordsTableBody" class="text-gray-900 text-sm font-light transition-all duration-500"></tbody>
                    </table>
                </div>
                </form>
            </div>
            <div class="flex items-center mt-2 gap-8">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="select-all-bottom" class="hidden transition-all duration-500" />
                    <label for="select-all-bottom" class="text-gray-700 text-sm hidden" id="select-all-bottom-label">Select All</label>
                </div>
                <div id="archive-controls" class="hidden flex items-center gap-2 ml-4">
                    <span class="text-gray-700">Archive selected items?</span>
                    <button type="button" id="archive-yes" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">Yes</button>
                    <button type="button" id="archive-cancel" class="bg-gray-400 hover:bg-gray-500 text-white px-3 py-1 rounded">Cancel</button>
                </div>
            </div>
            <div class="flex items-center justify-between mt-2">
                <div id="pageStatus" class="text-gray-700 font-bold"></div>
                <div class="flex">
                    <button id="prevPage" type="button" class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500 transition-all duration-300">Previous</button>
                    <button id="nextPage" type="button" class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500 transition-all duration-300">Next</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Toast -->
    <div id="toast-success" class="fixed top-6 right-6 z-50 hidden items-center w-full max-w-xs p-4 mb-4 text-green-800 bg-green-100 rounded-lg shadow transition-opacity duration-500" role="alert">
        <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
        <div class="ml-3 text-sm font-medium" id="toast-message"></div>
    </div>
</div>
<script>
let archived = 0;
let mode = 'all';
let page = 1, pages = 1;
let search = "", paidDate = "", dueDate = "";
let currentRows = [];
let archiveMode = false;

function showToast(msg) {
    const toast = document.getElementById('toast-success');
    document.getElementById('toast-message').innerText = msg;
    toast.classList.remove('hidden');
    toast.style.opacity = 1;
    setTimeout(() => { toast.style.opacity = 0; }, 2000);
    setTimeout(() => { toast.classList.add('hidden'); }, 2600);
}

function renderRows(rows) {
    let tbody = document.getElementById('recordsTableBody');
    tbody.innerHTML = "";
    if (rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4">No records found</td></tr>`;
    } else {
        rows.forEach(row => {
            tbody.innerHTML += `<tr class="hover:bg-violet-100 transition-all duration-300">
                ${archiveMode && !archived ? `<td class="px-4 py-2 w-10"><input type="checkbox" class="row-checkbox" value="${row.id}" /></td>` : `<td class="px-4 py-2 w-10"></td>`}
                <td class="pl-2 pr-6 py-2">${row.reference_id}</td>
                <td class="px-6 py-2">${row.account_name}</td>
                <td class="px-6 py-2">${row.requested_department}</td>
                <td class="px-6 py-2">${row.mode_of_payment}</td>
                <td class="px-6 py-2">${row.expense_categories}</td>
                <td class="px-6 py-2">₱${Number(row.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                <td class="px-6 py-2">${row.payment_due ? row.payment_due.substring(0,10) : ''}</td>
                <td class="px-6 py-2">${row.disbursed_at ? row.disbursed_at.substring(0,10) : ''}</td>
            </tr>`;
        });
    }
    updateCheckboxMode();
}

function loadTable() {
    fetch(`disbursedrecords.php?ajax=1&archived=${archived}&search=${encodeURIComponent(search)}&paidDate=${paidDate}&dueDate=${dueDate}&mode=${mode}&page=${page}`)
        .then(resp => resp.json())
        .then(data => {
            currentRows = data.rows;
            pages = data.pages;
            renderRows(currentRows);
            document.getElementById("pageStatus").innerText = `Page ${page} of ${pages}`;
        });
}

// Filters
document.getElementById('searchInput').addEventListener('input', function() {
    search = this.value;
    page = 1;
    loadTable();
});
document.getElementById('paidDate').addEventListener('change', function() {
    paidDate = this.value;
    page = 1;
    loadTable();
});
document.getElementById('dueDate').addEventListener('change', function() {
    dueDate = this.value;
    page = 1;
    loadTable();
});

// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(tab =>
            tab.classList.remove('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold')
        );
        this.classList.add('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold');
        archived = parseInt(this.getAttribute('data-archived'));
        page = 1;
        document.getElementById('archive-btn').style.display = archived ? 'none' : '';
        document.getElementById('archive-controls').classList.add('hidden');
        archiveMode = false;
        loadTable();
    });
});

// Mode of Payment Tabs
document.querySelectorAll('.mode-tab').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.mode-tab').forEach(tab => tab.classList.remove('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold'));
        this.classList.add('border-b-4', 'border-yellow-400', 'text-yellow-600', 'font-semibold');
        mode = this.getAttribute('data-mode');
        page = 1;
        loadTable();
    });
});

// Pagination
document.getElementById('prevPage').addEventListener('click', function() {
    if (page > 1) { page--; loadTable(); }
});
document.getElementById('nextPage').addEventListener('click', function() {
    if (page < pages) { page++; loadTable(); }
});

// Archive button logic
function updateCheckboxMode() {
    const show = archiveMode && !archived;
    document.getElementById('select-all-bottom').classList.toggle('hidden', !show);
    document.getElementById('select-all-bottom-label').classList.toggle('hidden', !show);
    document.getElementById('archive-controls').classList.toggle('hidden', !show);
}
document.getElementById('archive-btn').addEventListener('click', function() {
    archiveMode = true;
    renderRows(currentRows); // rerender so checkboxes appear!
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
});
document.getElementById('archive-cancel').addEventListener('click', function() {
    archiveMode = false;
    renderRows(currentRows); // rerender so checkboxes disappear!
    document.getElementById('select-all-bottom').checked = false;
});
document.getElementById('archive-yes').addEventListener('click', function() {
    const ids = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return showToast('Please select items to archive');
    fetch('disbursedrecords.php', {
        method: "POST",
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: "archive_ids[]=" + ids.join('&archive_ids[]=')
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            showToast('Records archived successfully!');
            archiveMode = false;
            page = 1;
            loadTable();
        }
    });
});

// Select all bottom
document.getElementById('select-all-bottom').addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checked);
});

window.onload = function() { loadTable(); };

// --- Export functions ---
function getExportData() {
    const headers = ["Reference ID","Account Name","Department","Payment Mode","Expense Categories","Amount","Payment Due","Disbursed At"];
    const data = currentRows.map(row => [
        row.reference_id,
        row.account_name,
        row.requested_department,
        row.mode_of_payment,
        row.expense_categories,
        Number(row.amount),
        row.payment_due ? row.payment_due.substring(0,10) : '',
        row.disbursed_at ? row.disbursed_at.substring(0,10) : ''
    ]);
    return {headers, data};
}
function exportPDF() {
    const {headers, data} = getExportData();
    const doc = new window.jspdf.jsPDF('l', 'pt', 'a4');
    doc.setFontSize(15);
    doc.text("Disbursed Records", 40, 40);
    doc.autoTable({
        head: [headers],
        body: data,
        startY: 60,
        theme: 'striped',
        headStyles: { fillColor: [44,62,80], textColor: 255, fontStyle: 'bold' },
        bodyStyles: { fontSize: 10 },
        margin: {left: 40, right: 40}
    });
    doc.save("disbursed-records.pdf");
}
function exportCSV() {
    const {headers, data} = getExportData();
    let csvRows = [headers];
    data.forEach(row => {
        csvRows.push(row.map(v => `"${(v+'').replace(/"/g,'""')}"`));
    });
    let csvContent = csvRows.map(e => e.join(",")).join("\n");
    let blob = new Blob([csvContent], { type: 'text/csv' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'disbursed-records.csv';
    a.click();
    URL.revokeObjectURL(url);
}
function exportExcel() {
    const {headers, data} = getExportData();
    let ws_data = [headers, ...data];
    let ws = XLSX.utils.aoa_to_sheet(ws_data);
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Records");
    XLSX.writeFile(wb, "disbursed-records.xlsx");
}
</script>
</body>
</html>