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
      $servername = "localhost";
      $usernameDB = "financial"; 
      $passwordDB = "UbrdRDvrHRAyHiA]"; 
      $dbname = "financial_db"; 

      $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

      // Check connection
      if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
      }

      // Handle approval, rejection, resend, and archive actions
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Approve logic
        if (isset($_POST['approve_id'])) {
          $approveId = $_POST['approve_id'];

          // Insert into the table
          $insert_sql = "INSERT INTO budget_request (id, reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number)
                  SELECT id, reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number 
                  FROM rejected_request WHERE id = ?";
          $stmt = $conn->prepare($insert_sql);
          $stmt->bind_param("i", $approveId);

          if ($stmt->execute()) {
            // After successful insertion, delete the row
            $delete_sql = "DELETE FROM rejected_request WHERE id = ?";
            $stmt_delete = $conn->prepare($delete_sql);
            $stmt_delete->bind_param("i", $approveId);

            if ($stmt_delete->execute()) {
              echo "<div class='bg-green-500 text-white p-4 rounded'>Request Approved and Moved!</div>";
            } else {
              echo "Error deleting record: " . $conn->error;
            }
          } else {
            echo "Error inserting record: " . $conn->error;
          }
        }

        // Reject logic
        if (isset($_POST['reject_id'])) {
          $rejectId = $_POST['reject_id'];
          $reason   = $_POST['reason'];

          // Insert into the table
          $insert_sql = "INSERT INTO rejected_request (id, reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due)
                  SELECT id, reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, payment_due
                  FROM br WHERE id = ?";
          $stmt = $conn->prepare($insert_sql);
          $stmt->bind_param("i", $rejectId);

          if ($stmt->execute()) {
            // Update rejection reason
            $update_sql = "UPDATE rejected_request SET rejected_reason = ? WHERE id = ?";
            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bind_param("si", $reason, $rejectId);

            if ($stmt_update->execute()) {
              echo "<div class='bg-red-500 text-white p-4 rounded'>Budget Rejected!</div>";
            } else {
              echo "Error updating rejection reason: " . $conn->error;
            }
          } else {
            echo "Error inserting into rejected_request: " . $conn->error;
          }
        }

        // Handle resent action (formerly “resend”)
        if (isset($_POST['action']) && $_POST['action'] === 'resend') {
          if (isset($_POST['resend_id'])) {
            $resendId = intval($_POST['resend_id']); // sanitize ID

            // Perform the "resent" action
            $insert_sql = "INSERT INTO br (id, reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, time_period, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number)
                      SELECT id, reference_id, account_name, requested_department, mode_of_payment, expense_categories, amount, description, document, time_period, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number 
                      FROM rejected_request WHERE id = ?";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("i", $resendId);

            if ($stmt->execute()) {
              // Delete from rejected_request
              $delete_sql = "DELETE FROM rejected_request WHERE id = ?";
              $stmt_delete = $conn->prepare($delete_sql);
              $stmt_delete->bind_param("i", $resendId);

              if ($stmt_delete->execute()) {
                echo "<div class='bg-blue-500 text-white p-4 rounded'>Request Resent Successfully!</div>";
              } else {
                echo "<div class='bg-red-500 text-white p-4 rounded'>Error deleting record after resent: {$conn->error}</div>";
              }
            } else {
              echo "<div class='bg-red-500 text-white p-4 rounded'>Error resending request: {$conn->error}</div>";
            }
          }
        }

        // Handle archive action (formerly “delete” action)
        if (isset($_POST['action']) && $_POST['action'] === 'archive') {
          if (isset($_POST['delete_id'])) {
            $deleteId = intval($_POST['delete_id']); // sanitize ID

            // 1) Insert into `archive` table from `rejected_request`
            $archive_sql = "
            INSERT INTO archive (
              id,
              reference_id,
              account_name,
              requested_department,
              mode_of_payment,
              expense_categories,
              amount,
              description,
              document,
              payment_due,
              bank_name,
              bank_account_name,
              bank_account_number,
              ecash_provider,
              ecash_account_name,
              ecash_account_number,
              rejected_reason,
              time_period
            )
            SELECT
              id,
              reference_id,
              account_name,
              requested_department,
              mode_of_payment,
              expense_categories,
              amount,
              description,
              document,
              payment_due,
              bank_name,
              bank_account_name,
              bank_account_number,
              ecash_provider,
              ecash_account_name,
              ecash_account_number,
              rejected_reason,
              time_period
            FROM rejected_request
            WHERE id = ?
        ";
            $stmt_archive = $conn->prepare($archive_sql);
            $stmt_archive->bind_param("i", $deleteId);

            if ($stmt_archive->execute()) {
              // 2) Delete from rejected_request
              $delete_sql = "DELETE FROM rejected_request WHERE id = ?";
              $stmt_delete = $conn->prepare($delete_sql);
              $stmt_delete->bind_param("i", $deleteId);

              if ($stmt_delete->execute()) {
                echo "<div class='bg-green-500 text-white p-4 rounded'>Request Archived Successfully!</div>";
              } else {
                echo "Error deleting record: " . $conn->error;
              }
            } else {
              echo "<div class='bg-red-500 text-white p-4 rounded'>Error archiving record: " . $conn->error . "</div>";
            }
          }
        }
      }

      // ==============
      // TIME PERIOD + SEARCH
      // ==============
      $timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : 'all';
      $searchID   = isset($_GET['search_id'])   ? $_GET['search_id']   : '';

      $sql = "SELECT * FROM rejected_request WHERE 1=1";

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
      $result = $conn->query($sql);
    ?>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <title>Rejected Request</title>
</head>

<body class="bg-white overflow-hidden">
  <?php include('sidebar.php'); ?>

  <div class="overflow-y-auto h-full px-6">
  <!-- Breadcrumb -->
   <div class="bg-white p-4">
    <nav class="text-[#4311A5] font-poppins text-2xl ">
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
          <a class="text-black hover:text-[#4311A5]" href="#">Rejected Request</a>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Main content area -->
  <div class="flex-1  p-6 w-full">
      <h1 class="font-bold font-[Poppins] text-[#0e1847] text-3xl mb-6 text-center">Rejected Requests</h1>
      <div class="w-full">
                  <div class="flex justify-between items-center flex-wrap gap-4">
                  <!-- Filter Form (Time Period + Search by Reference ID) -->
                  <form method="GET" action="" class="flex items-center gap-4 flex-wrap">
                    <div class="flex gap-2 font-poppins text-m font-medium border-b border-gray-300">
                        <?php
                        $tabs = ['all', 'weekly', 'monthly', 'quarterly', 'yearly'];
                        foreach ($tabs as $tab) {
                            $isActive = ($timePeriod === $tab);
                            echo '<button type="submit" name="time_period" value="' . $tab . '" class="px-4 py-2 rounded-t-full transition-all ' .
                            ($isActive
                                ? 'border-b-4 border-purple-600 text-purple-600 font-semibold'
                                : 'text-gray-900 hover:text-purple-900 hover:border-b-2 hover:border-purple-900') .
                            '">' . strtoupper($tab) . '</button>';
                        }
                        ?>
                    </div>

                    <!-- Reference ID Search -->
                    <div class="flex items-center gap-2">
                        <input type="text" name="search_id" id="search_id"
                        value="<?= htmlspecialchars($searchID) ?>"
                        placeholder="Search Request"
                        class="border px-3 py-2 rounded-full text-m font-medium text-black font-poppins focus:outline-none focus:ring-2 focus:ring-purple-500"/>
                    </div>
                  </form>
                  
                  <div class="flex items-center gap-3 float-right">
                    <a href="rejected_request.php?page=rejectrequest">
                      <button class="bg-red-500 text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:text-white px-2 py-1 rounded text-lg cursor-pointer whitespace-nowrap mb-4 float-right ">Rejects</button>
                    </a>
                    <a href="archive.php?page=archive">
                      <button class="bg-purple-500 text-white hover:bg-[linear-gradient(to_right,_#9A66FF,_#6532C9,_#4311A5)] hover:text-white px-2 py-1 rounded text-lg cursor-pointer whitespace-nowrap mb-4 float-right ">Archives</button>
                    </a>
                  </div>
                </div>

        <div class="">
            <table class="min-w-full bg-white">
              <thead>
                <tr class="sticky top-0 px-2 py-2 t text-blue-900">
                    <th>ID</th>
                    <th>Reference ID</th>
                    <th>Account Name</th>
                    <th>Department</th>
                    <th>Payment</th>
                    <th>Expense Category</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Document</th>
                    <th>Time Period</th>
                    <th>Payment Due</th>
                    <th>Reject Reason</th>
                    <th>Actions</th>
                </tr>
              </thead>
              <tbody class="text-gray-900 text-sm font-light">
                <?php
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    echo "<tr class='hover:bg-gray-200' text-left py-3 px-3>";
                    echo "<td class='py-3 px-3 text-left '>{$row['id']}</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['reference_id']}</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['account_name']}</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['requested_department']}</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['mode_of_payment']}</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['expense_categories']}</td>";
                    echo "<td class='py-3 px-3 text-left '>" . number_format($row['amount'], 2) . "</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['description']}</td>";

                    // Document link
                    if (!empty($row['document']) && file_exists("files/" . $row['document'])) {
                      echo "<td class='py-3 px-3 text-left '>
                          <a href='download.php?file=" . urlencode($row['document']) . "' style='color: blue; text-decoration: underline;'>
                            Download
                          </a>
                        </td>";
                    } else {
                      echo "<td class='py-3 px-3 text-left '>No document available</td>";
                    }

                    echo "<td class='py-3 px-3 text-left '>{$row['time_period']}</td>";
                    echo "<td class='py-3 px-3 text-left '>{$row['payment_due']}</td>";

                    // Rejection Reason
                    echo "<td class=' text-center'>
                      <button onclick=\"showModal('{$row['rejected_reason']}')\" class='text-blue-500 underline'>
                        View Reason
                      </button>
                    </td>";

                    // Action buttons with confirmations in one line with increased spacing
                    echo "<td class='pt-2 px-6 text-left '>";
                    echo "<div class='flex space-x-10'>";

                    // Resent Button => Confirmation
                    echo "<form method='POST' action='rejected_request.php' id='resentForm{$row['id']}' style='display:inline-block;' onsubmit='return confirmResent({$row['id']})'>
                              <input type='hidden' name='action' value='resend'>
                              <input type='hidden' name='resend_id' value='{$row['id']}'>
                              <button type='submit' class='text-blue-500 w-8 h-4 flex justify-center items-center'>
                                  <i class='fas fa-paper-plane mr-1'></i>Resent
                              </button>
                            </form>";

                    // Archive Button => Confirmation
                    echo "<form method='POST' action='rejected_request.php' id='archiveForm{$row['id']}' style='display:inline-block;' onsubmit='return confirmArchive({$row['id']})'>
                              <input type='hidden' name='action' value='archive'>
                              <input type='hidden' name='delete_id' value='{$row['id']}'>
                              <button type='submit' class='text-red-500 w-8 h-4 flex justify-center items-center'>
                                  <i class='fas fa-archive mr-1'></i>Archive
                              </button>
                            </form>";

                    echo "</div>";
                    echo "</td>";


                    echo "</tr>";
                  }
                } else {
                  echo "<tr><td colspan='13' class='text-center py-3'>No records found</td></tr>";
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
      <!-- Confirm Resent Modal (just like Approve confirmation) -->
      <div id="confirmResentModal"
        class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-80 text-center relative">
          <h2 class="text-lg font-bold text-gray-800 mb-4">Confirm Resent</h2>
          <p class="text-sm text-gray-600 mb-4">Are you sure you want to resend this request?</p>
          <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeResentModal()" class="bg-gray-300 text-gray-800 hover:bg-gray-400 px-4 py-2 rounded-lg">
              Cancel
            </button>
            <button type="button" onclick="submitResentForm()" class="bg-blue-500 text-white hover:bg-blue-600 px-4 py-2 rounded-lg">
              Confirm
            </button>
          </div>
        </div>
      </div>

      <!-- Confirm Archive Modal -->
      <div id="confirmArchiveModal"
        class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-80 text-center relative">
          <h2 class="text-lg font-bold text-gray-800 mb-4">Confirm Archive</h2>
          <p class="text-sm text-gray-600 mb-4">Are you sure you want to archive this request?</p>
          <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeArchiveModal()" class="bg-gray-300 text-gray-800 hover:bg-gray-400 px-4 py-2 rounded-lg">
              Cancel
            </button>
            <button type="button" onclick="submitArchiveForm()" class="bg-red-500 text-white hover:bg-red-600 px-4 py-2 rounded-lg">
              Confirm
            </button>
          </div>
        </div>
      </div>


      <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('hidden');
    }

    function toggleDropdown(id) {
      const dropdown = document.getElementById(id);
      const icon = dropdown.previousElementSibling.querySelector('.fas.fa-chevron-right');
      dropdown.classList.toggle('hidden');
      icon.classList.toggle('rotate-90');
    }

    function openModal() {
      const modal = document.getElementById('addEmployeeModal');
      modal.classList.remove('hidden');
    }

    // Keep original closeModal name but handle reason modal, etc.
    function closeModal() {
      const modal = document.getElementById('addEmployeeModal');
      modal.classList.add('hidden');
    }

    window.onclick = function(event) {
      const modal = document.getElementById('addEmployeeModal');
      if (event.target === modal) {
        closeModal();
      }
    };

    function showModal(reason) {
      console.log('Rejection Reason:', reason); // Debug
      document.getElementById("reasonText").innerText = reason;
      document.getElementById("reasonModal").classList.remove("hidden");
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.add("hidden");
    }

    // Show the modal when clicking "Archive" or "Resent"
    function showPasswordModal(action, id) {
      document.getElementById('action').value = action;
      document.getElementById('delete_id').value = id;
      document.getElementById('resend_id').value = id;
      document.getElementById('passwordModal').classList.remove('hidden');
    }

    function closePasswordModal() {
      document.getElementById('passwordModal').classList.add('hidden');
    }

    function openModal(modalId, reasonText = '') {
      const modal = document.getElementById(modalId);
      if (reasonText) {
        document.getElementById('reasonText').innerText = reasonText;
      }
      modal.classList.remove('hidden');
    }
  </script>
      <script>
        // ================
        // Confirmation for RESENT
        // ================
        let resentFormToSubmit;

        function confirmResent(resentId) {
          console.log('Opening modal for RESENT ID:', resentId);
          document.getElementById('confirmResentModal').style.display = 'flex';
          // We'll store the form in a global variable using the ID we set
          resentFormToSubmit = document.getElementById('resentForm' + resentId);
          return false; // Prevent immediate submission
        }

        function submitResentForm() {
          console.log('Submitting resent form');
          if (resentFormToSubmit) {
            resentFormToSubmit.submit();
          }
        }

        function closeResentModal() {
          console.log('Cancel Resent');
          document.getElementById('confirmResentModal').style.display = 'none';
        }

        // ================
        // Confirmation for ARCHIVE
        // ================
        let archiveFormToSubmit;

        function confirmArchive(archiveId) {
          console.log('Opening modal for ARCHIVE ID:', archiveId);
          document.getElementById('confirmArchiveModal').style.display = 'flex';
          archiveFormToSubmit = document.getElementById('archiveForm' + archiveId);
          return false;
        }

        function submitArchiveForm() {
          console.log('Submitting archive form');
          if (archiveFormToSubmit) {
            archiveFormToSubmit.submit();
          }
        }

        function closeArchiveModal() {
          console.log('Cancel Archive');
          document.getElementById('confirmArchiveModal').style.display = 'none';
        }
      </script>

  <!-- Reason Modal -->
  <div id="reasonModal" class="modal-overlay hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex justify-center items-center">
    <div class="modal-content bg-white p-6 rounded shadow-lg">
      <h2 class="text-lg font-bold mb-4">Reason for Rejection</h2>
      <p id="reasonText" class="mb-4 text-gray-700"></p>
      <button onclick="closeModal('reasonModal')" class="bg-blue-500 text-white px-4 py-2 rounded">Close</button>
    </div>
  </div>

  <!-- Password Modal (kept, but not used) -->
  <div id="passwordModal" class="modal-overlay hidden">
    <div class="modal-content">
      <form method="POST" action="" id="passwordForm">
        <input type="hidden" id="action" name="action">
        <input type="hidden" id="delete_id" name="delete_id">
        <input type="hidden" id="resend_id" name="resend_id">

        <label for="password">Enter Admin Password:</label>
        <input type="password" id="password" name="password">

        <div class="modal-actions">
          <button type="button" onclick="closePasswordModal()" class="btn">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>
    </div>
  </div>

</body>

</html>