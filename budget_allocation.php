<?php 
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['add'])) {
    // Enable error reporting
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Validate required fields
    $required = ['department', 'category', 'from_allocated', 'to_allocated', 'allocated_amount'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die("Error: Field '$field' is required!");
        }
    }

    // Sanitize and validate inputs
    $department = trim($_POST['department']);
    $category = trim($_POST['category']);
    $from_allocated = $_POST['from_allocated'];
    $to_allocated = $_POST['to_allocated'];
    $allocated_amount = (float)$_POST['allocated_amount'];
    $spent = 0;
    $remaining_balance = $allocated_amount;

    // Validate department name
    if (strlen($department) < 2 || strlen($department) > 255) {
        die("Department name must be 2-255 characters");
    }

    // Validate dates
    if (!DateTime::createFromFormat('Y-m-d', $from_allocated) || 
        !DateTime::createFromFormat('Y-m-d', $to_allocated)) {
        die("Invalid date format. Use YYYY-MM-DD");
    }

    // Validate amount
    if ($allocated_amount <= 0) {
        die("Allocated amount must be greater than 0");
    }

    // Database insertion
    $stmt = $conn->prepare("INSERT INTO budget_allocations 
        (department, category, from_allocated, to_allocated, allocated_amount, remaining_balance, spent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssddi", 
        $department, 
        $category, 
        $from_allocated, 
        $to_allocated, 
        $allocated_amount, 
        $remaining_balance, 
        $spent
    );

    if ($stmt->execute()) {
        // Success handling
        header("Location: budget_allocation.php");
        exit();
    } else {
        die("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
}

// Update budget allocation
if (isset($_POST['update'])) {
  $id = $_POST['id'];
  $department = $_POST['department'];
  $category = $_POST['category'];
  $from_allocated = $_POST['from_allocated'];
  $to_allocated = $_POST['to_allocated'];
  $allocated_amount = $_POST['allocated_amount'];
  $remaining_balance = $_POST['remaining_balance'];
  $spent = $_POST['spent'];

  $stmt = $conn->prepare("UPDATE budget_allocations SET department=?, category=?, from_allocated=?, to_allocated=?, allocated_amount=?, remaining_balance=?, spent=? WHERE id=?");
  $stmt->bind_param("ssssdddi", $department, $category, $from_allocated, $to_allocated, $allocated_amount, $remaining_balance, $spent, $id);
  $stmt->execute();
  $stmt->close();
}


// Delete budget allocation
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM budget_allocations WHERE id=$id");
}

// --- FILTER SETUP ---

// Capture the department filter from GET parameters
$department_filter = isset($_GET['department_filter']) ? $_GET['department_filter'] : '';

// Capture the date filter from GET parameters (default to 'all')
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build an array for conditions
$conditions = [];

// Add the department filter if provided
if ($department_filter != '') {
    $conditions[] = "department = '" . $conn->real_escape_string($department_filter) . "'";
}

// Add date condition based on the filter type
if ($filter === 'weekly') {
    // Weekly: Duration between 7 and 27 days
    $conditions[] = "TIMESTAMPDIFF(DAY, from_allocated, to_allocated) BETWEEN 7 AND 25";
} elseif ($filter === 'monthly') {
    // Monthly: Duration between 1 and 2 months
    $conditions[] = "TIMESTAMPDIFF(DAY, from_allocated, to_allocated) BETWEEN 27 AND 80";
} elseif ($filter === 'quarterly') {
    // Quarterly: Duration between 3 and 11 months
    $conditions[] = "TIMESTAMPDIFF(DAY, from_allocated, to_allocated) BETWEEN 81 AND 350";
} elseif ($filter === 'yearly') {
    // Yearly: Duration greater than 360 days
    $conditions[] = "TIMESTAMPDIFF(DAY, from_allocated, to_allocated) > 351";
}

// --- Count Total Records ---
$count_sql = "SELECT COUNT(*) as total FROM budget_allocations";
if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}
// Set pagination limit FIRST
$records_per_page = 10;

// Then calculate page and offset
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

$count_result = $conn->query($count_sql);
$row_count = $count_result->fetch_assoc();
$total_rows = $row_count['total'];
$total_pages = ($total_rows > 0) ? ceil($total_rows / $records_per_page) : 1;

// --- Build Main Query with LIMIT ---
$sql = "SELECT id, department, category, from_allocated, to_allocated, allocated_amount, remaining_balance, spent 
        FROM budget_allocations";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

      $sql .= " ORDER BY id DESC";

      // Calculate total pages
      $total_pages = ($total_rows > 0) ? ceil($total_rows / $records_per_page) : 1;

      // Build base query string (preserving filters like timePeriod & searchID)
      $queryParams = $_GET;
      unset($queryParams['page']); // remove page param so we can add it manually
      $baseUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($queryParams);

      // Create prev and next links
      $prevLink = $baseUrl . '&page=' . ($page - 1);
      $nextLink = $baseUrl . '&page=' . ($page + 1);

// --- Build Final SQL Query with LIMIT and OFFSET ---
$sql = "SELECT id, department, category, from_allocated, to_allocated, allocated_amount, remaining_balance, spent 
        FROM budget_allocations";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY id DESC";

// ✅ Add pagination here
$sql .= " LIMIT $records_per_page OFFSET $offset";

// Now execute the query
$result = $conn->query($sql);
?>


<html>
 <head>
  <title>Budget Allocation</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
 </head>
 
  <body class="bg-blue-100 overflow-hidden">
    
    <?php include('sidebar.php'); ?>
 <div class="overflow-y-auto h-full px-6">
    <!-- Breadcrumb -->
  <div class="bg-white p-4">
      <nav class="text-[#4311A5] font-poppins text-2xl">
        <ol class="list-reset flex">
          <li>
            <a class="text-black hover:text-[#4311A5]" href="dashboard.php">Dashboard</a>
          </li>
          <li><span class="mx-2">&gt;</span></li>
          <li>
            <a class="text-black hover:text-[#4311A5]" href="budget_request.php">Budget</a>
          </li>
          <li><span class="mx-2">&gt;</span></li>
          <li>
            <a class="text-black hover:text-[#4311A5]" href="budget_allocation.php">Budget Allocation</a>
          </li>
        </ol>
      </nav>
    </div>


    <!-- Main content area -->
    <div class="flex-1 p-6 w-full">
        <h1 class="font-bold font-[Poppins] text-[#0e1847] text-3xl mb-6 text-center">Budget Allocation</h1>

        <div class=" w-full">
          <div class="flex items-center justify-between w-full">
            <!-- Filter Form -->
            <form method="GET" action="budget_allocation.php" class="flex flex-wrap items-center justify-between mb-6">
              <!-- Time Period Tabs (Left) -->
              <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300">
                <?php
                  $tabs = ['all', 'weekly', 'monthly', 'quarterly', 'yearly'];
                  foreach ($tabs as $tab) {
                    $isActive = ($filter === $tab);
                    echo '<button type="submit" name="filter" value="' . $tab . '" class="px-4 py-2 rounded-t-full transition-all ' .
                      ($isActive
                        ? 'border-b-4 border-yellow-400 text-yellow-600 font-semibold'
                        : 'text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300') .
                      '">' . strtoupper($tab) . '</button>';
                  }
                ?>
              </div>

              <!-- Department Dropdown (Right) -->
              <div class="flex items-center gap-6">
                <label for="department_filter" class="font-poppins text-sm"></label>
                <select name="department_filter" id="department_filter"
                  class="font-poppins border px-3 py-2 rounded-full text-sm"
                  onchange="this.form.submit()">
                  <option value="">All Departments</option>
                  <?php
                    $departments = [
                      'Administrative', 'Core-1', 'Core-2',
                      'Human Resource-1', 'Human Resource-2', 'Human Resource-3', 'Human Resource-4',
                      'Logistic-1', 'Logistic-2', 'Financial'
                    ];
                    foreach ($departments as $dept) {
                      $selected = ($department_filter === $dept) ? 'selected' : '';
                      echo "<option value=\"$dept\" $selected>$dept</option>";
                    }
                  ?>
                </select>
              </div>
            </form>
            
            <div><button class="bg-purple-700 text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:text-white px-4 py-2 rounded-md mb-4 float-right" onclick="openModal('addModal')">Add New Allocation</button></div>
          </div>
  
        <div class="bg-white">
          <table class="min-w-full bg-white border-white">
            <thead class="bg-white">
              <tr class="py-2 px-4 text-center text-m font-bold text-blue-900">
                <th>Department</th>
                <th>Expesne Category</th>
                <th>From</th>
                <th>To</th>
                <th>Allocated Amount</th>
                <th>Amount Spent</th>
                <th>Remaining Balance</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody class="font-semilight">
              <?php
              $totalAllocated = 0;
              $totalSpent = 0;
              $totalRemaining = 0;

              while ($row = $result->fetch_assoc()) {
                  // Add to the totals
                  $totalAllocated += $row['allocated_amount'];
                  $totalSpent += $row['spent'];
                  $totalRemaining += $row['allocated_amount'] - $row['spent'];
              ?>
              <tr class="hover:bg-purple-200 text-center text-sm py-2 px-4 text-gray-800 ">
                  <td><?= $row['department'] ?></td>
                  <td><?= $row['category'] ?></td>
                  <td><?= $row['from_allocated'] ?></td>
                  <td><?= $row['to_allocated'] ?></td>
                  <td>₱<?= number_format($row['allocated_amount'], 2) ?></td>
                  <td><?= number_format($row['spent'], 2) ?></td>
                  <td>₱<?= number_format($row['allocated_amount'] - $row['spent'], 2) ?></td>
                  <td class="text-center text-sm py-2 px-4 space-x-2">
                      <button class="bg-blue-700 text-white px-4 py-1 rounded-md" onclick="editBudget(<?= $row['id'] ?>, '<?= $row['department'] ?>', '<?= $row['category'] ?>', '<?= $row['from_allocated'] ?>', '<?= $row['to_allocated'] ?>',<?= $row['allocated_amount'] ?>, <?= $row['remaining_balance'] ?>, <?= $row['spent'] ?>)">Adjust</button>
                     <!-- <a href="?delete=<?= $row['id'] ?>" class="bg-red-500 text-white px-4 py-1 rounded-md" onclick="return confirmDelete()">Delete</a>-->
                  </td>
              </tr>
              <?php } ?>
              <!-- Totals Row -->
              <tr class="font-semibold text-gray-700 text-center bg-purple-100">
                  <td colspan="4" class="text-center py-2 px-4 border-r text-gray-800 border-gray-500">Total</td>
                  <td class="text-sm py-2 px-4 border-r text-blue-700 border-gray-500">₱<?= number_format($totalAllocated, 2) ?></td>
                  <td class="text-sm py-2 px-4 border-r text-red-700 border-gray-500">₱<?= number_format($totalSpent, 2) ?></td>
                  <td class="text-sm py-2 px-4 border-r text-green-700 border-gray-500">₱<?= number_format($totalRemaining, 2) ?></td>
                  <td class="text-center py-2 px-4"></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PAGINATION UI -->
        <div class="flex justify-between items-center p-2 mt-4 rounded">
          <div class="text-gray-800 font-semibold">
            Showing <?= $offset + 1 ?> - <?= min($offset + $records_per_page, $total_rows) ?> of <?= $total_rows ?>
          </div>
          <div class="flex gap-2">
            <?php if ($page > 1): ?>
              <a href="<?= $prevLink ?>" class="bg-purple-500 text-white border-2 px-4 py-2 rounded hover:bg-white hover:text-purple-700 hover:border-purple-700">Previous</a>
            <?php else: ?>
              <button class="bg-gray-300 text-white px-4 py-2 rounded cursor-not-allowed" disabled>Previous</button>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
              <a href="<?= $nextLink ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Next</a>
            <?php else: ?>
              <button class="bg-gray-300 text-white px-4 py-2 rounded cursor-not-allowed" disabled>Next</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
<div class="mt-6">
    <canvas id="pdf-viewer" width="600" height="400"></canvas>
</div>

<!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 flex justify-center items-center bg-gray-500 bg-opacity-75 z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-96 relative">
          <h3 class="text-xl font-bold mb-4">Add New Budget Allocation</h3>
          <form action="budget_allocation.php" method="POST">
          
              <div class="mb-4">
                <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                <select name="department" id="department" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                  <option value="" disabled selected>Select Department</option>
                  <option value="Administrative" <?php if ($department_filter == 'Administrative') echo 'selected'; ?>>Administrative</option>
                  <option value="Core-1" <?php if ($department_filter == 'Core-1') echo 'selected'; ?>>Core-1</option>
                  <option value="Core-2" <?php if ($department_filter == 'Core-2') echo 'selected'; ?>>Core-2</option>
                  <option value="Human Resource-1" <?php if ($department_filter == 'Human Resource-1') echo 'selected'; ?>>Human Resource-1</option>
                  <option value="Human Resource-2" <?php if ($department_filter == 'Human Resource-2') echo 'selected'; ?>>Human Resource-2</option>
                  <option value="Human Resource-3" <?php if ($department_filter == 'Human Resource-3') echo 'selected'; ?>>Human Resource-3</option>
                  <option value="Human Resource-4" <?php if ($department_filter == 'Human Resource-4') echo 'selected'; ?>>Human Resource-4</option>
                  <option value="Logistic-1" <?php if ($department_filter == 'Logistic-1') echo 'selected'; ?>>Logistic-1</option>
                  <option value="Logistic-2" <?php if ($department_filter == 'Logistic-2') echo 'selected'; ?>>Logistic-2</option>
                  <option value="Financial" <?php if ($department_filter == 'Financial') echo 'selected'; ?>>Financial</option>
                </select>
              </div>

              <div class="mb-4">
                <label for="category" class="block text-sm font-medium text-gray-700">Expense Category</label>
                <input type="text" name="category" id="category" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
              </div>


              <div class="mb-4">
                <label for="from_allocated" class="block text-sm font-medium text-gray-700">From</label>
                <input type="date" name="from_allocated" id="from_allocated" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
              </div>

              <div class="mb-4">
                <label for="to_allocated" class="block text-sm font-medium text-gray-700">To</label>
                <input type="date" name="to_allocated" id="to_allocated" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
              </div>

              <div class="mb-4">
                <label for="allocated_amount" class="block text-sm font-medium text-gray-700">Allocated Amount</label>
                <input type="number" name="allocated_amount" id="allocated_amount" class="w-full px-3 py-2 border border-gray-300 rounded-md">
              </div>

              <input type="hidden" name="spent" value="0">
              <input type="hidden" name="remaining_balance" id="remaining_balance">
              
              <div class="flex space-x-4 mt-4">
                <button type="submit" name="add" class="bg-blue-500 text-white px-4 py-2 rounded-md">Save Allocation</button>
                <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded-md" onclick="closeModal('addModal')">Close</button>
              </div>
          </form>
        </div>
    </div>


    <!-- Update Modal -->
    <div id="updateModal" class="fixed inset-0 flex justify-center items-center bg-gray-500 bg-opacity-75 z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-96">
          <h3 class="text-xl font-bold mb-4">Adjust Budget Allocation</h3>
          <form action="budget_allocation.php" method="POST">
            <input type="hidden" name="id" id="update_id">
          
            <div class="mb-4">
              <label for="update_department" class="block text-sm font-medium text-gray-700">Department</label>
              <select name="department" id="update_department" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                <option value="" disabled selected>Select Department</option>
                <option value="Administrative" <?php if ($department_filter == 'Administrative') echo 'selected'; ?>>Administrative</option>
                <option value="Core-1" <?php if ($department_filter == 'Core-1') echo 'selected'; ?>>Core-1</option>
                <option value="Core-2" <?php if ($department_filter == 'Core-2') echo 'selected'; ?>>Core-2</option>
                <option value="Human Resource-1" <?php if ($department_filter == 'Human Resource-1') echo 'selected'; ?>>Human Resource-1</option>
                <option value="Human Resource-2" <?php if ($department_filter == 'Human Resource-2') echo 'selected'; ?>>Human Resource-2</option>
                <option value="Human Resource-3" <?php if ($department_filter == 'Human Resource-3') echo 'selected'; ?>>Human Resource-3</option>
                <option value="Human Resource-4" <?php if ($department_filter == 'Human Resource-4') echo 'selected'; ?>>Human Resource-4</option>
                <option value="Logistic-1" <?php if ($department_filter == 'Logistic-1') echo 'selected'; ?>>Logistic-1</option>
                <option value="Logistic-2" <?php if ($department_filter == 'Logistic-2') echo 'selected'; ?>>Logistic-2</option>
                <option value="Financial" <?php if ($department_filter == 'Financial') echo 'selected'; ?>>Financial</option>
              </select>
            </div>

            <div class="mb-4">
              <label for="update_category" class="block text-sm font-medium text-gray-700">Expense Category</label>
              <input type="text" name="category" id="update_category" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>

            <div class="mb-4">
              <label for="update_from_allocated" class="block text-sm font-medium text-gray-700">From Allocated Date</label>
              <input type="date" name="from_allocated" id="update_from_allocated" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
              <label for="update_to_allocated" class="block text-sm font-medium text-gray-700">To Allocated Date</label>
              <input type="date" name="to_allocated" id="update_to_allocated" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
              <label for="update_allocated_amount" class="block text-sm font-medium text-gray-700">Allocated Amount</label>
              <input type="number" name="allocated_amount" id="update_allocated_amount" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
              <label for="update_spent" class="block text-sm font-medium text-gray-700">Spent</label>
              <input type="number" name="spent" id="update_spent" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>
            <div class="mb-4">
              <label for="update_remaining_balance" class="block text-sm font-medium text-gray-700">Remaining Balance</label>
              <input type="number" name="remaining_balance" id="update_remaining_balance" class="w-full px-3 py-2 border border-gray-300 rounded-md" readonly>
            </div>
            <div class="flex space-x-4 mt-4">
              <button type="submit" name="update" class="bg-blue-500 text-white px-4 py-2 rounded-md">Update Allocation</button>
              <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded-md" onclick="closeModal('updateModal')">Close</button>
            </div>
          </form>
        </div>
    </div>


    <script>
      // Function to open the modal
      function openModal(modalId) {
          document.getElementById(modalId).classList.remove('hidden');
      }

      // Function to close the modal
      function closeModal(modalId) {
          document.getElementById(modalId).classList.add('hidden');
      }

      // Automatically set the Remaining Balance when Allocated Amount changes in Add Modal
      document.getElementById('allocated_amount').addEventListener('input', function() {
          var allocatedAmount = parseFloat(this.value) || 0;
          document.getElementById('remaining_balance').value = allocatedAmount;
      });

      // Automatically set the Remaining Balance when Allocated Amount or Spent changes in Update Modal
      document.getElementById('update_allocated_amount').addEventListener('input', updateRemainingBalance);
      document.getElementById('update_spent').addEventListener('input', updateRemainingBalance);

      function updateRemainingBalance() {
          var allocatedAmount = parseFloat(document.getElementById('update_allocated_amount').value) || 0;
          var spent = parseFloat(document.getElementById('update_spent').value) || 0;
          document.getElementById('update_remaining_balance').value = allocatedAmount - spent;
      }

      // Function to pre-fill the Update Modal with the selected allocation details
      function editBudget(id, department, category, fromAllocated, toAllocated, allocatedAmount, remainingBalance, spent) {
        document.getElementById('update_id').value = id;
        document.getElementById('update_department').value = department;
        document.getElementById('update_category').value = category;
        document.getElementById('update_from_allocated').value = fromAllocated;
        document.getElementById('update_to_allocated').value = toAllocated;
        document.getElementById('update_allocated_amount').value = allocatedAmount;
        document.getElementById('update_spent').value = spent;
        document.getElementById('update_remaining_balance').value = remainingBalance;
        openModal('updateModal');
    }


      // Function to confirm deletion
      function confirmDelete() {
          return confirm('Are you sure you want to delete this allocation?');
      }
    </script>  
 </body>
</html>
