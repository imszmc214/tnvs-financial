<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Token-based authentication
$token = $_GET['token'] ?? $_POST['token'] ?? null;
if (!$token) {
    header("HTTP/1.1 401 Unauthorized");
    die("Token required");
}

$servername = "localhost";
$usernameDB = "financial";
$passwordDB = "UbrdRDvrHRAyHiA]";
$dbname = "financial_db";

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Validate token and get department information
$stmt = $conn->prepare("
    SELECT department, token_name 
    FROM department_tokens 
    WHERE token = ? AND expires_at > NOW() AND is_active = 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.1 401 Unauthorized");
    die("Invalid or expired token");
}

$token_data = $result->fetch_assoc();
$department = $token_data['department'];
$token_name = $token_data['token_name'];

// For form submissions, we'll get the requestor name from the form
$requestor_name = $_POST['account_name'] ?? 'Department User';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_type'])) {
    $type = $_POST['request_type'];
    $reference_id = $_POST['reference_id'] ?? '';
    $account_name = $_POST['account_name'] ?? '';
    $department = $department; // Use token-authenticated department
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_due = $_POST['payment_due'] ?? '';
    $mode_of_payment = $_POST['mode_of_payment'] ?? '';
    $status = 'pending';
    $created_at = date('Y-m-d H:i:s');
    $extra_fields = [];
    $document = '';

    $time_period = (isset($_POST['time_period']) && in_array($type, ['budget', 'petty_cash'])) ? $_POST['time_period'] : null;

    if ($mode_of_payment === 'ecash') {
        $extra_fields['ecash_provider'] = $_POST['ecash_provider'] ?? '';
        $extra_fields['ecash_account_name'] = $_POST['ecash_account_name'] ?? '';
        $extra_fields['ecash_account_number'] = $_POST['ecash_account_number'] ?? '';
        $extra_fields['bank_name'] = $extra_fields['bank_account_name'] = $extra_fields['bank_account_number'] = '';
    } elseif ($mode_of_payment === 'bank') {
        $extra_fields['bank_name'] = $_POST['bank_name'] ?? '';
        $extra_fields['bank_account_name'] = $_POST['bank_account_name'] ?? '';
        $extra_fields['bank_account_number'] = $_POST['bank_account_number'] ?? '';
        $extra_fields['ecash_provider'] = $extra_fields['ecash_account_name'] = $extra_fields['ecash_account_number'] = '';
    } else {
        $extra_fields = array_fill_keys([
            'ecash_provider','ecash_account_name','ecash_account_number',
            'bank_name','bank_account_name','bank_account_number'
        ], '');
    }

    $mode_of_payment = $_POST['mode_of_payment'] ?? '';

    // Map to proper case
    switch ($mode_of_payment) {
        case 'cash':
            $mode_of_payment = 'Cash';
            break;
        case 'ecash':
            $mode_of_payment = 'Ecash';
            break;
        case 'bank':
            $mode_of_payment = 'Bank Transfer';
            break;
        default:
            $mode_of_payment = 'Cash'; // fallback, or handle error
    }

    // --- File Upload ---
    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    $uploadDir = 'C:/xampp/uploads/';
    if (!empty($_FILES['document']['name'])) {
        $fileName = basename($_FILES['document']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedTypes)) {
            $safeName = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", "", $fileName);
            $targetPath = $uploadDir . $safeName;
            if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
                $document = $safeName;
            }
        }
    }

    // --- Insert logic for each table ---
    if ($type == 'budget') {
        $stmt = $conn->prepare("
            INSERT INTO budget_request (
                reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, time_period, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, status, created_at, token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssdsssssssssssss",
            $reference_id, $account_name, $department, $mode_of_payment, $category, $amount, $description, $document,
            $time_period, $payment_due, $extra_fields['bank_name'], $extra_fields['bank_account_name'], $extra_fields['bank_account_number'],
            $extra_fields['ecash_provider'], $extra_fields['ecash_account_name'], $extra_fields['ecash_account_number'], $status, $created_at, $token
        );
    } elseif ($type == 'petty_cash') {
        $stmt = $conn->prepare("
            INSERT INTO pettycash (
                reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, time_period, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, created_at, token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssdssssssssssss",
            $reference_id, $account_name, $department, $mode_of_payment, $category, $amount, $description, $document,
            $time_period, $payment_due, $extra_fields['bank_name'], $extra_fields['bank_account_name'], $extra_fields['bank_account_number'],
            $extra_fields['ecash_provider'], $extra_fields['ecash_account_name'], $extra_fields['ecash_account_number'], $created_at, $token
        );
    } elseif ($type == 'payable') {
        $amount_paid = 0;
        $approval_date = null;
        $updated_at = $created_at;

        // MATCHES accounts_payable COLUMN ORDER
        $stmt = $conn->prepare("
            INSERT INTO accounts_payable (
                invoice_id, department, account_name, payment_method, document,
                description, amount, amount_paid, payment_due, status, approval_date,
                created_at, updated_at, bank_name, bank_account_name, bank_account_number,
                ecash_provider, ecash_account_name, ecash_account_number, token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssddssssssssssss",
            $reference_id, $department, $account_name, $mode_of_payment, $document,
            $description, $amount, $amount_paid, $payment_due, $status, $approval_date,
            $created_at, $updated_at, $extra_fields['bank_name'], $extra_fields['bank_account_name'],
            $extra_fields['bank_account_number'], $extra_fields['ecash_provider'],
            $extra_fields['ecash_account_name'], $extra_fields['ecash_account_number'], $token
        );

    } elseif ($type == 'emergency') {
        $requested_at = $created_at;
        $stmt = $conn->prepare("
            INSERT INTO pa (
                reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document,
                payment_due, requested_at, bank_name, bank_account_number, bank_account_name,
                ecash_provider, ecash_account_name, ecash_account_number, from_payable, token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $from_payable = 1;
        $stmt->bind_param(
            "sssssdssssssssssis",
            $reference_id, $account_name, $department, $mode_of_payment, $category, $amount, $description, $document,
            $payment_due, $requested_at, $extra_fields['bank_name'], $extra_fields['bank_account_number'], $extra_fields['bank_account_name'],
            $extra_fields['ecash_provider'], $extra_fields['ecash_account_name'], $extra_fields['ecash_account_number'], $from_payable, $token
        );
    } else {
        echo json_encode(['success'=>false,'msg'=>'Invalid request type.']);
        exit;
    }

    if (!$stmt) {
        echo json_encode(['success'=>false, 'msg'=>'Prepare failed: '.$conn->error]);
        exit;
    }

    $typeLabels = [
        'payable'     => 'AP',
        'petty_cash'  => 'Petty Cash',
        'budget'      => 'Budget Request',
        'emergency'   => 'Emergency Disburse'
    ];

    $displayType = $typeLabels[$type] ?? ucfirst($type);

    if ($stmt->execute()) {
        $notifMsg = "New {$type} request submitted.<br>Reference ID: $reference_id<br>Department: $department";
        $notifStmt = $conn->prepare("INSERT INTO notifications (message, request_type, department, token) VALUES (?, ?, ?, ?)");
        $notifStmt->bind_param("ssss", $notifMsg, $displayType, $department, $token);
        $notifStmt->execute();
        echo json_encode(['success'=>true, 'msg'=>'Request submitted successfully.']);
    } else {
        echo json_encode(['success'=>false, 'msg'=>'Error: '.$stmt->error]);
    }
    exit;
}
?>
<html>
<head>
    <title>Request Portal - <?= htmlspecialchars($token_name) ?></title>
    <meta charset="UTF-8">
    <link rel="icon" href="logo.png" type="img">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        .tab-active { border-bottom: 4px solid #7c3aed; color: #7c3aed !important; background: #ede9fe; }
        .tab-btn:not(.tab-active):hover { background: #f3f4f6; }
        .fade-in { animation: fadein .5s; }
        @keyframes fadein { from { opacity:0; transform: translateY(16px);} to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="bg-white-100 overflow-hidden">
<?php 
// Include a simplified sidebar that works with tokens
include('sidebar.php'); 
?>
<div class="flex h-screen">
    <div class="flex flex-1">

        <!-- Center Main Content -->
        <main class="flex-1 px-10 py-8 overflow-y-auto">
            <!-- Breadcrumb -->
            <div class="flex justify-between items-center px-6 pb-6 -mt-2 font-poppins">
                <h1 class="text-2xl -ml-4"> Financial Request Portal</h1>
                <div class="text-sm">
                    <a href="dashboard.php?token=<?= $token ?>" class="text-black hover:text-blue-600">Home</a>
                    /
                    <a href="request_portal.php?token=<?= $token ?>" class="text-blue-600 hover:text-blue-600">Financial Request Portal</a>
                </div>
            </div>
            <!-- Request TYPE TABS (horizontal & on top) -->
            <div class="mb-4 flex flex-row gap-2 items-end">
                <button class="tab-btn tab-active py-3 px-8 font-semibold text-lg transition-all" data-type="budget">
                    <i class="fas fa-clipboard-list mr-2"></i> Budget Request
                </button>
                <button class="tab-btn py-3 px-8 font-semibold text-lg transition-all" data-type="petty_cash">
                    <i class="fas fa-wallet mr-2"></i> Petty Cash
                </button>
                <button class="tab-btn py-3 px-8 font-semibold text-lg transition-all" data-type="payable">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> Payable
                </button>
                <button class="tab-btn py-3 px-8 font-semibold text-lg transition-all" data-type="emergency">
                    <i class="fas fa-ambulance mr-2"></i> Emergency Disbursement
                </button>
            </div>
            <!-- Add New Prompt -->
            <div class="bg-white rounded-xl shadow-md p-8 mb-6 flex flex-col items-center justify-center">
                <div class="text-center text-gray-700 font-bold text-2xl mb-2">Add new request?</div>
                <button id="addRequestBtn" class="bg-violet-700 hover:bg-violet-800 text-white rounded-full px-8 py-3 flex items-center mt-2 text-lg shadow-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Add New Request
                </button>
            </div>
            <!-- Tabs: Recent Requests / Request History -->
            <div class="flex flex-row items-end justify-between mb-2">
                <div class="flex gap-2">
                    <button id="recentTab" class="tab-tab px-6 py-2 border-b-4 border-violet-700 text-violet-800 font-bold">Recent Requests</button>
                    <button id="historyTab" class="tab-tab px-6 py-2 text-gray-600 hover:text-violet-700">Request History</button>
                </div>
                <form onsubmit="return false;" class="flex items-center gap-2">
                    <input id="searchBox" type="text" class="px-3 py-2 border border-gray-300 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Search requests..."/>
                    <i class="fas fa-search text-gray-500"></i>
                </form>
            </div>
            
            <!-- Table: will be dynamically loaded -->
            <div id="requestsTableContainer" class="bg-white p-4 rounded-xl shadow-md fade-in"></div>
                <div class="mt-6">
                <canvas id="pdf-viewer" width="600" height="400"></canvas>
            </div>

            <!-- Dynamic Form Modal -->
            <div id="requestFormModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center hidden">
                <div class="bg-white rounded-lg shadow-xl p-8 w-[450px] relative fade-in overflow-y-auto max-h-[90vh]">
                    <button class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-xl" onclick="closeForm()"><i class="fas fa-times"></i></button>
                    <h2 class="text-xl font-bold mb-4 text-center" id="formTitle">New Request</h2>
                    <form id="dynamicRequestForm" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="request_type" id="request_type">
                        <input type="hidden" name="token" value="<?= $token ?>">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Reference ID</label>
                            <input type="text" name="reference_id" id="reference_id" class="w-full px-3 py-2 border rounded-lg bg-gray-100 font-mono" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Requestor Name</label>
                            <input type="text" name="account_name" id="account_name" class="w-full px-3 py-2 border rounded-lg" value="" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Requesting Department</label>
                            <input type="text" class="w-full px-3 py-2 border rounded-lg bg-gray-100" value="<?= htmlspecialchars($department) ?>" readonly>
                            <input type="hidden" name="department" value="<?= htmlspecialchars($department) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Expense Category</label>
                            <input type="text" name="category" id="category" class="w-full px-3 py-2 border rounded-lg" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Description</label>
                            <input type="text" name="description" id="description" class="w-full px-3 py-2 border rounded-lg" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Document Upload</label>
                            <input type="file" name="document" id="document" class="w-full px-3 py-2 border rounded-lg" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Amount</label>
                            <input type="number" name="amount" id="amount" min="1" step="0.01" class="w-full px-3 py-2 border rounded-lg" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Payment Due Date</label>
                            <input type="date" name="payment_due" id="payment_due" class="w-full px-3 py-2 border rounded-lg" required>
                        </div>
                        <!-- Time Period for Budget and Petty Cash -->
                        <div class="mb-3" id="timePeriodGroup" style="display:none;">
                            <label class="block text-sm font-semibold mb-1">Time Period</label>
                            <select name="time_period" id="time_period" class="w-full px-3 py-2 border rounded-lg">
                                <option value="">Select Period</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <!-- Mode of Payment -->
                        <div class="mb-3">
                            <label class="block text-sm font-semibold mb-1">Mode of Payment</label>
                            <div class="flex gap-4 mt-1">
                                <label class="flex items-center gap-1"><input type="radio" name="mode_of_payment" value="cash" checked onchange="togglePaymentFields()"> Cash</label>
                                <label class="flex items-center gap-1"><input type="radio" name="mode_of_payment" value="ecash" onchange="togglePaymentFields()"> eCash</label>
                                <label class="flex items-center gap-1"><input type="radio" name="mode_of_payment" value="bank" onchange="togglePaymentFields()"> Bank</label>
                            </div>
                        </div>
                        <!-- eCash fields -->
                        <div class="mb-3" id="ecashFields" style="display:none;">
                            <label class="block text-xs mb-1">eCash Provider</label>
                            <input type="text" name="ecash_provider" id="ecash_provider" class="w-full px-3 py-2 border rounded-lg">
                            <label class="block text-xs mb-1 mt-2">eCash Account Name</label>
                            <input type="text" name="ecash_account_name" id="ecash_account_name" class="w-full px-3 py-2 border rounded-lg">
                            <label class="block text-xs mb-1 mt-2">eCash Account Number</label>
                            <input type="text" name="ecash_account_number" id="ecash_account_number" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <!-- Bank fields -->
                        <div class="mb-3" id="bankFields" style="display:none;">
                            <label class="block text-xs mb-1">Bank Name</label>
                            <input type="text" name="bank_name" id="bank_name" class="w-full px-3 py-2 border rounded-lg">
                            <label class="block text-xs mb-1 mt-2">Bank Account Name</label>
                            <input type="text" name="bank_account_name" id="bank_account_name" class="w-full px-3 py-2 border rounded-lg">
                            <label class="block text-xs mb-1 mt-2">Bank Account Number</label>
                            <input type="text" name="bank_account_number" id="bank_account_number" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div class="flex gap-2 justify-end mt-6">
                            <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded" onclick="closeForm()">Cancel</button>
                            <button type="submit" class="bg-violet-700 hover:bg-violet-800 text-white px-4 py-2 rounded flex items-center gap-1"><i class="fas fa-paper-plane"></i> Submit</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Toast -->
            <div id="toast" class="fixed top-8 right-8 px-6 py-3 rounded-lg bg-green-600 text-white shadow-lg z-[9999] hidden font-semibold"></div>
        </main>
    
        <!-- Notification Panel Wrapper -->
        <div id="resizablePanel" class="relative">
            <aside id="notificationAside" class="fixed right-0 h-[90vh] w-[320px] bg-white border-l border-gray-200 shadow-sm flex flex-col py-6 px-4 transition-transform duration-300 ease-in-out translate-x-full [&.visible]:translate-x-0 z-30">
                <div id="notificationIcon" class="absolute top-6 -left-[50px] z-40 bg-purple-600 text-white w-[50px] h-[50px] rounded-l-full flex items-center justify-center cursor-pointer shadow-md transition-transform duration-300 ease-in-out">
                    <i class="fas fa-bell"></i>
                    <!-- Badge -->
                    <div id="notificationBadge"
                        class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold hidden">
                        0
                    </div>
                </div>
                <!-- Header -->
                <div class="text-xl font-bold mb-4 flex gap-2 items-center">
                    <span>Notifications</span>
                    <button class="ml-auto text-gray-400 hover:text-gray-600" onclick="toggleNotifications()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Notification List -->
                <div class="flex-1 overflow-y-auto space-y-2" id="notificationPanel">
                <!-- JS will populate items here -->
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
    const token = "<?= $token ?>";
    const userDepartment = "<?= $department ?>";

    function generateReferenceID(type) {
        const prefix = {budget:'BR-',petty_cash:'PC-',payable:'INV-',emergency:'EM-'}[type];
        const date = (new Date()).toISOString().slice(0,10).replace(/-/g,'');
        const rand = Math.floor(1000 + Math.random() * 9000);
        return `${prefix}${date}-${rand}`;
    }

    // --- Tab logic ---
    let selectedType = 'budget';
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
            this.classList.add('tab-active');
            selectedType = this.getAttribute('data-type');
            document.getElementById('recentTab').click();
            loadRequests('recent');
        });
    });

    // --- Add New Request Modal ---
    const formModal = document.getElementById('requestFormModal');
    document.getElementById('addRequestBtn').onclick = function() {
        showForm();
    };
    function showForm() {
        // Set fields
        document.getElementById('formTitle').innerText = {
            'budget': "New Budget Request",
            'petty_cash': "New Petty Cash Request",
            'payable': "New Payable Request",
            'emergency': "New Emergency Disbursement"
        }[selectedType];
        // Reset form
        document.getElementById('dynamicRequestForm').reset();
        document.getElementById('request_type').value = selectedType;
        document.getElementById('reference_id').value = generateReferenceID(selectedType);
        // Show/hide time period
        document.getElementById('timePeriodGroup').style.display = (selectedType == 'budget' || selectedType == 'petty_cash') ? '' : 'none';
        formModal.classList.remove('hidden');
        document.querySelector('input[name="mode_of_payment"][value="cash"]').checked = true;
        togglePaymentFields();
    }
    function closeForm() { formModal.classList.add('hidden'); }

    // --- Reference ID logic ---
    function generateReferenceID(type) {
        const prefix = {budget:'BR-',petty_cash:'PC-',payable:'INV-',emergency:'EM-'}[type];
        const date = (new Date()).toISOString().slice(0,10).replace(/-/g,'');
        const rand = Math.floor(1000 + Math.random() * 9000);
        return `${prefix}${date}-${rand}`;
    }

    // --- Form Mode of Payment toggle ---
    function togglePaymentFields() {
        let mop = document.querySelector('input[name="mode_of_payment"]:checked').value;
        document.getElementById('ecashFields').style.display = mop === 'ecash' ? '' : 'none';
        document.getElementById('bankFields').style.display = mop === 'bank' ? '' : 'none';
    }

    // --- Form Submission ---
    document.getElementById('dynamicRequestForm').onsubmit = function(e) {
        e.preventDefault();
        let form = e.target;
        let fd = new FormData(form);
        fd.append('request_type', selectedType);
        fd.append('token', token);
        fetch('request_portal.php', {
            method: 'POST',
            body: fd
        }).then(res => res.json()).then(res => {
            showToast(res.msg, res.success);
            if (res.success) {
                closeForm();
                loadRequests('recent');
                // Refresh notifications after submitting a request
                loadNotifications();
            }
        }).catch(()=> showToast("Network error.", false));
    };

    // --- Toast ---
    function showToast(msg, success=true) {
        let t = document.getElementById('toast');
        t.innerText = msg;
        t.className = "fixed top-8 right-8 px-6 py-3 rounded-lg shadow-lg z-[9999] font-semibold " + (success ? "bg-green-600 text-white" : "bg-red-600 text-white");
        t.style.display = '';
        setTimeout(()=>{ t.style.opacity=0; }, 2000);
        setTimeout(()=>{ t.style.display='none'; t.style.opacity=1; }, 2600);
    }

    // --- Table Tabs ---
    document.getElementById('recentTab').onclick = function() {
        this.classList.add('border-b-4','border-violet-700','text-violet-800','font-bold');
        document.getElementById('historyTab').classList.remove('border-b-4','border-violet-700','text-violet-800','font-bold');
        loadRequests('recent');
    };
    document.getElementById('historyTab').onclick = function() {
        this.classList.add('border-b-4','border-violet-700','text-violet-800','font-bold');
        document.getElementById('recentTab').classList.remove('border-b-4','border-violet-700','text-violet-800','font-bold');
        loadRequests('history');
    };

    // --- Requests Table Loader (AJAX) ---
    function loadRequests(tabType) {
        let search = document.getElementById('searchBox').value.trim();
        fetch(`request_portal_data.php?type=${encodeURIComponent(selectedType)}&tab=${tabType}&search=${encodeURIComponent(search)}&token=${token}`)
            .then(resp => resp.text())
            .then(html => document.getElementById('requestsTableContainer').innerHTML = html);
    }
    document.getElementById('searchBox').addEventListener('input', () => {
        if (document.getElementById('recentTab').classList.contains('border-b-4'))
            loadRequests('recent');
        else
            loadRequests('history');
    });

    // --- Notification Panel Toggle ---
    function toggleNotifications() {
        const aside = document.getElementById('notificationAside');
        aside.classList.toggle('visible');
        
        // If we're closing the panel, mark all notifications as read
        if (!aside.classList.contains('visible')) {
            markNotificationsAsRead();
        }
    }

    document.getElementById('notificationIcon').addEventListener('click', toggleNotifications);

    // --- Update notification badge ---
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (count > 0) {
            badge.classList.remove('hidden');
            badge.textContent = count;
        } else {
            badge.classList.add('hidden');
        }
    }

    // --- Mark notifications as read ---
    function markNotificationsAsRead() {
        const formData = new FormData();
        formData.append('action', 'mark_all_read');
        formData.append('token', token);
        formData.append('department', userDepartment);
        
        fetch('notifications_panel.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(0);
                // Remove unread styling from notifications
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('bg-violet-50', 'unread');
                    item.classList.add('bg-white');
                });
            }
        });
    }

    // --- Mark single notification as read ---
    function markNotificationAsRead(notificationId) {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('id', notificationId);
        formData.append('token', token);
        formData.append('department', userDepartment);
        
        fetch('notifications_panel.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the UI
                const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('bg-violet-50', 'unread');
                    notificationItem.classList.add('bg-white');
                    
                    // Update badge count
                    const currentCount = parseInt(document.getElementById('notificationBadge').textContent);
                    updateNotificationBadge(Math.max(0, currentCount - 1));
                }
            }
        });
    }

    // --- Delete notification ---
    function deleteNotification(notificationId) {
        if (confirm('Are you sure you want to delete this notification?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', notificationId);
            formData.append('token', token);
            formData.append('department', userDepartment);
            
            fetch('notifications_panel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification from the UI
                    const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                    if (notificationItem) {
                        notificationItem.remove();
                        
                        // Update badge count if it was unread
                        if (notificationItem.classList.contains('unread')) {
                            const currentCount = parseInt(document.getElementById('notificationBadge').textContent);
                            updateNotificationBadge(Math.max(0, currentCount - 1));
                        }
                        
                        // Reload notifications if empty
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            loadNotifications();
                        }
                    }
                }
            });
        }
    }

    // --- Toggle dropdown menu ---
    function toggleDropdown(button) {
        const dropdown = button.nextElementSibling;
        const allDropdowns = document.querySelectorAll('.dropdown-menu');
        
        // Close all other dropdowns
        allDropdowns.forEach(d => {
            if (d !== dropdown) {
                d.classList.add('hidden');
            }
        });
        
        // Toggle current dropdown
        dropdown.classList.toggle('hidden');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.dropdown-container')) {
            document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }
    });

    // --- Initial load ---
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('recentTab').click();
        loadRequests('recent');
        loadNotifications();
    });

    // --- Notifications Panel (AJAX) ---
    function loadNotifications() {
        fetch(`notifications_panel.php?token=${token}&department=${encodeURIComponent(userDepartment)}`)
        .then(r=>r.text())
        .then(html=>{
            document.getElementById('notificationPanel').innerHTML = html;
            
            // Count unread notifications
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            updateNotificationBadge(unreadCount);
        });
    }

    // --- Real-time updates (polling) ---
    setInterval(() => {
        loadNotifications();
        // Refresh table if on recent tab
        if (document.getElementById('recentTab').classList.contains('border-b-4')) {
            loadRequests('recent');
        }
    }, 30000); // Every 30 seconds
</script>
</body>
</html>