<?php
$token = $_GET['token'] ?? $_GET['ptoken'] ?? '';

if (empty($token)) {
    // Only check session if no token is provided

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
} else {
    // If token is provided, start session without requiring login
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Set the current page from the URL parameter
$page = $_GET['page'] ?? 'sidebar';

$dropdowns = [
    'budget' => ['budgetrequest', 'rejectrequest', 'budgetallocation', 'budgetestimation', 'archive', 'pettycash'],
    'disburse' => ['disbursementrequest', 'banktransfer', 'ecash', 'cheque', 'cash', 'disbursedrecords'],
    'collect' => ['paymentrecords', 'arreceipts', 'collected'],
    'ap' => ['iapayables', 'payables', 'apreceipts', 'payablesrecords'],
    'ar' => ['iareceivables', 'receivables', 'receivablesrecords'],
    'gl' => ['chartsofaccounts', 'journalentry', 'ledger', 'trialbalance', 'financialstatement', 'expensereports', 'auditreports'],
    'tax' => ['taxemployees', 'taxpaidrecords']
];

$activeDropdown = null;

// Check which dropdown should be open
foreach ($dropdowns as $dropdown => $pages) {
    if (in_array($page, $pages)) {af
        $activeDropdown = $dropdown;
        break;
    }
}

$servername = "localhost";
$usernameDB = "financial"; 
$passwordDB = "UbrdRDvrHRAyHiA]"; 
$dbname = "financial_db"; 

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user info - handle both session and token cases
$userRole = $_SESSION['user_role'] ?? '';
$userDepartment = $_SESSION['user_department'] ?? '';
$givenname = $_SESSION['givenname'] ?? '';
$surname = $_SESSION['surname'] ?? '';
$userProfilePicture = "default_profile.png"; // Default profile picture

// If accessing via token, we need to get department from token
if (!empty($token) && empty($userDepartment)) {
    $stmt = $conn->prepare("SELECT department FROM department_tokens WHERE token = ? AND expires_at > NOW() AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        $userDepartment = $token_data['department'];
        // For token access, set a generic role
        $userRole = 'department_user';
    }
}

// Modules data
$modules = [
    'addap' => 'Add Buget Request',
    'sidebar' => 'Dashboard',
    'budgetrequest' => 'Budget Requests',
    'rejectrequest' => 'Rejected Requests',
    'budgetallocation' => 'Budget Allocation',
    'budgetestimation' => 'Budget Estimation',
    'pettycash' => 'Petty Cash Allowance',
    'disbursementrequest' => 'Disbursement Request',
    'banktransfer' => 'Bank Transfer Payout',
    'ecash' => 'Ecash Payout',
    'cash' => 'Cash Payout',
    'disbursedrecords' => 'Disbursed Records',
    'paymentrecords' => 'Payment Records',
    'collected' => 'Collected Receipts',
    'apreceipts' => 'Payables Receipts',
    'arreceipts' => 'Receivables Receipts',
    'iapayables' => 'Account Payables',
    'payables' => 'Payables',
    'payablesrecords' => 'Payables Records',
    'iareceivables' => 'Account Receivables',
    'receivables' => 'Receivables',
    'receivablesrecords' => 'Receivables Records',
    'chartsofaccounts' => 'Charts of Accounts',
    'journalentry' => 'Journal Entry',
    'ledger' => 'Ledger',
    'trialbalance' => 'Trial Balance',
    'financialstatement' => 'Financial Statement',
    'auditreports' => 'Audit Reports',
    'expensereports' => 'Expense Reports',
    'taxemployees' => 'Employees Tax Records',
    'taxpaidrecords' => 'Paid Tax Records',
    'archive' => 'Archive'
];

// Handle search query
$searchQuery = $_GET['search'] ?? '';
$searchResults = [];

if (!empty($searchQuery)) {
    // Search through modules for matching terms
    foreach ($modules as $key => $value) {
        if (stripos($value, $searchQuery) !== false) {
            $searchResults[] = ['key' => $key, 'name' => $value];
        }
    }
}

// Fetch notifications based on role - only for logged-in users, not token users
$notifications = [];
if (!empty($userRole) && $userRole !== 'department_user') {
    $notification_sql = "SELECT * FROM notifications WHERE ";

    if (in_array($userRole, ['financial admin', 'auditor'])) {
        $notification_sql .= "1=1";
    } elseif (in_array($userRole, ['hr', 'logistics', 'administrative', 'core'])) {
        $req_name = $givenname . ' ' . $surname;
        $notification_sql .= "requestor_username = '$req_name'";
    } else {
        $notification_sql .= "recipient_role = '$userRole'";
    }

    $notification_sql .= " ORDER BY timestamp DESC LIMIT 5";

    $notification_result = $conn->query($notification_sql);
    if ($notification_result && $notification_result->num_rows > 0) {
        while ($n = $notification_result->fetch_assoc()) {
            $notifications[] = $n;
        }
    }
}

// Prepare notification for insertion if needed
$stmt = $conn->prepare("INSERT INTO notifications 
  (reference_id, request_type, message, sender_role, recipient_role, department, status)
  VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $reference_id, $request_type, $message, $sender_role, $recipient_role, $department, $status);

// Dynamic Financial Request Portal link based on department
$departmentTokens = [
    'Administrative' => 'admin123token456',
    'Financial' => 'fintokenvwx234',
    'Human Resource-1' => 'hr10xende456',
    'Human Resource-2' => 'hr20xenpi7/89',
    'Human Resource-3' => 'hr30xenjk012',
    'Human Resource-4' => 'hr40xenm0345',
    'Core-1' => 'coreToken789xyz',
    'Core-2' => 'core20xenabc123',
    'Logistic-1' => 'log10xenprp678',
    'Logistic-2' => 'log20xensub01'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ViaHale Dashboard</title>
  <link rel="icon" href="logo.png" type="img">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Quicksand:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    .font-poppins {
      font-family: 'Poppins', sans-serif;
    }
    .font-quicksand {
      font-family: 'Quicksand', sans-serif;
    }
    .font-bricolage {
      font-family: 'Bricolage Grotesque', sans-serif;
    }
  </style>
</head>
<body>
<div class="flex min-h-screen h-full bg-white font-poppins">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-[#181c2f] text-white w-64 space-y-6 transition-all duration-300 flex-shrink-0 min-h-screen h-full overflow-y-auto">
        <div class="flex items-center pr-4 pt-4">
            <img src="logo2.png" class="w-50 h-50 my-6" alt="logo">
        </div>
      <nav>
        <ul>
          
          <li>
            <div>
            <a href="dashboard.php?page=sidebar" class="flex items-center -ml-4 space-x-1 p-2 pl-12 cursor-pointer text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4
            <?php echo ($page == 'sidebar' 
                        ?
                        : 'text-[#bfc7d1] hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
              <i class="fas fa-th-large"></i>
              <span>Dashboard</span>
            </a>
            </div>
          </li>

          <?php if ($userRole == 'admin' || $userRole == 'budget manager'): ?>
          <li>
            <div>
            <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
              <i class="fas fa-th-large"></i>
              <span>Budget Management</span>
            </a></div>
            <ul class="pl-4 text-sm mt-2">
              <li class="mb-2">
                <a href="budget_request.php?page=budgetrequest" class="flex items-center
                  <?php echo ($page == 'budgetrequest' 
                    ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                    : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-8 cursor-pointer">
                  <i class="fas fa-file-alt"></i>
                  <span>Budget Requests</span>
                </a>
              </li>

              <li class="mb-2">
                <a href="budget_allocation.php?page=budgetallocation" class="flex items-center
                  <?php echo ($page == 'budgetallocation' 
                    ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                    : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-8 cursor-pointer">
                  <i class="fas fa-chart-pie"></i>
                  <span>Budget Allocation</span>
                </a>
              </li>

              <li class="mb-2">
                <a href="budget_estimation.php?page=budgetestimation" class="flex items-center
                  <?php echo ($page == 'budgetestimation' 
                    ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                    : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-8 cursor-pointer">
                  <i class="fas fa-lightbulb"></i>
                  <span>Budget Estimation</span>
                </a>
              </li>

              <li class="mb-2">
                <a href="pettycash.php?page=pettycash" class="flex items-center
                  <?php echo ($page == 'pettycash' 
                    ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                    : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-8 cursor-pointer">
                  <i class="fas fa-wallet"></i>
                  <span>Petty Cash</span>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>
          <?php if ($userRole == 'admin' || $userRole == 'disburse officer'): ?>
          <li>
            <div>
            <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
              <i class="fas fa-landmark"></i>
              <span>Accounts Payable</span>
            </a>
                <ul class="pl-4 text-sm mt-2 ">
                  <li class="mb-2">
                      <a href="payables_ia.php?page=iapayables" class="flex items-center 
                        <?php echo ($page == 'iapayables' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Invoice Approval
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="payables.php?page=payables" class="flex items-center 
                        <?php echo ($page == 'payables' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Payables
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="payables_receipts.php?page=apreceipts" class="flex items-center 
                        <?php echo ($page == 'apreceipts' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Payables Receipts
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="payables_records.php?page=payablesrecords" class="flex items-center 
                        <?php echo ($page == 'payablesrecords' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Payables Records
                      </a>
                  </li>
                </ul>
            </div>
          </li>
          <?php endif; ?>
          <?php if ($userRole == 'admin' || $userRole == 'disburse officer'): ?>
          <li>
            <div>
                <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
                  <i class="fas fa-coins"></i>
                  <span>Disbursement</span>
                </a>
                
                <ul class="pl-4 text-sm mt-2">
                  <li class="mb-2">
                      <a href="disbursement_request.php?page=disbursementrequest" class="flex items-center 
                        <?php echo ($page == 'disbursementrequest' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Disbursement Request
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="banktransfer.php?page=banktransfer" class="flex items-center 
                        <?php echo ($page == 'banktransfer' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Bank Transfer Payout
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="ecash.php?page=ecash" class="flex items-center 
                        <?php echo ($page == 'ecash' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Ecash Payout
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="cash.php?page=cash" class="flex items-center 
                        <?php echo ($page == 'cash' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Cash Payout
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="disbursedrecords.php?page=disbursedrecords" class="flex items-center 
                        <?php echo ($page == 'disbursedrecords' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Disbursed Records
                      </a>
                  </li>
                </ul>
            </div>
          </li>
          <?php endif; ?>

          <?php if ($userRole == 'admin' || $userRole == 'collector'): ?>
          <li>
            <div>
            <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
              <i class="fas fa-file-invoice-dollar"></i>
              <span>Account Receivables</span>
            </a>
            
                <ul class="pl-4 text-sm mt-2">
                  <li class="mb-2">
                      <a href="receivables_ia.php?page=iareceivables" class="flex items-center
                        <?php echo ($page == 'iareceivables' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Invoice Confirmation
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="receivables.php?page=receivables" class="flex items-center 
                        <?php echo ($page == 'receivables' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Receivables
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="receivables_receipts.php?page=arreceipts" class="flex items-center 
                        <?php echo ($page == 'arreceipts'
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">                      
                        Receivables Receipts
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="receivables_records.php?page=receivablesrecords" class="flex items-center 
                        <?php echo ($page == 'receivablesrecords' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Receivables Records
                      </a>
                  </li>
                </ul>
            </div>      
          </li>
          <?php endif; ?>

            <?php if ($userRole == 'admin' || $userRole == 'collector'): ?>
          <li>
            <div>
            <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
              <i class="fas fa-chart-line"></i>
              <span>Collection</span>
            </a>
                <ul class="pl-4 text-sm mt-2">
                  <li class="mb-2">
                      <a href="paymentrecords.php?page=paymentrecords" class="flex items-center 
                        <?php echo ($page == 'paymentrecords' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Payment Records
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="collect_receipt.php?page=collected" class="flex items-center 
                        <?php echo ($page == 'collected' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">                      
                        Collected Receipts
                      </a>
                  </li>
                </ul>
            </div>
          </li>
              <?php endif; ?>
          
          <?php if ($userRole == 'admin' || $userRole == 'auditor'): ?>
          <li>
            <div>
            <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
              <i class="fas fa-book"></i>
              <span>General Ledger</span>
            </a>
                <ul class="pl-4 text-sm mt-2">
                  <li class="mb-2">
                      <a href="charts_of_accounts.php?page=chartsofaccounts" class="flex items-center mt-4
                        <?php echo ($page == 'chartsofaccounts' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Charts of Accounts
                        </a>
                  </li>
                  <li class="mb-2">
                      <a href="journal_entry.php?page=journalentry" class="flex items-center 
                        <?php echo ($page == 'journalentry' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Journal Entry
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="ledger.php?page=ledger" class="flex items-center 
                        <?php echo ($page == 'ledger' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Ledger
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="trial_balance.php?page=trialbalance" class="flex items
                        <?php echo ($page == 'trialbalance' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Trial Balance
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="financial_statement.php?page=financialstatement" class="flex items-center 
                        <?php echo ($page == 'financialstatement' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Financial Statement
                      </a>
                  </li>

                  <li class="mb-2">
                      <a href="audit_reports.php?page=auditreports" class="flex items-center 
                        <?php echo ($page == 'auditreports' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Audit Reports
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="expense_reports.php?page=expensereports" class="flex items-center 
                        <?php echo ($page == 'expensereports' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Expense Reports
                      </a>
                  </li>
                </ul>
            </div>
          </li>
          <?php endif; ?>
          <?php if ($userRole == 'admin' || $userRole == 'auditor'): ?>
          <li>
            <div>
            <a href="#" class="flex items-center -mb-2 space-x-1 p-2 pl-8 text-white">
              <i class="fas fa-file-invoice"></i>
              <span>Tax Management</span>
            </a>
                <ul class="pl-4 text-sm mt-2">
                  <li class="mb-2">
                      <a href="tax_employees.php?page=taxemployees" class="flex items-center mt-4 
                        <?php echo ($page == 'taxemployees' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Employees Tax Records
                      </a>
                  </li>
                  <li class="mb-2">
                      <a href="paid_tax.php?page=taxpaidrecords" class="flex items-center 
                        <?php echo ($page == 'taxpaidrecords' 
                        ? 'text-white bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] border-l-4 border-purple-500' 
                        : 'text-[#bfc7d1] hover:text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:border-l-4'); ?> space-x-2 p-2 pl-12 cursor-pointer">
                        Paid Tax Records
                      </a>
                  </li>
                </ul>
            </div>
          </li>
          <?php endif; ?>

          <?php if ($userDepartment == 'Financial'): ?>
          <li>
            <div>
                <a href="financial request portal.php?token=fintokenvwx234" class="flex items-center -mb-2 space-x-1 p-2 pl-8 cursor-pointer text-[#bfc7d1] hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:text-white hover:border-l-4">
                  <i class="fas fa-coins"></i>
                  <span>Financial Request</span>
                </a>
            </div>
          </li>
          <?php endif; ?>
      </nav>
      <div class="h-px bg-gray-600 mx-12"></div>
    </aside>

     <!-- Main Content -->
    <main class="flex-1 overflow-hidden">
        <!-- Header Bar -->
        <header class="flex justify-between items-center px-6 py-3 bg-white border border-gray-200 font-poppins">
            <div class="flex items-center space-x-12">
                <button id="menu-toggle" class="cursor-pointer"><i class="fas fa-bars text-2xl"></i></button>
                <a href="dashboard.php?page=sidebar" class="text-lg text-black hover:text-blue-500">Home</a>
                <a href="#" class="text-lg text-black hover:text-blue-500">Contact</a>

                <!-- Search Bar -->
                <div class="relative">
                    <input type="text" oninput="showSuggestions()" placeholder="Search here" class="ml-6 w-60 h-8 bg-gray-100 text-xs px-[18px] py-1 pl-4 border rounded-full">
                    <i class="fas fa-search absolute right-3 top-2 text-gray-600"></i>
                </div>
                <div id="suggestions" class="absolute left-0 right-0 bg-white border rounded-lg shadow-lg mt-2 hidden"></div>
            </div>

            <!-- Notification and Profile -->
            <div class="flex items-center space-x-4">

                <!-- Notification Dropdown -->
                <div class="relative cursor-pointer">
                    <button class="flex items-center" onclick="toggleSimpleDropdown('notificationDropdown', event)">
                        <i class="fas fa-envelope text-2xl pr-4"></i>
                        <?php if (!empty($notifications) && count($notifications) > 0): ?>
                            <span id="notificationCount" class="absolute top-0 right-0 bg-purple-700 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                                <?php echo count($notifications); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationDropdown" class="absolute right-0 mt-2 w-56 bg-white overflow-y-auto h-52 rounded-lg shadow-lg py-2 hidden">
                        <?php if (empty($notifications)): ?>
                            <p class="block px-4 py-2 text-gray-700 text-sm">No notifications</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="px-4 py-2 border-b border-gray-200 notification-item" 
                                    data-id="<?php echo $notification['id']; ?>" 
                                    onclick="markAsRead(this)">
                                    <p class="text-gray-700 text-sm"><?php echo $notification['message']; ?></p>
                                    <p class="text-gray-500 text-xs"><?php echo date("F j, Y, g:i a", strtotime($notification['timestamp'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Profile Dropdown -->
                <div class="flex items-center space-x-2 relative" onclick="toggleSimpleDropdown('userDropdown', event)">              
                    <img src="<?php echo $userProfilePicture; ?>" class="rounded-full w-10 h-10" alt="Profile">
                    <div class="font-poppins pr-8 pl-2">
                        <div class="font-poppins">
                            <?php
                            $initial = strtoupper($_SESSION['givenname'][0]); // Get first letter and capitalize
                            $surname = $_SESSION['surname']; // Get full surname
                            echo $initial . '. ' . $surname;
                            ?>
                        </div>
                        <div class="text-sm text-gray-500"><?php echo $userRole; ?></div>
                    </div>
                    <div id="userDropdown" class="absolute right-0 w-48 bg-white rounded-md shadow-lg py-2 hidden" style="top: 100%; z-index: 10;">
                        <a class="block px-4 py-2 text-gray-700 font-bold hover:bg-purple-700 hover:text-white" href="profile.php">Profile</a>
                        <a class="block px-4 py-2 text-gray-700 font-bold hover:bg-purple-700 hover:text-white" href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </header>

  <script>
// Sidebar toggle
document.getElementById('menu-toggle').addEventListener('click', () => {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-ml-64');
});

// Unified toggleDropdown function for sidebar
function toggleDropdown(id) {
    const currentDropdown = document.getElementById(id);
    
    // Close all other dropdowns
    document.querySelectorAll('ul[id$="Dropdown"]').forEach(dropdown => {
        if (dropdown.id !== id) {
            dropdown.classList.remove("max-h-[500px]", "opacity-100");
            dropdown.classList.add("max-h-0", "opacity-0");
        }
    });

    // Toggle the clicked dropdown
    const isOpen = currentDropdown.classList.contains("max-h-[500px]");
    if (isOpen) {
        currentDropdown.classList.remove("max-h-[500px]", "opacity-100");
        currentDropdown.classList.add("max-h-0", "opacity-0");
    } else {
        currentDropdown.classList.remove("max-h-0", "opacity-0");
        currentDropdown.classList.add("max-h-[500px]", "opacity-100");
    }
}

// Toggle function for notifications and user dropdowns
function toggleSimpleDropdown(dropdownId, event) {
    event.stopPropagation(); // Prevent the click event from bubbling up
    const dropdown = document.getElementById(dropdownId);
    dropdown.classList.toggle('hidden'); // Toggle the visibility of the dropdown
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    // Close notification dropdown if clicked outside
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (notificationDropdown && !event.target.closest('.relative.cursor-pointer')) {
        notificationDropdown.classList.add('hidden');
    }
    // Close user dropdown if clicked outside
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown && !event.target.closest('.flex.items-center.space-x-2')) {
        userDropdown.classList.add('hidden');
    }
});


// Function to mark notification as read
function markAsRead(element) {
    const notificationId = element.getAttribute('data-id');
    
    // Add visual feedback that it's read
    element.style.backgroundColor = '#f0f0f0'; // Optional: Change background color
    element.style.opacity = '0.7'; // Optional: Change opacity

    // Update the notification count
    const countElement = document.getElementById('notificationCount');
    let currentCount = parseInt(countElement.textContent);

    // Only decrease count if it hasn't been read yet
    if (currentCount > 0 && !element.classList.contains('read')) {
        currentCount--;
        countElement.textContent = currentCount;

        // Hide the count badge if no unread notifications left
        if (currentCount === 0) {
            countElement.style.display = 'none';
        }

        // Mark as read in the DOM
        element.classList.add('read');

        // Send AJAX request to server to mark as read
        markNotificationAsReadOnServer(notificationId);
    }
}

// AJAX function to update server
function markNotificationAsReadOnServer(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to mark notification as read');
            // Optionally revert the UI changes if the server update failed
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
  

// Define the suggestions and their corresponding pages
    const suggestionsData = {
        "Dashboard": "dashboard.php",
        "Budget Requests": "budget_request.php",
        "Rejected Requests": "rejected_request.php",
        "Budget Allocation": "budget_allocation.php",
        "Budget Estimation": "budget_estimation.php",
        "Payout Approval": "payout_approval.php",
        "Bank Transfer Payout": "banktransfer.php",
        "Ecash Payout": "ecash.php",
        "Cheque Payout": "cheque.php",
        "Cash Payout": "cash.php",
        "Disbursed Records": "disbursedrecords.php",
        "Payment Records": "paymentrecords.php",
        "Payables Receipts": "payables_receipts.php",
        "Receivables Receipts": "receivables_receipts.php",
        "Invoice Approval (Payables)": "payables_ia.php",
        "Payables": "payables.php",
        "Payables Records": "payables_records.php",
        "Invoice Approval (Receivables)": "receivables_ia.php",
        "Receivables": "receivables.php",
        "Receivables Records": "receivables_records.php",
        "Charts of Accounts": "charts_of_accounts.php",
        "Journal Entry": "journal_entry.php",
        "Ledger": "ledger.php",
        "Trial Balance": "trial_balance.php",
        "Financial Statement": "financial_statement.php",
        "Audit Reports": "audit_reports.php",
        "Employees Tax Records": "tax_employees.php",
        "Paid Tax Records": "paid_tax.php",
        "Add Budget Request": "add_ap.php",
        "Expense Reports": "expense_reports.php",
        "Petty Cash Allowance": "pettycash.php",
    };

    function showSuggestions() {
        const input = document.getElementById("searchInputnavbar");
        const suggestionsBox = document.getElementById("suggestions");
        const query = input.value.toLowerCase().trim();

        // Clear previous suggestions
        suggestionsBox.innerHTML = "";
        suggestionsBox.classList.add("hidden");

        if (query === "") return; // Do not show suggestions for an empty input

        // Filter suggestions based on the query
        const filteredSuggestions = Object.keys(suggestionsData).filter(name =>
            name.toLowerCase().includes(query)
        );

        // Display suggestions
        if (filteredSuggestions.length > 0) {
            suggestionsBox.classList.remove("hidden");
            filteredSuggestions.forEach(name => {
                const suggestionItem = document.createElement("div");
                suggestionItem.textContent = name;
                suggestionItem.classList.add("py-2", "px-4", "hover:bg-gray-100", "cursor-pointer");

                // Add click event to redirect to the corresponding page
                suggestionItem.onclick = () => {
                    window.location.href = suggestionsData[name]; // Navigate to the page
                };

                suggestionsBox.appendChild(suggestionItem);
            });
        }
    }
</script>


