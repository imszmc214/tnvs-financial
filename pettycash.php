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
            $servername = "localhost";
            $usernameDB = "financial"; 
            $passwordDB = "UbrdRDvrHRAyHiA]"; 
            $dbname = "financial_db"; 

            $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            // Handle approval action
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id']) && isset($_POST['official_amount'])) {
                $approveId = intval($_POST['approve_id']);
                $officialAmount = floatval($_POST['official_amount']);
                // Start transaction
                $conn->begin_transaction();

                try {
                    // Fetch the requested department before deleting the record
                    $fetch_sql = "SELECT requested_department, expense_categories, mode_of_payment FROM pettycash WHERE id = ?";
                    $stmt_fetch = $conn->prepare($fetch_sql);
                    $stmt_fetch->bind_param("i", $approveId);
                    $stmt_fetch->execute();
                    $stmt_fetch->bind_result($requested_department, $expense_category, $mode_of_payment);
                    $stmt_fetch->fetch();
                    $stmt_fetch->close();

                    // Update the amount in the pettycash table
                    $update_sql = "UPDATE pettycash SET amount = ? WHERE id = ?";
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
                        $insert_sql = "INSERT INTO pa (account_name, requested_department, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number, reference_id, mode_of_payment)
                               SELECT account_name, requested_department, expense_categories, amount, description, document, payment_due, bank_name, bank_account_name, bank_account_number, ecash_provider, ecash_account_name, ecash_account_number,
                                      CONCAT('PA-', SUBSTRING(reference_id, 4)) AS reference_id, mode_of_payment
                               FROM pettycash WHERE id = ?";
                        $stmt_insert = $conn->prepare($insert_sql);
                        $stmt_insert->bind_param("i", $approveId);

                        if ($stmt_insert->execute()) {
                            // Now delete from pettycash table
                            $delete_sql = "DELETE FROM pettycash WHERE id = ?";
                            $stmt_delete = $conn->prepare($delete_sql);
                            $stmt_delete->bind_param("i", $approveId);

                            if ($stmt_delete->execute()) {
                                // Update the allocated_amount in the budget_allocations table
                                $update_allocated_sql = "UPDATE budget_allocations SET allocated_amount = allocated_amount + ? WHERE department = ?";
                                $stmt_update_allocated = $conn->prepare($update_allocated_sql);
                                $stmt_update_allocated->bind_param("ds", $officialAmount, $requested_department);
                                $stmt_update_allocated->execute();

                                // Commit transaction if all queries succeed
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
                                throw new Exception("Error deleting record from pettycash: " . $stmt_delete->error);
                            }
                        } else {
                            throw new Exception("Error inserting record into pa: " . $stmt_insert->error);
                        }
                    } else {
                        throw new Exception("Error updating amount in pettycash: " . $stmt_update->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    echo "<div class='bg-red-500 text-white p-4 rounded'>Transaction failed: " . $e->getMessage() . "</div>";
                }
            }

            // Handle rejection action
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
                $rejectId = intval($_POST['reject_id']);

                // Start a transaction
                $conn->begin_transaction();

                try {
                    // Delete the record from the pettycash table
                    $delete_sql = "DELETE FROM pettycash WHERE id = ?";
                    $stmt_delete = $conn->prepare($delete_sql);
                    $stmt_delete->bind_param("i", $rejectId);

                    if ($stmt_delete->execute()) {
                        // Commit the transaction
                        $conn->commit();
                        echo "
                            <div id='success-message' class='bg-green-500 text-white p-4 rounded'>
                                Budget Rejected and removed from the list!
                            </div>
                            <script>
                                setTimeout(function() {
                                    document.getElementById('success-message').style.display = 'none';
                                }, 2000);
                            </script>
                        ";
                    } else {
                        throw new Exception("Error deleting record: " . $stmt_delete->error);
                    }
                } catch (Exception $e) {
                    // Rollback the transaction in case of an error
                    $conn->rollback();
                    echo "<div class='bg-red-500 text-white p-4 rounded'>Transaction failed: " . $e->getMessage() . "</div>";
                }
            }

            // Handle delete action
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
                $deleteId = intval($_POST['delete_id']);

                $delete_sql = "DELETE FROM pettycash WHERE id = ?";
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
            $servername = "localhost";
            $usernameDB = "financial"; 
            $passwordDB = "UbrdRDvrHRAyHiA]"; 
            $dbname = "financial_db"; 

            $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

            // =========================
            // Existing Time Period Filter
            // =========================
            $timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : 'all';

            // =========================
            // ADDED FOR REFERENCE ID SEARCH
            // =========================
            $searchID   = isset($_GET['search_id']) ? $_GET['search_id'] : '';

            // Build base query without the ORDER BY clause
            $sql = "SELECT * FROM pettycash WHERE 1=1";

            // Filter by time_period if not 'all'
            if ($timePeriod !== 'all') {
                $sql .= " AND time_period = '$timePeriod'";
            }

            // Filter by Reference ID if search box is not empty
            if (!empty($searchID)) {
                $safeSearch = $conn->real_escape_string($searchID);
                $sql .= " AND reference_id LIKE '%$safeSearch%'";
            }

            // Now add the ORDER BY clause
            $sql .= " ORDER BY id DESC";

            // Pagination settings
            $records_per_page = 10;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) {
                $page = 1;
            }
            $offset = ($page - 1) * $records_per_page;

            // Modify the query to include LIMIT and OFFSET
            $sql .= " LIMIT $offset, $records_per_page";

            // 1) Count total records for pagination
            $count_sql = "SELECT COUNT(*) as total FROM pettycash WHERE 1=1";
            if ($timePeriod !== 'all') {
                $count_sql .= " AND time_period = '$timePeriod'";
            }
            if (!empty($searchID)) {
                $safeSearch = $conn->real_escape_string($searchID);
                $count_sql .= " AND reference_id LIKE '%$safeSearch%'";
            }
            $count_result = $conn->query($count_sql);
            $row_count = $count_result->fetch_assoc();
            $total_rows = $row_count['total'];
            $total_pages = ($total_rows > 0) ? ceil($total_rows / $records_per_page) : 1;

            // Execute the query
            $result = $conn->query($sql);

            ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>Petty Cash Allowance</title>
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
            <a class="text-black hover:text-[#4311A5]" href="pettycash.php">Petty Cash Allowance</a>
          </li>
        </ol>
      </nav>
    </div>

    <!-- Main content area -->
    <div class="flex-1 p-6 w-full">
        <h1 class="font-bold font-[Poppins] text-[#0e1847] text-3xl mb-6 text-center">Petty Cash Allowance</h1>
        <div class="w-full">

            <!-- Filter Form (Time Period + Search by Reference ID) -->
            <div class="flex items-center justify-between w-full">
            <form method="GET" action="pettycash.php" class="flex flex-wrap items-center justify-between gap-4 mb-4">
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

            </div>

            <!-- Table of Records -->
            <!-- Add border-collapse: collapse plus border for each cell -->
            <div class="">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="py-2 px-4 text-center text-m font-bold text-blue-900">
                            <th>ID</th>
                            <th>Reference ID</th>
                            <th>Account Name</th>
                            <th>Department</th>
                            <th>Payment</th>
                            <th>Expense Category</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Document</th>
                            <th>Payment Due</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm font-poppins">

                        <?php

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr class=' hover:bg-purple-100'>";
                                echo "<td class='py-2 px-4 text-center'>{$row['id']}</td>";
                                echo "<td class='py-2 px-4 text-center'>{$row['reference_id']}</td>";
                                echo "<td class='py-2 px-4 text-center'>{$row['account_name']}</td>";
                                echo "<td class='py-2 px-4 text-center'>{$row['requested_department']}</td>";
                                echo "<td class='py-2 px-4 text-center'>{$row['mode_of_payment']}</td>";
                                echo "<td class='py-2 px-4 text-center'>{$row['expense_categories']}</td>";
                                echo "<td class='py-2 px-4 text-center'>" . number_format($row['amount'], 2) . "</td>";
                                echo "<td class='py-2 px-4 text-center'>{$row['description']}</td>";

                                // Document download link
                                if (!empty($row['document']) && file_exists("files/" . $row['document'])) {
                                    echo "<td class=' text-center'>
                              <a href='download.php?file=" . urlencode($row['document']) . "' 
                                style='color: blue; text-decoration: underline;'>
                                Download
                              </a>
                            </td>";
                                } else {
                                    echo "<td class='px-2 text-center'>No document available</td>";
                                }

                                echo "<td class='py-1 px-4 text-left'>{$row['payment_due']}</td>";

                                // Action buttons with confirmation modal
                                echo "
                  <td class='pt-2 px-4 text-left'>
                      <div class='flex justify-start items-center space-x-1'>
                          <form method='POST' action='' id='approvalForm{$row['id']}' onsubmit='return confirmApproval({$row['id']}, {$row['amount']})'>
                              <input type='hidden' name='approve_id' id='approve_id_{$row['id']}' value=''>
                              <button type='submit' class='bg-green-500 text-white hover:text-white  w-16 h-6 text-sm rounded-lg'>
                                  Approve
                              </button>
                          </form>

                          <button type='button' 
                                  class='bg-red-500 mb-3 text-white w-16 h-6 text-sm flex justify-center rounded-lg items-center' 
                                  onclick='handleReject({$row['id']})'>
                              Reject
                          </button>
                      </div>
                  </td>
                  ";
                            }

                            // End while
                            echo "
            <div id='confirmModal' class='fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 hidden z-50'>
                <div class='bg-white p-6 rounded-lg shadow-lg w-80 relative'>
                    <h2 class='text-lg font-bold text-gray-800 mb-4 text-center'>Enter Amount</h2>
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

            <script>
              // Global variable to store the currently selected approval form's ID
              var currentApprovalFormId = null;

              function confirmApproval(approveId, requestedAmount) {
                console.log('Opening modal for approval ID:', approveId);
                // Store the current record's ID
                currentApprovalFormId = approveId;
                // Display the requested amount
                document.getElementById('requestedAmount').innerText = requestedAmount;
                // Display the confirmation modal
                document.getElementById('confirmModal').style.display = 'flex';
                // Set the hidden input for the specific approval form
                var approveInput = document.getElementById('approve_id_' + approveId);
                if (approveInput) {
                    approveInput.value = approveId;
                }
                return false; // Prevent form from submitting immediately
              }

              function submitApprovalForm() {
                console.log('Submitting approval form for ID:', currentApprovalFormId);
                
                if (currentApprovalFormId !== null) {
                  // Get the official amount
                  var officialAmount = document.getElementById('officialAmount').value;
                  // Set the official amount in the form
                  var officialAmountInput = document.createElement('input');
                  officialAmountInput.type = 'hidden';
                  officialAmountInput.name = 'official_amount';
                  officialAmountInput.value = officialAmount;
                  document.getElementById('approvalForm' + currentApprovalFormId).appendChild(officialAmountInput);
                  // Submit the form corresponding to the stored ID
                  document.getElementById('approvalForm' + currentApprovalFormId).submit();
                }
              }

              function closeConfirmationModal() {
                console.log('Closing confirmation modal');
                document.getElementById('confirmModal').style.display = 'none';
              }

              // Close modal when clicking outside the modal content
              window.addEventListener('click', function(event) {
                var modal = document.getElementById('confirmModal');
                if (event.target === modal) {
                  closeConfirmationModal();
                }
              });
            </script>
            ";
                        } else {
                            echo "<tr><td colspan='12' class='text-center'>No records found</td></tr>";
                        }

                        $conn->close();

                        ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION UI (Previous/Next) -->
            <div class="flex justify-between items-center p-2 mt-4 rounded border-t border-gray-300">
                <div class="text-gray-800 font-semibold">
                    Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $records_per_page, $total_rows); ?> of <?php echo $total_rows; ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $prevLink; ?>"
                            class="bg-purple-500 text-white border-2 px-4 py-2 rounded hover:bg-white hover:text-purple-700 hover:border-purple-700">
                            Previous
                        </a>
                    <?php else: ?>
                        <button class="bg-gray-300 text-white px-4 py-2 rounded cursor-not-allowed" disabled>
                            Previous
                        </button>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $nextLink; ?>"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Next
                        </a>
                    <?php else: ?>
                        <button class="bg-gray-300 text-white px-4 py-2 rounded cursor-not-allowed" disabled>
                            Next
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="mt-6">
        <canvas id="pdf-viewer" width="600" height="400"></canvas>
    </div>
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
                <!-- Changed type to "button" and added onclick handler -->
                <button type="button" onclick="handleRejectSubmit()"
                    class="bg-blue-600 hover:bg-blue-700 mt-4 w-full text-white font-bold py-2 px-4 rounded">
                    Submit
                </button>
            </form>
        </div>
    </div>

    <script>
        // ==================
        // Existing Scripts
        // ==================

        // For Reject Buttons
        $(document).on('click', '.reject-btn', function() {
            var rejectId = $(this).data('id'); // Get the ID from the button's data-id attribute
            $('#reject_id').val(rejectId); // Set the reject_id input field with the ID
            $('#rejectModal').show(); // Show the modal
        });

        // Close the modal when the close button is clicked
        $('.close').on('click', function() {
            $('#rejectModal').hide(); // Hide the modal
        });

        // Optionally close the modal when clicked outside of the modal content
        $(window).on('click', function(event) {
            if ($(event.target).is('#rejectModal')) {
                $('#rejectModal').hide();
            }
        });

        // Example PDF Viewing (not critical, but left intact)
        var url = 'uploads/your-file.pdf';
        var pdfjsLib = window['pdfjs-dist'];

        pdfjsLib.getDocument(url).promise.then(function(pdf) {
            pdf.getPage(1).then(function(page) {
                var scale = 1.5;
                var viewport = page.getViewport({
                    scale: scale
                });

                var canvas = document.getElementById('pdf-viewer');
                var context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                page.render({
                    canvasContext: context,
                    viewport: viewport
                });
            });
        });

        // Function to handle rejection with a confirmation prompt
        function handleReject(rejectId) {
            const confirmation = confirm("ARE YOU SURE YOU WANT TO DELETE THIS?");
            if (confirmation) {
                // Create a form dynamically to submit the rejection
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "";

                // Add reject_id input
                const rejectIdInput = document.createElement("input");
                rejectIdInput.type = "hidden";
                rejectIdInput.name = "reject_id";
                rejectIdInput.value = rejectId;
                form.appendChild(rejectIdInput);

                // Append the form to the body and submit it
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

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

        // Function to open the confirmation modal (using the correct id)
        function openConfirmationModal() {
            document.getElementById("rejectconfirmationModal").style.display = "flex";
        }

        // Function to close the confirmation modal
        function closeConfirmationModal() {
            document.getElementById("rejectconfirmationModal").style.display = "none"
            document.getElementById("confirmModal").style.display = "none"
        }

        // This function handles the rejection form submission.
        // It prevents immediate submission, checks if a reason is entered, and opens the confirmation modal.
        function handleRejectSubmit() {
            const reason = document.getElementById('reason').value.trim();
            if (reason === '') {
                alert('Please enter a reason for rejection.');
                return;
            }
            formToSubmit = document.getElementById('rejectForm'); // Store the form for later submission
            openConfirmationModal(); // Open the custom confirmation modal
        }

        // Confirm rejection when 'Yes, Reject' is clicked
        document.getElementById('confirmRejectBtn').addEventListener('click', function() {
            formToSubmit.submit(); // Submit the stored form
        });
    </script>



</body>

</html>