<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: login.php");
  exit();
}

// DB connection
$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// --- Enhanced Notification Functions ---
function notify_officer($conn, $reference_id, $request_type, $message, $sender_role, $recipient_role, $department, $status='pending', $requestor_username='') {
    $stmt = $conn->prepare("INSERT INTO notifications 
        (reference_id, request_type, message, sender_role, recipient_role, department, status, requestor_username) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $reference_id, $request_type, $message, $sender_role, $recipient_role, $department, $status, $requestor_username);
    $stmt->execute();
    $stmt->close();
}

function notify_requestor($conn, $username, $reference_id, $message, $status, $request_type) {
    $stmt = $conn->prepare("INSERT INTO requestor_notif 
        (username, reference_id, message, status, request_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $reference_id, $message, $status, $request_type);
    $stmt->execute();
    $stmt->close();
}

$username = $_SESSION['users_username'];
$user_role = $_SESSION['user_role'] ?? '';
$full_name = $_SESSION['givenname'] . ' ' . $_SESSION['surname'];

// --- Approval Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id']) && isset($_POST['official_amount'])) {
  $approveId = intval($_POST['approve_id']);
  $officialAmount = floatval($_POST['official_amount']);

  $conn->begin_transaction();
  try {
    $fetch_sql = "SELECT requested_department, expense_categories, mode_of_payment, reference_id, account_name FROM budget_request WHERE id = ?";
    $stmt_fetch = $conn->prepare($fetch_sql);
    $stmt_fetch->bind_param("i", $approveId);
    $stmt_fetch->execute();
    $stmt_fetch->bind_result($requested_department, $expense_category, $mode_of_payment, $reference_id, $account_name);
    $stmt_fetch->fetch();
    $stmt_fetch->close();

    $update_sql = "UPDATE budget_request SET amount = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("di", $officialAmount, $approveId);
    $stmt_update->execute();

    $expense_categories = [
      "Equipments/Assets",
      "Maintenance/Repair",
      "Bonuses",
      "Facility Cost",
      "Training Cost",
      "Wellness Program Cost",
      "Tax Payment",
      "Extra",
      "Salaries"
    ];

    $payment_modes = ["Cash", "Ecash", "Bank Transfer"];

    if (in_array($expense_category, $expense_categories) && in_array($mode_of_payment, $payment_modes)) {
      // ✅ Insert new debit entry for the expense category
      $insert_journal_sql = "INSERT INTO journal_table (expense_categories, debit_amount, credit_amount, credit_account) 
                              VALUES (?, ?, 0, NULL)";
      $stmt_insert_journal = $conn->prepare($insert_journal_sql);
      $stmt_insert_journal->bind_param("sd", $expense_category, $officialAmount);
      $stmt_insert_journal->execute();

      // ✅ Insert new credit entry (for the specific payment mode)
      $insert_journal_sql = "INSERT INTO journal_table (expense_categories, debit_amount, credit_amount, credit_account) 
                              VALUES (?, 0, ?, ?)";
      $stmt_insert_journal = $conn->prepare($insert_journal_sql);
      $stmt_insert_journal->bind_param("sds", $mode_of_payment, $officialAmount, $expense_category);
      $stmt_insert_journal->execute();
    }

    if ($stmt_update->execute()) {
      // Insert into pa table
      $insert_sql = "INSERT INTO pa (
          account_name, requested_department, expense_categories, amount, description, document, payment_due,
          bank_name, bank_account_number, bank_account_name,
          ecash_provider, ecash_account_name, ecash_account_number,
          reference_id, mode_of_payment, from_payable
      )
      SELECT
          account_name, requested_department, expense_categories, amount, description, document, payment_due,
          bank_name, bank_account_number, bank_account_name,
          ecash_provider, ecash_account_name, ecash_account_number,
          CONCAT('PA-', SUBSTRING(reference_id, 4)) AS reference_id, mode_of_payment,
          1 AS from_payable
      FROM budget_request WHERE id = ?";

      $stmt_insert = $conn->prepare($insert_sql);
      $stmt_insert->bind_param("i", $approveId);

      if ($stmt_insert->execute()) {
        // Delete from budget_request
        $delete_sql = "DELETE FROM budget_request WHERE id = ?";
        $stmt_delete = $conn->prepare($delete_sql);
        $stmt_delete->bind_param("i", $approveId);

        if ($stmt_delete->execute()) {
          // Update the allocated_amount in the budget_allocations table...
          $update_allocated_sql = "UPDATE budget_allocations SET allocated_amount = allocated_amount + ? WHERE department = ?";
          $stmt_update_allocated = $conn->prepare($update_allocated_sql);
          $stmt_update_allocated->bind_param("ds", $officialAmount, $requested_department);
          $stmt_update_allocated->execute();

          // --- Enhanced Notifications ---
          $notif_msg = "Budget request $reference_id approved.";
          notify_officer($conn, $reference_id, "budget", $notif_msg, $user_role, "budget manager", $requested_department, "approved", $account_name);
          notify_requestor($conn, $account_name, $reference_id, $notif_msg, "approved", "Budget Request");

          $conn->commit();
          $_SESSION['toast_message'] = "Budget Approved and moved to Payout!";
          $_SESSION['toast_type'] = "success";
        } else {
          throw new Exception("Error deleting record from budget_request: " . $stmt_delete->error);
        }
      } else {
        throw new Exception("Error inserting record into pa: " . $stmt_insert->error);
      }
    } else {
      throw new Exception("Error updating amount in budget_request: " . $stmt_update->error);
    }
  } catch (Exception $e) {
    $conn->rollback();
    $_SESSION['toast_message'] = "Transaction failed: " . $e->getMessage();
    $_SESSION['toast_type'] = "error";
  }
}

// --- Rejection ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id']) && isset($_POST['reason'])) {
  $rejectId = intval($_POST['reject_id']);
  $reason   = $conn->real_escape_string($_POST['reason']);

  $conn->begin_transaction();
  try {
    $fetch_sql = "SELECT reference_id, account_name, requested_department FROM budget_request WHERE id = ?";
    $stmt_fetch = $conn->prepare($fetch_sql);
    $stmt_fetch->bind_param("i", $rejectId);
    $stmt_fetch->execute();
    $stmt_fetch->bind_result($reference_id, $account_name, $requested_department);
    $stmt_fetch->fetch();
    $stmt_fetch->close();

    // Insert into rejected_request table
    $insert_sql = "INSERT INTO rejected_request (reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, time_period, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, rejected_reason)
                        SELECT reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, time_period, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, ?
                        FROM budget_request WHERE id = ?";
    $stmt_insert = $conn->prepare($insert_sql);
    $stmt_insert->bind_param("si", $reason, $rejectId);

    if ($stmt_insert->execute()) {
      // Delete from budget_request
      $delete_sql = "DELETE FROM budget_request WHERE id = ?";
      $stmt_delete = $conn->prepare($delete_sql);
      $stmt_delete->bind_param("i", $rejectId);

      if ($stmt_delete->execute()) {
        // --- Enhanced Notifications ---
        $notif_msg = "Budget request $reference_id rejected. Reason: $reason";
        notify_officer($conn, $reference_id, "budget", $notif_msg, $user_role, "budget manager", $requested_department, "rejected", $account_name);
        notify_requestor($conn, $account_name, $reference_id, $notif_msg, "rejected", "Budget Request");

        $conn->commit();
        $_SESSION['toast_message'] = "Budget Rejected and moved to Rejected Requests!";
        $_SESSION['toast_type'] = "success";
      } else {
        throw new Exception("Error deleting record from budget_request: " . $stmt_delete->error);
      }
    } else {
      throw new Exception("Error inserting record into rejected_request: " . $stmt_insert->error);
    }
  } catch (Exception $e) {
    $conn->rollback();
    $_SESSION['toast_message'] = "Transaction failed: " . $e->getMessage();
    $_SESSION['toast_type'] = "error";
  }
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = intval($_POST['delete_id']);

  $delete_sql = "DELETE FROM budget_request WHERE id = ?";
  $stmt_delete = $conn->prepare($delete_sql);
  $stmt_delete->bind_param("i", $deleteId);

  if ($stmt_delete->execute()) {
    $_SESSION['toast_message'] = "Record deleted successfully!";
    $_SESSION['toast_type'] = "success";
  } else {
    $_SESSION['toast_message'] = "Error deleting record: " . $stmt_delete->error;
    $_SESSION['toast_type'] = "error";
  }
}

// ==============================
// Re-establish DB (as in original code)
// ==============================
$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

// Get time period filter
$timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : 'all';

// Build the SQL query with time period filter
$sql = "SELECT * FROM budget_request WHERE 1=1";
if ($timePeriod !== 'all') {
  $sql .= " AND time_period = '$timePeriod'";
}
$sql .= " ORDER BY id DESC";

$result = $conn->query($sql);
$rows = [];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
}

// Overview metrics for PENDING payouts only
$total_pending = count($rows);
$total_amount_due = 0;
$pending_cash = 0;
$pending_bank = 0;
$pending_ecash = 0;

foreach ($rows as $r) {
  $total_amount_due += floatval($r['amount']);
  $mode = strtolower($r['mode_of_payment']);
  if ($mode === 'cash') $pending_cash++;
  elseif ($mode === 'bank' || $mode === 'bank transfer') $pending_bank++;
  elseif ($mode === 'ecash') $pending_ecash++;
}
?>

<html>
  <head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>Budget Request</title>
    <link rel="icon" href="logo.png" type="img">
    <style>
      @media (max-width: 1024px) {
        .overview-flex { flex-direction: column !important; }
        .overview-left, .overview-right { width: 100% !important; }
        .overview-cards { flex-direction: column !important; }
        .overview-right { min-width: 0 !important; }
      }
      
      /* Toast notification styles */
      .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
        max-width: 300px;
      }
      
      .toast.success {
        background-color: #10B981;
      }
      
      .toast.error {
        background-color: #EF4444;
      }
      
      .toast.show {
        opacity: 1;
      }
      
      /* Tab styles */
      .tab-button {
        padding: 8px 16px;
        border-radius: 9999px;
        font-weight: 500;
        transition: all 0.3s;
      }
      
      .tab-button.active {
        background-color: #8B5CF6;
        color: white;
      }
      
      .tab-button:not(.active) {
        background-color: #F3F4F6;
        color: #4B5563;
      }
      
      .tab-button:not(.active):hover {
        background-color: #E5E7EB;
      }
      
      .status-tab {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s;
      }
      
      .status-tab.active {
        background-color: #8B5CF6;
        color: white;
      }
      
      .status-tab:not(.active) {
        background-color: #F3F4F6;
        color: #4B5563;
      }
      
      .status-tab:not(.active):hover {
        background-color: #E5E7EB;
      }
    </style>
  </head>

  <body class="bg-white">
    <?php include('sidebar.php'); ?>

    <div class="overflow-y-auto h-full px-6">
        <!-- Breadcrumb -->
        <div class="flex justify-between items-center px-6 py-6 font-poppins">
            <h1 class="text-2xl">Budget Request</h1>
            <div class="text-sm">
                <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
                /
                <a class="text-black">Budget Management</a>
                /
                <a href="budget_request.php" class="text-blue-600 hover:text-blue-600">Budget Request</a>
            </div>
        </div>

        <!-- Toast Notification -->
        <?php if (isset($_SESSION['toast_message'])): ?>
        <div class="toast <?php echo $_SESSION['toast_type'] === 'success' ? 'success' : 'error'; ?>" id="toast">
            <?php echo $_SESSION['toast_message']; ?>
        </div>
        <?php 
          unset($_SESSION['toast_message']);
          unset($_SESSION['toast_type']);
        endif; 
        ?>

        <div class="ml-6 mr-6 p-4 bg-white border border-gray-200 rounded-xl shadow flex flex-col gap-4">
          <!-- Header -->
          <div class="flex items-center gap-2">
              <i class="fas fa-clipboard text-xl text-violet-700 "></i>
              <h2 class="text-xl font-poppins text-black">Overview</h2>
          </div>

          <!-- Stat Cards -->
          <div class="flex overview-left overview-cards pt-4 pb-4 gap-8">
            <!-- Pending -->
            <div class="flex-1 rounded-lg bg-purple-50 p-6 flex flex-col items-center justify-center">
              <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-clipboard-list text-xl text-purple-600"></i>
                <span class="font-semibold text-xl text-gray-700">Total Requests</span>
              </div>
              <span class="text-2xl font-bold text-purple-600"><?php echo $total_pending; ?></span>
            </div>

            <!-- Pending by Mode -->
            <div class="flex-1 rounded-lg bg-blue-50 p-6 flex flex-col items-center justify-center">
              <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-tasks text-xl text-blue-600"></i>
                <span class="font-semibold text-base text-gray-700">Pending by Mode of Payment</span>
              </div>
              <div class="flex flex-col gap-1 font-bold text-md">
                <span class="text-green-700">Cash: <?php echo $pending_cash; ?></span>
                <span class="text-blue-700">Bank: <?php echo $pending_bank; ?></span>
                <span class="text-yellow-600">Ecash: <?php echo $pending_ecash; ?></span>
              </div>
            </div>

            <!-- Total Amount Due -->
            <div class="flex-1 rounded-lg bg-orange-50 p-6 flex flex-col items-center justify-center">
              <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-money-bill-wave text-xl text-red-500"></i>
                <span class="font-semibold text-base text-gray-700">Total Amount</span>
              </div>
              <span class="text-2xl font-bold text-red-600">₱ <?php echo number_format($total_amount_due, 2); ?></span>
            </div>
          </div>
        </div>

        <!-- Dual Tab Layout -->
        <div class="ml-6 mr-6 mt-6 p-4 bg-white border border-gray-200 rounded-xl shadow">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <!-- Time Period Tabs -->
            <div class="flex flex-wrap gap-2">
              <a href="?time_period=all" class="tab-button <?php echo $timePeriod === 'all' ? 'active' : ''; ?>">
                All
              </a>
              <a href="?time_period=weekly" class="tab-button <?php echo $timePeriod === 'weekly' ? 'active' : ''; ?>">
                Weekly
              </a>
              <a href="?time_period=monthly" class="tab-button <?php echo $timePeriod === 'monthly' ? 'active' : ''; ?>">
                Monthly
              </a>
              <a href="?time_period=quarterly" class="tab-button <?php echo $timePeriod === 'quarterly' ? 'active' : ''; ?>">
                Quarterly
              </a>
              <a href="?time_period=yearly" class="tab-button <?php echo $timePeriod === 'yearly' ? 'active' : ''; ?>">
                Annually
              </a>
            </div>

            <!-- Status Tabs -->
            <div class="flex flex-wrap gap-2" id="statusTabs">
              <button class="status-tab active" data-tab="pending">
                Pending Requests
              </button>
              <button class="status-tab" data-tab="rejected">
                Rejected Requests
              </button>
              <button class="status-tab" data-tab="archived">
                Archived Requests
              </button>
            </div>
          </div>

          <!-- Search Bar -->
          <div class="flex justify-start items-center mb-4 space-x-2">
            <input type="text" id="searchInput" class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Search by Reference ID, Department, Description, Amount, or Category" onkeyup="filterTable()" />
            <label for="dueDate" class="font-semibold ml-2">Payment Due:</label>
            <input type="date" id="dueDate" class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm" onchange="filterTable()" />
          </div>

          <!-- Tab Content -->
          <div id="tabContent">
            <!-- Pending Requests Tab (Default) -->
            <div id="pendingTab" class="tab-pane active">
              <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                  <i class="far fa-file-alt text-xl"></i>
                  <h2 class="text-2xl font-poppins text-black">Pending Requests</h2>
                </div>
                <div class="overflow-x-auto w-full">
                  <table class="w-full table-auto bg-white mt-4" id="brTable">
                    <thead>
                      <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                        <th class="px-6 py-2">Reference ID</th>
                        <th class="px-6 py-2">Account Name</th>
                        <th class="px-6 py-2">Department</th>
                        <th class="px-6 py-2">Payment Mode</th>
                        <th class="px-6 py-2">Expense Category</th>
                        <th class="px-6 py-2">Amount</th>
                        <th class="px-6 py-2">Description</th>
                        <th class="px-6 py-2">Document</th>
                        <th class="px-6 py-2">Time Period</th>
                        <th class="px-6 py-2">Payment Due</th>
                        <th class="px-6 py-2">Actions</th>
                      </tr>
                    </thead>
                    <tbody class="text-gray-900 text-sm font-light" id="brTableBody">
                    <?php
                      foreach ($rows as $row):
                          $paymentDue = $row['payment_due'] ? date('Y-m-d', strtotime($row['payment_due'])) : '';
                          ?>
                          <tr class="hover:bg-violet-100" data-mode="<?php echo strtolower($row['mode_of_payment']); ?>"
                            data-refid="<?php echo htmlspecialchars($row['reference_id']); ?>"
                            data-acct="<?php echo htmlspecialchars($row['account_name']); ?>"
                            data-dept="<?php echo htmlspecialchars($row['requested_department']); ?>"
                            data-category="<?php echo htmlspecialchars($row['expense_categories']); ?>"
                            data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                            data-duedate="<?php echo $paymentDue; ?>"
                            data-period="<?php echo $row['time_period']; ?>">
                            <td class='pl-10 pr-6 py-2'><?php echo $row['reference_id'];?></td>
                            <td class='px-6 py-2'><?php echo $row['account_name'];?></td>
                            <td class='px-6 py-2'><?php echo $row['requested_department'];?></td>
                            <td class='px-6 py-2'><?php echo $row['mode_of_payment'];?></td>
                            <td class='px-6 py-2'><?php echo $row['expense_categories'];?></td>
                            <td class='px-6 py-2'>₱<?php echo number_format($row['amount'], 2);?></td>
                            <td class='px-6 py-2'><?php echo $row['description'] ?? '';?></td>
                            <td class='px-6 py-2'>
                                <?php
                                if ($row['document']) {
                                    echo "<a href='view_pdf.php?file=".urlencode($row['document'])."' target='_blank' class='font-semibold text-blue-700 px-2 py-1 rounded hover:text-purple-600'>View File</a>";
                                } else {
                                    echo "<span class='text-gray-400 italic'>No document available</span>";
                                }
                                ?>
                            </td>
                            <td class='py-2 px-4'><?php echo $row['time_period'];?></td>
                            <td class='py-2 px-4'><?php echo $paymentDue;?></td>
                            <td class='py-2 pt-3 px-4 text-center'>
                                <form method='POST' action='budget_request.php' id='approvalForm<?php echo $row['id']; ?>' class='inline-block'>
                                    <input type='hidden' name='approve_id' value='<?php echo $row['id']; ?>'>
                                    <input type='hidden' name='official_amount' id='official_amount_<?php echo $row['id']; ?>' value='<?php echo $row['amount']; ?>'>
                                    <button type='button' onclick='confirmApproval(<?php echo $row['id']; ?>, <?php echo $row['amount']; ?>)' class='text-green-500 px-2 py-1 rounded'><i class='fas fa-check'></i></button>
                                </form>
                                <button type='button' onclick='openModal(<?php echo $row['id']; ?>)' class='text-red-500 px-2 py-1 rounded'><i class='fas fa-trash'></i></button>
                            </td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                  </table>
                  <?php if (count($rows) === 0): ?>
                  <tr><td colspan='11' class='text-center py-4'>No records found</td></tr>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Rejected Requests Tab -->
            <div id="rejectedTab" class="tab-pane hidden">
              <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                  <i class="far fa-times-circle text-xl"></i>
                  <h2 class="text-2xl font-poppins text-black">Rejected Requests</h2>
                </div>
                <div class="overflow-x-auto w-full" id="rejectedContent">
                  <!-- Content will be loaded via AJAX -->
                  <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <p>Loading rejected requests...</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Archived Requests Tab -->
            <div id="archivedTab" class="tab-pane hidden">
              <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
                <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                  <i class="far fa-folder text-xl"></i>
                  <h2 class="text-2xl font-poppins text-black">Archived Requests</h2>
                </div>
                <div class="overflow-x-auto w-full" id="archivedContent">
                  <!-- Content will be loaded via AJAX -->
                  <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <p>Loading archived requests...</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 flex justify-between items-center">
            <div id="pageStatus" class="text-gray-700 font-bold"></div>
            <div class="flex">
                <button id="prevPage" class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500">Previous</button>
                <button id="nextPage" class="bg-purple-500 text-white px-4 py-2 rounded mr-2 hover:bg-violet-200 hover:text-violet-700 border border-purple-500">Next</button>
            </div>
        </div>
        
        <div class="mt-6">
            <canvas id="pdf-viewer" width="600" height="400"></canvas>
        </div>
    </div>

    <!-- Approval Confirmation Modal -->
    <div id='confirmModal' class='fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden z-50'>
        <div class='bg-white p-6 rounded-lg shadow-lg w-80 relative'>
            <h2 class='text-lg font-bold text-gray-800 mb-4 text-center'>Allocate Amount</h2>
            <p class='text-sm text-gray-600 mb-4'>Requested Amount: <span id='requestedAmount'></span></p>
            <p class='text-sm text-gray-600 mb-4'>Enter official amount:</p>
            <input type='number' id='officialAmount' class='border px-2 py-1 rounded w-full mb-4' placeholder='Enter amount'>
            <div class='flex justify-center space-x-4'>
                <button type='button' onclick='closeConfirmationModal()' class='bg-gray-300 text-gray-800 hover:bg-gray-400 px-4 py-2 rounded-lg'>
                    Cancel
                </button>
                <button type='button' onclick='submitApprovalForm()' class='bg-blue-500 text-white hover:bg-blue-600 px-4 py-2 rounded-lg'>
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Modal for Reject Reason -->
    <div id="rejectModal"
      class="modal fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden"
      tabindex="-1"
      role="dialog">
      <div class="bg-white p-6 rounded-lg shadow-lg w-120">
        <div class="flex justify-between items-center font-poppins mb-4">
          <h2 class="text-lg font-bold">Reason for Rejection</h2>
          <button type="button" aria-label="Close" onclick="closeModal()" class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <form id="rejectForm" method="POST" action="budget_request.php">
          <input type="hidden" name="reject_id" id="reject_id">
          <div>
            <label for="reason" class="text-sm block mb-2">Reason:</label>
            <textarea name="reason" id="reason" rows="4"
              class="w-full p-2 mt-2 bg-white text-black rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-purple-500"
              required></textarea>
          </div>
          <button type="button" onclick="handleRejectSubmit()"
            class="bg-purple-600 hover:bg-purple-700 mt-4 w-full text-white font-bold py-2 px-4 rounded">
            Submit
          </button>
        </form>
      </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="rejectconfirmationModal"
      class="modal fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden"
      tabindex="-1"
      role="dialog">
      <div class="bg-white p-6 rounded-lg shadow-lg w-80">
        <div class="text-center">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Confirm Rejection</h2>
          <p class="text-gray-600 mb-4">Are you sure you want to reject this request?</p>
          <div class="flex justify-center space-x-4">
            <button id="confirmRejectBtn"
              class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
              Yes
            </button>
            <button type="button" onclick="closeConfirmationModal()"
              class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
      // Show toast notification
      <?php if (isset($_SESSION['toast_message'])): ?>
      document.addEventListener('DOMContentLoaded', function() {
        const toast = document.getElementById('toast');
        if (toast) {
          toast.classList.add('show');
          setTimeout(function() {
            toast.classList.remove('show');
          }, 3000);
        }
      });
      <?php endif; ?>

      // Tab switching functionality
      document.addEventListener('DOMContentLoaded', function() {
        const statusTabs = document.querySelectorAll('#statusTabs .status-tab');
        
        statusTabs.forEach(tab => {
          tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update active tab
            statusTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding content
            document.querySelectorAll('.tab-pane').forEach(pane => {
              pane.classList.add('hidden');
              pane.classList.remove('active');
            });
            
            const targetPane = document.getElementById(tabName + 'Tab');
            targetPane.classList.remove('hidden');
            targetPane.classList.add('active');
            
            // Load content via AJAX if needed
            if (tabName === 'rejected') {
              loadRejectedRequests();
            } else if (tabName === 'archived') {
              loadArchivedRequests();
            }
          });
        });
        
        // Initialize table pagination and filtering
        initTable();
      });
      
      // Load rejected requests via AJAX
      function loadRejectedRequests() {
        const rejectedContent = document.getElementById('rejectedContent');
        if (rejectedContent.innerHTML.includes('Loading')) {
          fetch('rejected_request.php?ajax=1')
            .then(response => response.text())
            .then(data => {
              rejectedContent.innerHTML = data;
            })
            .catch(error => {
              rejectedContent.innerHTML = '<div class="text-center py-8 text-red-500">Error loading rejected requests</div>';
            });
        }
      }
      
      // Load archived requests via AJAX
      function loadArchivedRequests() {
        const archivedContent = document.getElementById('archivedContent');
        if (archivedContent.innerHTML.includes('Loading')) {
          fetch('archive.php?ajax=1')
            .then(response => response.text())
            .then(data => {
              archivedContent.innerHTML = data;
            })
            .catch(error => {
              archivedContent.innerHTML = '<div class="text-center py-8 text-red-500">Error loading archived requests</div>';
            });
        }
      }
      
      // Table filtering function
      function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const dueDate = document.getElementById('dueDate').value;
        const rows = document.querySelectorAll('#brTableBody tr');
        
        rows.forEach(row => {
          const refId = row.getAttribute('data-refid').toLowerCase();
          const acct = row.getAttribute('data-acct').toLowerCase();
          const dept = row.getAttribute('data-dept').toLowerCase();
          const category = row.getAttribute('data-category').toLowerCase();
          const desc = row.getAttribute('data-desc').toLowerCase();
          const amount = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
          const dueDateValue = row.getAttribute('data-duedate');
          
          const matchesSearch = refId.includes(search) || 
                              acct.includes(search) || 
                              dept.includes(search) || 
                              category.includes(search) || 
                              desc.includes(search) || 
                              amount.includes(search);
          
          const matchesDueDate = !dueDate || dueDateValue === dueDate;
          
          if (matchesSearch && matchesDueDate) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      }
      
      // Table pagination
      function initTable() {
        const rowsPerPage = 10;
        const table = document.getElementById('brTable');
        const tbodyRows = table.querySelectorAll('tbody tr');
        const pageStatus = document.getElementById('pageStatus');
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        
        let currentPage = 1;
        const totalPages = Math.ceil(tbodyRows.length / rowsPerPage);
        
        function showPage(page) {
          const start = (page - 1) * rowsPerPage;
          const end = start + rowsPerPage;
          
          tbodyRows.forEach((row, index) => {
            if (index >= start && index < end) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
          
          pageStatus.textContent = `Page ${page} of ${totalPages}`;
          
          prevBtn.disabled = page === 1;
          nextBtn.disabled = page === totalPages;
        }
        
        prevBtn.addEventListener('click', () => {
          if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
          }
        });
        
        nextBtn.addEventListener('click', () => {
          if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
          }
        });
        
        showPage(1);
      }
      
      // Modal functions
      function openModal(id) {
        document.getElementById('reject_id').value = id;
        document.getElementById('rejectModal').classList.remove('hidden');
      }
      
      function closeModal() {
        document.getElementById('rejectModal').classList.add('hidden');
      }
      
      function handleRejectSubmit() {
        const reason = document.getElementById('reason').value.trim();
        if (!reason) {
          alert('Please provide a reason for rejection.');
          return;
        }
        
        // Show confirmation modal
        document.getElementById('rejectconfirmationModal').classList.remove('hidden');
        
        // Set up the confirm button to submit the form
        document.getElementById('confirmRejectBtn').onclick = function() {
          document.getElementById('rejectForm').submit();
        };
      }
      
      function closeConfirmationModal() {
        document.getElementById('rejectconfirmationModal').classList.add('hidden');
      }
      
      // Approval functions
      function confirmApproval(id, amount) {
        document.getElementById('requestedAmount').textContent = '₱' + amount.toFixed(2);
        document.getElementById('officialAmount').value = amount;
        document.getElementById('officialAmount').setAttribute('data-id', id);
        document.getElementById('confirmModal').classList.remove('hidden');
      }
      
      function closeConfirmationModal() {
        document.getElementById('confirmModal').classList.add('hidden');
      }
      
      function submitApprovalForm() {
        const id = document.getElementById('officialAmount').getAttribute('data-id');
        const amount = document.getElementById('officialAmount').value;
        
        if (!amount || amount <= 0) {
          alert('Please enter a valid amount');
          return;
        }
        
        document.getElementById('official_amount_' + id).value = amount;
        document.getElementById('approvalForm' + id).submit();
      }
    </script>
  </body>
</html>