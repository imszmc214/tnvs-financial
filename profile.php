<?php
session_start();
include 'session_manager.php';
$servername = 'localhost';
$usernameDB = 'financial';
$passwordDB = 'UbrdRDvrHRAyHiA]';
$dbname = 'financial_db';

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Fetch user details
$stmt = $conn->prepare("SELECT username, gname, minitial, surname, address, age, contact, email, role FROM userss WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle PIN change request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_pin"])) {
    $current_pin = $_POST["current_pin"];
    $new_pin = $_POST["new_pin"];
    $confirm_pin = $_POST["confirm_pin"];

    $user_id = $_SESSION["user_id"];

    // Check if PIN is exactly 6 digits
    if (!preg_match('/^\d{6}$/', $new_pin)) {
        $error = "PIN must be exactly 6 digits!";
    } elseif ($new_pin !== $confirm_pin) {
        $error = "New PIN and Confirm PIN do not match!";
    } else {
        // Fetch the user's current PIN from the database
        $stmt = $conn->prepare("SELECT pin FROM userss WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Compare as a simple string since PINs are stored as plain VARCHAR
        if (!$user || $current_pin !== $user["pin"]) {
            $error = "Current PIN is incorrect!";
        } else {
            // Update the PIN directly (without hashing)
            $update_stmt = $conn->prepare("UPDATE userss SET pin = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_pin, $user_id);
            if ($update_stmt->execute()) {
                $success = "PIN changed successfully!";
            } else {
                $error = "Error updating PIN.";
            }
        }
    }
}

$stmt->close();
$conn->close();
?>

<html>

<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <title>Profile</title>
    <link rel="icon" href="logo.png" type="img">
</head>

<body>
    <?php include('sidebar.php'); ?>
    <div class="flex-1 p-6 overflow-y-auto h-full px-6">
        <div class="bg-white p-6 rounded-lg w-full">
            <h2 class="text-xl font-bold mb-4 text-gray-800">PROFILE</h2>
            <div class="flex items-center gap-4">
                <div class="h-24 text-5xl w-24 rounded-full bg-zinc-300 uppercase flex items-center font-bold justify-center">
                    <span class="mb-1">
                        <?php echo $_SESSION['users_username'][0] ?>
                    </span>
                </div>
                <p class="text-3xl font-bold mb-2"><?php echo $_SESSION['users_username']; ?></p>
            </div>

            <div class="mt-6">
                <h3 class="text-lg font-semibold">User Information</h3>
                <table class="mt-4 border-collapse border border-gray-300 w-full text-left">
                    <tr><td class="p-2 border border-gray-300 font-semibold">Full Name</td><td class="p-2 border border-gray-300"><?php echo htmlspecialchars($user['gname'] . ' ' . $user['minitial'] . '. ' . $user['surname']); ?></td></tr>
                    <tr><td class="p-2 border border-gray-300 font-semibold">Address</td><td class="p-2 border border-gray-300"><?php echo htmlspecialchars($user['address']); ?></td></tr>
                    <tr><td class="p-2 border border-gray-300 font-semibold">Age</td><td class="p-2 border border-gray-300"><?php echo htmlspecialchars($user['age']); ?></td></tr>
                    <tr><td class="p-2 border border-gray-300 font-semibold">Contact</td><td class="p-2 border border-gray-300"><?php echo htmlspecialchars($user['contact']); ?></td></tr>
                    <tr><td class="p-2 border border-gray-300 font-semibold">Email</td><td class="p-2 border border-gray-300"><?php echo htmlspecialchars($user['email']); ?></td></tr>
                    <tr><td class="p-2 border border-gray-300 font-semibold">Role</td><td class="p-2 border border-gray-300"><?php echo htmlspecialchars($user['role']); ?></td></tr>
                </table>
            </div>

            <!-- PIN Change Form -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold">Change PIN</h3>

                <?php if (isset($error)) : ?>
                    <p class="text-red-500"><?php echo $error; ?></p>
                <?php endif; ?>
                <?php if (isset($success)) : ?>
                    <p class="text-green-500"><?php echo $success; ?></p>
                <?php endif; ?>

                <form method="POST" class="mt-4">
                    <div class="mb-3">
                        <label class="block text-sm font-medium">Current PIN</label>
                        <input type="password" name="current_pin" pattern="\d{6}" title="PIN must be exactly 6 digits" required class="w-full p-2 border rounded" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium">New PIN (6 digits)</label>
                        <input type="password" name="new_pin" pattern="\d{6}" title="PIN must be exactly 6 digits" required class="w-full p-2 border rounded" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium">Confirm New PIN</label>
                        <input type="password" name="confirm_pin" pattern="\d{6}" title="PIN must be exactly 6 digits" required class="w-full p-2 border rounded" />
                    </div>
                    <button type="submit" name="change_pin" class="bg-blue-500 text-white px-4 py-2 rounded">
                        Change PIN
                    </button>
                </form>
            </div>

        </div>
        <div class="mt-6">
        <canvas id="pdf-viewer" width="600" height="400"></canvas>
    </div>
    </div>

</body>

</html>