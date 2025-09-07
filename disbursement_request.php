<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Approve Handler (PHP POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $servername = "localhost";
    $usernameDB = "financial";
    $passwordDB = "UbrdRDvrHRAyHiA]";
    $dbname = "financial_db";
    $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
    $approveId = intval($_POST['approve_id']);

    $mode_sql = "SELECT mode_of_payment, reference_id FROM pa WHERE id = ?";
    $stmt_mode = $conn->prepare($mode_sql);
    $stmt_mode->bind_param("i", $approveId);
    $stmt_mode->execute();
    $stmt_mode->bind_result($mode_of_payment, $reference_id);
    $stmt_mode->fetch();
    $stmt_mode->close();

    if (empty($mode_of_payment)) {
        $alert = "<div class='bg-red-500 text-white p-4 rounded'>Error: Mode of payment not found or invalid for ID $approveId.</div>";
    } else {
        $conn->begin_transaction();
        if ($mode_of_payment == 'Bank' || $mode_of_payment == 'Bank Transfer') {
            $insert_sql = "
            INSERT INTO bank (
              id, account_name, requested_department, expense_categories, amount, description, document, 
              payment_due, bank_name, bank_account_name, bank_account_number, reference_id, mode_of_payment
            )
            SELECT 
              id, account_name, requested_department, expense_categories, amount, description, document, 
              payment_due, bank_name, bank_account_name, bank_account_number,
              CONCAT('BNK-', SUBSTRING(reference_id, 4)) AS reference_id, mode_of_payment
            FROM pa 
            WHERE id = ?
            ";
        } elseif ($mode_of_payment == 'Cash') {
            $insert_sql = "
            INSERT INTO cash (
              id, account_name, requested_department, expense_categories, amount, description, document, 
              payment_due, reference_id, mode_of_payment
            )
            SELECT 
              id, account_name, requested_department, expense_categories, amount, description, document, 
              payment_due,
              CONCAT('C-', SUBSTRING(reference_id, 4)) AS reference_id, mode_of_payment
            FROM pa 
            WHERE id = ?
            ";
        } elseif ($mode_of_payment == 'Ecash') {
            $insert_sql = "
            INSERT INTO ecash (
              id, account_name, requested_department, expense_categories, amount, description, document, 
              payment_due, ecash_provider, ecash_account_name, ecash_account_number, reference_id, mode_of_payment
            )
            SELECT 
              id, account_name, requested_department, expense_categories, amount, description, document, 
              payment_due, ecash_provider, ecash_account_name, ecash_account_number,
              CONCAT('EC-', SUBSTRING(reference_id, 4)) AS reference_id, mode_of_payment
            FROM pa 
            WHERE id = ?
            ";
        }else {
            $alert = "<div class='bg-red-500 text-white p-4 rounded'>Invalid mode of payment: " . htmlspecialchars($mode_of_payment) . "</div>";
        }
        if (!isset($alert)) {
            $stmt_insert = $conn->prepare($insert_sql);
            $stmt_insert->bind_param("i", $approveId);
            try {
                if ($stmt_insert->execute()) {
                    $delete_sql = "DELETE FROM pa WHERE id = ?";
                    $stmt_delete = $conn->prepare($delete_sql);
                    $stmt_delete->bind_param("i", $approveId);
                    if ($stmt_delete->execute()) {
                        $update_tr_sql = "UPDATE tr SET status = 'disbursed' WHERE reference_id = ?";
                        $stmt_update_tr = $conn->prepare($update_tr_sql);
                        $stmt_update_tr->bind_param("s", $reference_id);
                        $stmt_update_tr->execute();
                        $conn->commit();
                        $alert = "<div class='bg-green-500 text-white p-4 rounded'>Payout Approved and moved to the appropriate table!</div>";
                    } else {
                        throw new Exception("Error deleting record from pa: " . $stmt_delete->error);
                    }
                } else {
                    throw new Exception("Error inserting record: " . $stmt_insert->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $alert = "<div class='bg-red-500 text-white p-4 rounded'>Transaction failed: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Fetch PENDING payouts only (for approval)
$servername = "localhost";
$usernameDB = "financial";
$passwordDB = "UbrdRDvrHRAyHiA]";
$dbname = "financial_db";
$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$sql = "SELECT * FROM pa WHERE from_payable = 1 ORDER BY requested_at DESC";
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <title>Payout Approval</title>
  <link rel="icon" href="logo.png" type="img">
  <style>
    @media (max-width: 1024px) {
      .overview-flex { flex-direction: column !important; }
      .overview-left, .overview-right { width: 100% !important; }
      .overview-cards { flex-direction: column !important; }
      .overview-right { min-width: 0 !important; }
    }
  </style>
</head>
<body class="bg-white">
  <?php include('sidebar.php'); ?>
  <div class="overflow-y-auto h-full px-6">
      <div class="flex justify-between items-center px-6 py-6 font-poppins">
          <h1 class="text-2xl">Disbursement Request</h1>
          <div class="text-sm">
              <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
              /
              <a class="text-black">Disbursement</a>
              /
              <a href="payout_approval.php" class="text-blue-600 hover:text-blue-600">Disbursement Request</a>
          </div>
      </div>

      <?php if (isset($alert)) echo $alert; ?>

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
              <span class="font-semibold text-base text-gray-700">Total Amount Due</span>
            </div>
            <span class="text-2xl font-bold text-red-600">₱ <?php echo number_format($total_amount_due, 2); ?></span>
          </div>
        </div>
      </div>

      <div class="flex-1 bg-white p-6 h-full w-full">
        <div class="w-full">
          <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300 mb-4">
              <button type="button" class="mode-tab px-4 py-2 rounded-t-full border-b-4 border-yellow-400 text-yellow-600 font-semibold" data-mode="all">ALL</button>
              <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="cash">CASH</button>
              <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="bank">BANK</button>
              <button type="button" class="mode-tab px-4 py-2 rounded-t-full text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300" data-mode="ecash">ECASH</button>
          </div>
          <div class="flex justify-start items-center mb-4 space-x-2">
              <input type="text" id="searchInput" class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Search here" onkeyup="filterTable()" />
              <label for="dueDate" class="font-semibold ml-2">Payment Due:</label>
              <input type="date" id="dueDate" class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm" onchange="filterTable()" />
          </div>

          <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
              <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
                  <i class="far fa-file-alt text-xl"></i>
                  <h2 class="text-2xl font-poppins text-black">Pending Requests</h2>
              </div>
              <div class="overflow-x-auto w-full">
                  <table class="w-full table-auto bg-white mt-4" id="payoutTable">
                    <thead>
                      <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                        <th class="pl-10 pr-6 py-2">Reference ID</th>
                        <th class="px-6 py-2">Account Name</th>
                        <th class="px-6 py-2">Department</th>
                        <th class="px-6 py-2">Mode of Payment</th>
                        <th class="px-6 py-2">Category</th>
                        <th class="px-6 py-2">Amount</th>
                        <th class="px-6 py-2">Description</th>
                        <th class="px-6 py-2">Document</th>
                        <th class="px-6 py-2">Payment Due</th>
                        <th class="px-6 py-2">Priority</th>
                        <th class="px-6 py-2">Action</th>
                      </tr>
                    </thead>
                    <tbody class="text-gray-900 text-sm font-light" id="payoutTableBody">
                      <?php
                      foreach ($rows as $row):
                          $paymentDue = $row['payment_due'] ? date('Y-m-d', strtotime($row['payment_due'])) : '';
                          ?>
                          <tr class= hover:bg-violet-100 data-mode="<?php echo strtolower($row['mode_of_payment']); ?>"
                              data-refid="<?php echo htmlspecialchars($row['reference_id']); ?>"
                              data-acct="<?php echo htmlspecialchars($row['account_name']); ?>"
                              data-dept="<?php echo htmlspecialchars($row['requested_department']); ?>"
                              data-category="<?php echo htmlspecialchars($row['expense_categories']); ?>"
                              data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                              data-duedate="<?php echo $paymentDue; ?>">
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
                              <td class='py-2 px-4'><?php echo $paymentDue;?></td>
                              <td class='py-2 px-4 priority-cell'></td>
                              <td class='py-2 px-4'>
                                  <form method="POST" style="display:inline">
                                      <input type="hidden" name="approve_id" value="<?php echo $row['id'];?>" />
                                      <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-700 font-bold">Approve</button>
                                  </form>
                              </td>
                          </tr>
                      <?php endforeach;?>
                    </tbody>
                  </table>
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
      </div>
  </div>
<script>
// Priority logic
function getPriorityLevel(payment_due) {
    if (!payment_due) return 4;
    const today = new Date();
    const due = new Date(payment_due);
    today.setHours(0,0,0,0); due.setHours(0,0,0,0);
    const diff = Math.ceil((due - today) / (1000 * 60 * 60 * 24));
    if (diff < 0) return 1;
    if (diff <= 3) return 2;
    if (diff <= 7) return 3;
    return 4;
}
function priorityLabel(level) {
    switch(level) {
        case 1: return '<span class="font-bold text-red-500">1 (Overdue)</span>';
        case 2: return '<span class="font-bold text-yellow-500">2 (Within 3 days)</span>';
        case 3: return '<span class="font-bold text-blue-500">3 (Within 7 days)</span>';
        default: return '<span class="font-bold text-green-600">4 (Low)</span>';
    }
}
let modeOfPayment = 'all';
let currentPage = 1;
const rowsPerPage = 10;
function filterAndPaginateRows() {
    const allRows = Array.from(document.querySelectorAll('#payoutTableBody > tr'));
    const search = document.getElementById('searchInput').value.toLowerCase();
    const dueDate = document.getElementById('dueDate').value;
    return allRows.filter(row => {
        if (modeOfPayment !== 'all') {
    let rowMode = row.getAttribute('data-mode');
    if (modeOfPayment === 'bank') {
        // Match both 'bank' and 'bank transfer'
        if (!(rowMode === 'bank' || rowMode === 'bank transfer')) return false;
    } else {
        if (rowMode !== modeOfPayment) return false;
    }
}
        let refid = row.getAttribute('data-refid').toLowerCase();
        let acct = row.getAttribute('data-acct').toLowerCase();
        let dept = row.getAttribute('data-dept').toLowerCase();
        let desc = row.getAttribute('data-desc').toLowerCase();
        let category = row.getAttribute('data-category').toLowerCase();
        let rowDue = row.getAttribute('data-duedate');
        let matchSearch = refid.includes(search) || acct.includes(search) || dept.includes(search) || desc.includes(search) || category.includes(search) || (rowDue && rowDue.includes(search));
        let matchDate = (!dueDate || rowDue === dueDate);
        return matchSearch && matchDate;
    });
}
function renderTable(page) {
    const allRows = Array.from(document.querySelectorAll('#payoutTableBody > tr'));
    allRows.forEach(row => row.style.display = 'none');
    let rows = filterAndPaginateRows();
    let start = (page-1) * rowsPerPage;
    let end = start + rowsPerPage;
    let paginated = rows.slice(start, end);
    paginated.forEach(row => row.style.display = '');
    paginated.forEach(row => {
        let cell = row.querySelector('.priority-cell');
        let paymentDue = row.getAttribute('data-duedate');
        let p = getPriorityLevel(paymentDue);
        cell.innerHTML = priorityLabel(p);
    });
    document.getElementById("prevPage").disabled = currentPage === 1;
    document.getElementById("nextPage").disabled = end >= rows.length;
    const pageStatus = document.getElementById("pageStatus");
    const totalPages = Math.max(1, Math.ceil(rows.length / rowsPerPage));
    pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
}
function updateOverviewAndChart() {
    let visibleRows = filterAndPaginateRows();
    document.getElementById("totalPayouts").textContent = visibleRows.length;
    document.getElementById("cashCount").textContent = visibleRows.filter(r => r.getAttribute('data-mode') === 'cash').length;
    document.getElementById("bankCount").textContent = visibleRows.filter(r => r.getAttribute('data-mode').includes('bank')).length;
    document.getElementById("ecashCount").textContent = visibleRows.filter(r => r.getAttribute('data-mode') === 'ecash').length;
    const mopLabels = ["Cash","Bank","Ecash"];
    const mopColors = ["#059669","#6366f1","#f59e42"];
    const mopCounts = [
        <?php echo $pending_cash; ?>,
        <?php echo $pending_bank; ?>,
        <?php echo $pending_ecash; ?>
    ];
};
document.querySelectorAll('.mode-tab').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.mode-tab').forEach(tab => tab.classList.remove('border-b-4','border-yellow-400','text-yellow-600','font-semibold'));
        this.classList.add('border-b-4','border-yellow-400','text-yellow-600','font-semibold');
        modeOfPayment = this.getAttribute('data-mode');
        currentPage = 1;
        renderTable(currentPage);
        updateOverviewAndChart();
    });
});
document.getElementById('searchInput').addEventListener('input', () => { currentPage = 1; renderTable(currentPage); updateOverviewAndChart(); });
document.getElementById('dueDate').addEventListener('change', () => { currentPage = 1; renderTable(currentPage); updateOverviewAndChart(); });
document.getElementById('prevPage').addEventListener('click', () => { if(currentPage>1){currentPage--;renderTable(currentPage);updateOverviewAndChart();}});
document.getElementById('nextPage').addEventListener('click', () => { let rows = filterAndPaginateRows();if(currentPage*rowsPerPage<rows.length){currentPage++;renderTable(currentPage);updateOverviewAndChart();}});
window.onload = () => { renderTable(currentPage); updateOverviewAndChart(); };
</script>
</body>
</html>