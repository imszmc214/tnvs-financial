<?php
session_start();

// Check if the user is logged in
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

    // Handle approval action
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['approve_id'])) {
        $approveId = $_POST['approve_id'];

        $fetch_sql = "SELECT expense_categories, mode_of_payment, amount FROM ecash WHERE id = ?";
        $stmt_fetch = $conn->prepare($fetch_sql);
        $stmt_fetch->bind_param("i", $approveId);
        $stmt_fetch->execute();
        $stmt_fetch->bind_result($expense_category, $mode_of_payment, $amount);
        $stmt_fetch->fetch();
        $stmt_fetch->close();

         if ($expense_category === 'Account Payable') {
           $update_journal_sql = "UPDATE ledger_table 
           SET debit_amount = debit_amount - ?
           WHERE expense_categories = 'Accounts Payables' AND credit_account = 'Liabilities'";
           $stmt_update_journal = $conn->prepare($update_journal_sql);
           $stmt_update_journal->bind_param("d", $amount);
           $stmt_update_journal->execute();

           $update_journal_sql = "UPDATE ledger_table 
                                SET debit_amount = 0, credit_amount = credit_amount - ?
                                WHERE expense_categories = 'Ecash' AND credit_account = 'Assets'";
           $stmt_update_journal = $conn->prepare($update_journal_sql);
           $stmt_update_journal->bind_param("d", $amount);
           $stmt_update_journal->execute();
         } else {
           $update_journal_sql = "UPDATE ledger_table 
                                SET debit_amount = debit_amount + ?, credit_amount = 0 
                                WHERE expense_categories = 'Equipments/Assets' AND credit_account = 'Expense'";
           $stmt_update_journal = $conn->prepare($update_journal_sql);
           $stmt_update_journal->bind_param("d", $amount);
           $stmt_update_journal->execute();

           $update_journal_sql = "UPDATE ledger_table 
                                SET debit_amount = 0, credit_amount = credit_amount - ?
                               WHERE expense_categories = 'Ecash' AND credit_account = 'Assets'";
           $stmt_update_journal = $conn->prepare($update_journal_sql);
           $stmt_update_journal->bind_param("d", $amount);
           $stmt_update_journal->execute();
          }

      
          // Insert a new debit entry for 'Accounts Payables' under Liabilities
          $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
                                 VALUES ('Accounts Payables', ?, 0, 'Liabilities', NOW())";
          $stmt_insert_journal = $conn->prepare($insert_journal_sql);
          $stmt_insert_journal->bind_param("d", $amount);
          $stmt_insert_journal->execute();

          // Insert a new credit entry for 'Ecash' under Assets
          $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
                                 VALUES ('Ecash', 0, ?, 'Assets', NOW())";
          $stmt_insert_journal = $conn->prepare($insert_journal_sql);
          $stmt_insert_journal->bind_param("d", $amount);
          $stmt_insert_journal->execute();
        } else {
          // Insert a new debit entry for 'Equipments/Assets' under Expense
          $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
                                 VALUES ('Equipments/Assets', ?, 0, 'Expense', NOW())";
          $stmt_insert_journal = $conn->prepare($insert_journal_sql);
          $stmt_insert_journal->bind_param("d", $amount);
          $stmt_insert_journal->execute();

          // Insert a new credit entry for 'Ecash' under Assets
          $insert_journal_sql = "INSERT INTO ledger_table (expense_categories, debit_amount, credit_amount, credit_account, transaction_date)
                                 VALUES ('Ecash', 0, ?, 'Assets', NOW())";
          $stmt_insert_journal = $conn->prepare($insert_journal_sql);
          $stmt_insert_journal->bind_param("d", $amount);
          $stmt_insert_journal->execute();
        }

    // Insert into the approved disbursements table (dr) with all ecash fields
    $insert_sql = "INSERT INTO dr (
        id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due, ecash_provider, ecash_account_name, ecash_account_number, reference_id
      )
      SELECT 
        id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due, ecash_provider, ecash_account_name, ecash_account_number,
        SUBSTRING(reference_id, 4) AS reference_id
      FROM ecash WHERE id = '$approveId'";

    if ($conn->query($insert_sql) === TRUE) {
      // Update status in tr table
      $update_tr_sql = "UPDATE tr SET status = 'approved' WHERE id = '$approveId'";
      if ($conn->query($update_tr_sql) === TRUE) {
        // After successful insertion, delete the record from ecash
        $delete_sql = "DELETE FROM ecash WHERE id = '$approveId'";
        if ($conn->query($delete_sql) === TRUE) {
          echo "<div class='bg-green-500 text-white p-4 rounded mb-4'>Disbursement Approved!</div>";
        } else {
          echo "<div class='bg-red-500 text-white p-4 rounded mb-4'>Error deleting record: " . $conn->error . "</div>";
        }
      } else {
        echo "<div class='bg-red-500 text-white p-4 rounded mb-4'>Error updating status in tr: " . $conn->error . "</div>";
      }
    } else {
      echo "<div class='bg-red-500 text-white p-4 rounded mb-4'>Error inserting record into dr: " . $conn->error . "</div>";
    }
  }

// Fetch all ecash records for the table
$sql = "SELECT * FROM ecash ORDER BY id DESC";
$result = $conn->query($sql);
?>
<html>
<head>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
  <title>Ecash Payout</title>
  <link rel="icon" href="logo.png" type="img">
  <script>
    function confirmDisburse(id) {
      document.getElementById('approve_id').value = id;
      document.getElementById('confirmationModal').classList.remove('hidden');
    }

    function closeModal() {
      document.getElementById('confirmationModal').classList.add('hidden');
    }
  </script>
</head>
<body class="bg-white">
  <?php include('sidebar.php'); ?>
  <!-- Breadcrumb -->
  <div class="overflow-y-auto h-full px-6">
    <div class="flex justify-between items-center px-6 py-6 font-poppins">
      <h1 class="text-2xl">Ecash Payout</h1>
      <div class="text-sm">
        <a href="dashboard.php?page=dashboard" class="text-black hover:text-blue-600">Home</a>
        /
        <a class="text-black">Disbursement</a>
        /
        <a href="ecash.php" class="text-blue-600 hover:text-blue-600">Ecash Payout</a>
      </div>
    </div>
    <div class="flex-1 bg-white p-6 h-full w-full">
      <div class="w-full">
        <div>
          <div class="flex items-center justify-between">
            <form method="GET" action="ecash.php" class="flex flex-wrap items-center gap-4 mb-4">
              <input type="text" id="searchInput" class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400" placeholder="Search here" />
              <div class="flex items-center space-x-2">
                <label for="dueDate" class="font-semibold">Payment Due:</label>
                <input type="date" id="dueDate" class="border border-gray-300 rounded-lg px-2 py-1 shadow-sm" />
              </div>
            </form>
          </div>
          <!-- Main content area -->
          <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 mb-6">
            <div class="flex items-center mt-4 mb-4 mx-4 space-x-3 text-purple-700">
              <i class="far fa-file-alt text-xl"></i>
              <h2 class="text-2xl font-poppins text-black">Ecash Payout</h2>
            </div>
            <!-- TABLE -->
            <div class="overflow-x-auto w-full">
              <table class="w-full table-auto bg-white mt-4" id="ecashTable">
                <thead>
                  <tr class="text-blue-800 uppercase text-sm leading-normal text-left">
                    <th class="pl-10 pr-6 py-2">ID</th>
                    <th class="px-4 py-2">Reference ID</th>
                    <th class="px-4 py-2">Account Name</th>
                    <th class="px-4 py-2">Requested Department</th>
                    <th class="px-4 py-2">Mode of Payment</th>
                    <th class="px-4 py-2">Expense Categories</th>
                    <th class="px-4 py-2">Amount</th>
                    <th class="px-4 py-2">Ecash Provider</th>
                    <th class="px-4 py-2">Ecash Account Name</th>
                    <th class="px-4 py-2">Ecash Account Number</th>
                    <th class="px-4 py-2">Payment Due</th>
                    <th class="px-4 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody class="text-gray-900 text-sm">
                  <?php
                  if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                      $payment_due = $row['payment_due'] ? date('Y-m-d', strtotime($row['payment_due'])) : '';
                      echo "<tr class='hover:bg-violet-100'>";
                      echo "<td class='pl-10 pr-6 py-2'>{$row['id']}</td>";
                      echo "<td class='px-4 py-2'>{$row['reference_id']}</td>";
                      echo "<td class='px-4 py-2'>{$row['account_name']}</td>";
                      echo "<td class='px-4 py-2'>{$row['requested_department']}</td>";
                      echo "<td class='px-4 py-2'>{$row['mode_of_payment']}</td>";
                      echo "<td class='px-4 py-2'>{$row['expense_categories']}</td>";
                      echo "<td class='px-4 py-2'>₱" . number_format($row['amount'], 2) . "</td>";
                      echo "<td class='px-4 py-2'>{$row['ecash_provider']}</td>";
                      echo "<td class='px-4 py-2'>{$row['ecash_account_name']}</td>";
                      echo "<td class='px-4 py-2'>{$row['ecash_account_number']}</td>";
                      echo "<td class='px-4 py-2'>{$payment_due}</td>";
                      echo "<td class='px-4 py-2'>
                            <button type='button' class='disburseButton bg-green-500 text-white py-1 px-2 rounded-full font-semibold' data-approve-id='{$row['id']}'>Disburse</button>
                          </td>";
                      echo "</tr>";
                    }
                  } else {
                    echo "<tr><td colspan='12' class='text-center py-3'>No records found</td></tr>";
                  }
                  ?>
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
  <!-- Modal -->
  <div id="disburseModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white p-6 rounded shadow-lg">
      <h2 class="text-xl font-bold mb-4">Confirm Disbursement</h2>
      <p>Are you sure you want to disburse this?</p>
      <div class="mt-4 flex items-center justify-end">
        <button id="cancelButton" class="bg-gray-500 text-white px-4 py-2 rounded mr-2">Cancel</button>
        <form id="disburseForm" method="POST">
          <input type="hidden" name="approve_id" id="approveIdInput">
          <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded mt-4">Disburse</button>
        </form>
      </div>
    </div>
  </div>

<script>
    // Modal logic
    document.querySelectorAll('.disburseButton').forEach(button => {
      button.addEventListener('click', function() {
        const approveId = this.getAttribute('data-approve-id');
        document.getElementById('approveIdInput').value = approveId;
        document.getElementById('disburseModal').classList.remove('hidden');
      });
    });

    document.getElementById('cancelButton').addEventListener('click', function() {
      document.getElementById('disburseModal').classList.add('hidden');
    });

    // Pagination/filter/search logic
let table = document.querySelector("#ecashTable");
let tbody = table.querySelector("tbody");
let masterRows = Array.from(tbody.querySelectorAll("tr"));
let currentPage = 1;
const rowsPerPage = 10;

function displayData(page) {
  let filteredRows = filterRows();
  tbody.innerHTML = "";
  let start = (page - 1) * rowsPerPage;
  let end = start + rowsPerPage;
  let paginatedRows = filteredRows.slice(start, end);

  if (paginatedRows.length === 0) {
    tbody.innerHTML = "<tr><td colspan='12' class='text-center py-4'>No records found</td></tr>";
  } else {
    paginatedRows.forEach(row => tbody.appendChild(row));
  }

  document.getElementById("prevPage").disabled = currentPage === 1;
  document.getElementById("nextPage").disabled = end >= filteredRows.length;
  const pageStatus = document.getElementById("pageStatus");
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
  pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
}

function filterRows() {
  const searchInput = document.getElementById("searchInput").value.toLowerCase();
  const dueDate = document.getElementById("dueDate").value;
  return masterRows.filter(row => {
    const cells = row.children;
    if (cells.length < 12) return false;
    const id = cells[0].textContent.toLowerCase();
    const referenceId = cells[1].textContent.toLowerCase();
    const accountName = cells[2].textContent.toLowerCase();
    const department = cells[3].textContent.toLowerCase();
    const modeOfPayment = cells[4].textContent.toLowerCase();
    const expenseCategories = cells[5].textContent.toLowerCase();
    const amount = cells[6].textContent.trim();
    const ecashProvider = cells[7].textContent.toLowerCase();
    const ecashAccountName = cells[8].textContent.toLowerCase();
    const ecashAccountNumber = cells[9].textContent.trim();
    const rowDate = cells[10].textContent.trim();
    const matchSearch = (
      id.includes(searchInput) ||
      referenceId.includes(searchInput) ||
      accountName.includes(searchInput) ||
      department.includes(searchInput) ||
      modeOfPayment.includes(searchInput) ||
      expenseCategories.includes(searchInput) ||
      amount.includes(searchInput) ||
      ecashProvider.includes(searchInput) ||
      ecashAccountName.includes(searchInput) ||
      ecashAccountNumber.includes(searchInput)
    );
    const matchDate = (!dueDate || rowDate === dueDate);
    return matchSearch && matchDate;
  });
}

function filterTable() {
  currentPage = 1;
  displayData(currentPage);
}

function prevPage() {
  if (currentPage > 1) {
    currentPage--;
    displayData(currentPage);
  }
}

function nextPage() {
  let filteredRows = filterRows();
  if (currentPage * rowsPerPage < filteredRows.length) {
    currentPage++;
    displayData(currentPage);
  }
}

document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('dueDate').addEventListener('change', filterTable);
document.getElementById('prevPage').addEventListener('click', prevPage);
document.getElementById('nextPage').addEventListener('click', nextPage);

window.onload = () => {
  displayData(currentPage);
};
</script>
</body>
</html>