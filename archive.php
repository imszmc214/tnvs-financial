<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  // Redirect to login page if not logged in
  header("Location: login.php");
  exit();
}
?>

<html>

<head>
  <?php
      // =============================
      // Database and POST Handling
      // =============================
      $servername = 'localhost';
      $usernameDB = 'financial';
      $passwordDB = 'UbrdRDvrHRAyHiA]';
      $dbname = 'financial_db';

      $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
      if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
      }

      // Handle approval action
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
        $approveId = intval($_POST['approve_id']);
        // Start transaction
        $conn->begin_transaction();

        try {
          // Insert into pa table
          $insert_sql = "INSERT INTO pa (account_name, requested_department, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, reference_id, mode_of_payment)
                             SELECT account_name, requested_department, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number,
                                    CONCAT('PA-', SUBSTRING(reference_id, 4)) AS reference_id, mode_of_payment
                             FROM budget_request WHERE id = ?";
          $stmt_insert = $conn->prepare($insert_sql);
          $stmt_insert->bind_param("i", $approveId);

          if ($stmt_insert->execute()) {
            // Now delete from budget_request table
            $delete_sql = "DELETE FROM budget_request WHERE id = ?";
            $stmt_delete = $conn->prepare($delete_sql);
            $stmt_delete->bind_param("i", $approveId);

            if ($stmt_delete->execute()) {
              // Commit transaction if both queries succeed
              $conn->commit();
              echo "
                          <div id='success-message' class='bg-green-500 text-white p-4 rounded'>
                              Budget Approved and moved to Payout!
                          </div>
                          <script>
                              setTimeout(function() {
                                  document.getElementById('success-message').style.display = 'none';
                              }, 2000);
                          </script>
                      ";
            } else {
              throw new Exception("Error deleting record from budget_request: " . $stmt_delete->error);
            }
          } else {
            throw new Exception("Error inserting record into pa: " . $stmt_insert->error);
          }
        } catch (Exception $e) {
          $conn->rollback();
          echo "<div class='bg-red-500 text-white p-4 rounded'>Transaction failed: " . $e->getMessage() . "</div>";
        }
      }

      // Handle rejection action
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id']) && isset($_POST['reason'])) {
        $rejectId = intval($_POST['reject_id']);
        $reason   = $conn->real_escape_string($_POST['reason']);

        // Start a transaction
        $conn->begin_transaction();

        try {
          // Insert into rejected_request table
          $insert_sql = "INSERT INTO rejected_request (reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, rejected_reason)
                             SELECT reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, ?
                             FROM budget_request WHERE id = ?";
          $stmt_insert = $conn->prepare($insert_sql);
          $stmt_insert->bind_param("si", $reason, $rejectId);

          if ($stmt_insert->execute()) {
            // Now delete from budget_request table
            $delete_sql = "DELETE FROM budget_request WHERE id = ?";
            $stmt_delete = $conn->prepare($delete_sql);
            $stmt_delete->bind_param("i", $rejectId);

            if ($stmt_delete->execute()) {
              // Commit transaction if both queries succeed
              $conn->commit();
              echo "
                          <div id='success-message' class='bg-green-500 text-white p-4 rounded'>
                              Budget Rejected and moved to Rejected Requests!
                          </div>
                          <script>
                              setTimeout(function() {
                                  document.getElementById('success-message').style.display = 'none';
                              }, 2000);
                          </script>
                      ";
            } else {
              throw new Exception("Error deleting record from budget_request: " . $stmt_delete->error);
            }
          } else {
            throw new Exception("Error inserting record into rejected_request: " . $stmt_insert->error);
          }
        } catch (Exception $e) {
          $conn->rollback();
          echo "<div class='bg-red-500 text-white p-4 rounded'>Transaction failed: " . $e->getMessage() . "</div>";
        }
      }

      // Handle delete action
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $deleteId = intval($_POST['delete_id']);

        $delete_sql = "DELETE FROM budget_request WHERE id = ?";
        $stmt_delete = $conn->prepare($delete_sql);
        $stmt_delete->bind_param("i", $deleteId);

        if ($stmt_delete->execute()) {
          echo "<div id='success-message' class='bg-green-500 text-white p-4 rounded'>
                      Record deleted successfully!
                    </div>
                    <script>
                        setTimeout(function() {
                            document.getElementById('success-message').style.display = 'none';
                        }, 2000);
                    </script>";
        } else {
          echo "<div class='bg-red-500 text-white p-4 rounded'>Error deleting record: " . $stmt_delete->error . "</div>";
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

      // =========================
      // Existing Time Period Filter
      // =========================
      $timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : 'all';

      // =========================
      // ADDED FOR REFERENCE ID SEARCH
      // =========================
      $searchID   = isset($_GET['search_id']) ? $_GET['search_id'] : '';

      // Build base query
      $sql = "SELECT * FROM archive WHERE 1=1";

      // Filter by time_period if not 'all'
      if ($timePeriod !== 'all') {
        $sql .= " AND time_period = '$timePeriod'";
      }

       // Filter by Reference ID if search box is not empty
      if (!empty($searchID)) {
        $safeSearch = $conn->real_escape_string($searchID);
        $sql .= " AND id LIKE '%$safeSearch%'
                  OR reference_id LIKE '%$safeSearch%'
                  OR account_name LIKE '%$safeSearch%'
                  OR requested_department LIKE '%$safeSearch%'
                  OR mode_of_payment LIKE '%$safeSearch%'
                  OR expense_categories LIKE '%$safeSearch%'
                  OR time_period LIKE '%$safeSearch%'
                  OR amount LIKE '%$safeSearch%'
                  OR description LIKE '%$safeSearch%'";
      }

      // Finally run the query
      $result = $conn->query($sql);
      ?>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <title>Archive</title>
</head>

<body class="bg-white overflow-hidden">

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
          <a class="text-black hover:text-[#4311A5]" href="#">Archived</a>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Main content area -->
  <div class="flex-1 p-6 w-full">

    <div class="w-full">
      <h1 class="font-bold font-[Poppins] text-[#0e1847] text-3xl mb-6 text-center">Archived Requests</h1>
      <div class="w-full">

      <!-- Filter Form (Time Period + Search by Reference ID) -->
       <div class="flex items-center justify-between w-full">
              <form method="GET" action="archive.php" class="flex flex-wrap items-center justify-between gap-4 mb-4">
              <!-- Time Filter Tabs -->
              <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300">
                  <?php
                  $tabs = ['all', 'weekly', 'monthly', 'quarterly', 'yearly'];
                  foreach ($tabs as $tab) {
                      $isActive = ($timePeriod === $tab);
                      echo '<button type="submit" name="time_period" value="' . $tab . '" class="px-4 py-2 rounded-t-full transition-all ' .
                      ($isActive
                          ? 'border-b-4 border-yellow-400 text-yellow-600 font-semibold'
                          : 'text-gray-900 hover:text-yellow-500 hover:border-b-2 hover:border-yellow-300') .
                      '">' . strtoupper($tab) . '</button>';
                  }
                  ?>
              </div>

              <!-- Reference ID Search -->
              <div class="flex items-center gap-2">
                  <input type="text" name="search_id" id="search_id"
                  value="<?= htmlspecialchars($searchID) ?>"
                  placeholder="Search Request"
                  class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-yellow-400"/>
              </div>
              </form>
        <div class="flex items-center gap-3">
          <a href="rejected_request.php?page=rejectrequest">
            <button class="bg-red-500 text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:text-white px-2 py-1 rounded text-lg cursor-pointer whitespace-nowrap mb-4 float-right shadow-lg">Rejects</button>
          </a>
          <a href="archive.php?page=archive">
            <button class="bg-purple-500 text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:text-white px-2 py-1 rounded text-lg cursor-pointer whitespace-nowrap mb-4 float-right shadow-lg">Archives</button>
          </a>
        </div>
      </div>

      <!-- Table of Records -->
      <div class="">
        <table class="min-w-full bg-white">
          <thead>
            <tr class="sticky top-0 px-2 py-2 t text-blue-900">
              <th>ID</th>
              <th>Reference ID</th>
              <th>Account Name</th>
              <th>Department</th>
              <th>Payment Mode</th>
              <th>Expense Category</th>
              <th>Amount</th>
              <th>Description</th>
              <th>Document</th>
              <th>Time Period</th>
              <th>Payment Due</th>
            </tr>
          </thead>

          <tbody class="text-sm font-light">
            <?php
            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo "<tr class=' hover:bg-purple-100'>";
                echo "<td class='py-3 px-6 text-center'>{$row['id']}</td>";
                echo "<td class='py-3 px-6 text-center'>{$row['reference_id']}</td>";
                echo "<td class='py-3 px-6 text-center'>{$row['account_name']}</td>";
                echo "<td class='py-3 px-6 text-center0'>{$row['requested_department']}</td>";
                echo "<td class='py-3 px-6 text-center'>{$row['mode_of_payment']}</td>";
                echo "<td class='py-3 px-6 text-center'>{$row['expense_categories']}</td>";
                echo "<td class='py-3 px-6 text-center text-right'>" . number_format($row['amount'], 2) . "</td>";
                echo "<td class='py-3 px-6 text-center'>{$row['description']}</td>";

                // Document download link
                if (!empty($row['document']) && file_exists("files/" . $row['document'])) {
                  echo "<td class='border border-gray-300 text-center'>
                    <a href='download.php?file=" . urlencode($row['document']) . "' 
                       style='color: blue; text-decoration: underline;'>
                      Download
                    </a>
                  </td>";
                } else {
                  echo "<td class='py-3 px-6 text-center''>No document available</td>";
                }

                echo "<td class='py-2 px-6 text-center'>{$row['time_period']}</td>";
                echo "<td class='py-2 px-6 text-center'>{$row['payment_due']}</td>";
                echo "</tr>"; // optional: close the TR here
              } // <-- CLOSES THE while LOOP
            } else {
              // Now we can safely do the else
              echo "<tr><td colspan='12' class='py-3 px-6 text-center border-r border-gray-300'>No records found</td></tr>";
            }
            $conn->close();
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-6">
    <canvas id="pdf-viewer" width="600" height="400"></canvas>
  </div>

  <!-- Modal for Reject Reason -->
  <div id="rejectModal"
    class="modal fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50"
    tabindex="-1"
    role="dialog"
    style="display: none;">
    <div class="bg-blue-900 text-white p-6 rounded-lg shadow-lg w-80">
      <div class="flex justify-between items-center">
        <h2 class="text-lg font-bold">Reason for Rejection</h2>
        <button type="button" aria-label="Close" onclick="closeModal()" class="text-white font-bold">&times;</button>
      </div>
      <form id="rejectForm" method="POST" action="budget_request.php" class="mt-4">
        <input type="hidden" name="reject_id" id="reject_id">
        <div>
          <label for="reason" class="text-sm">Reason:</label>
          <textarea name="reason" id="reason" rows="4"
            class="w-full p-2 mt-2 bg-white text-black rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            required></textarea>
        </div>
        <button type="submit"
          class="bg-blue-600 hover:bg-blue-700 mt-4 w-full text-white font-bold py-2 px-4 rounded">
          Submit
        </button>
      </form>
    </div>
  </div>

  <!-- Custom Confirmation Modal -->
  <div id="confirmationModal"
    class="modal fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50"
    tabindex="-1"
    role="dialog"
    style="display: none;">
    <div class="bg-white p-6 rounded-lg shadow-lg w-80">
      <div class="text-center">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Confirm Rejection</h2>
        <p class="text-gray-600 mb-4">Are you sure you want to reject this request?</p>
        <div class="flex justify-center space-x-4">
          <button id="confirmRejectBtn"
            class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
            Yes, Reject
          </button>
          <button onclick="closeConfirmationModal()"
            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript to manage modals and confirmation -->
  <script>
    let formToSubmit; // Global variable to store form reference for confirmation

    // Function to open the rejection modal
    function openModal(rejectId) {
      document.getElementById("reject_id").value = rejectId;
      document.getElementById("rejectModal").style.display = "flex";
    }

    // Function to close the rejection modal
    function closeModal() {
      document.getElementById("rejectModal").style.display = "none";
    }

    // Function to open the confirmation modal
    function openConfirmationModal() {
      document.getElementById("confirmationModal").style.display = "flex";
    }

    // Function to close the confirmation modal
    function closeConfirmationModal() {
      document.getElementById("confirmationModal").style.display = "none";
    }

    // Add confirmation step to the rejection form submission
    document.getElementById('rejectForm').addEventListener('submit', function(event) {
      event.preventDefault(); // Prevent form from submitting immediately

      const reason = document.getElementById('reason').value.trim();
      if (reason === '') {
        alert('Please enter a reason for rejection.');
        return;
      }

      formToSubmit = this; // Store the form in a variable for later submission
      openConfirmationModal(); // Open the custom confirmation modal
    });

    // Confirm rejection when 'Yes, Reject' is clicked
    document.getElementById('confirmRejectBtn').addEventListener('click', function() {
      formToSubmit.submit(); // Submit the stored form
    });

    // Additional JS for Approvals: already included above in the echo block
  </script>

</body>

</html>