<?php
// token management.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Very basic authentication for the token management interface
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($_POST['username'] !== 'admin' || $_POST['password'] !== 'admin123') {
        echo '
        <div style="max-width: 400px; margin: 100px auto; padding: 20px; border: 1px solid #ccc;">
            <h2>Token Management Login</h2>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label>Username:</label>
                    <input type="text" name="username" required style="width: 100%; padding: 8px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Password:</label>
                    <input type="password" name="password" required style="width: 100%; padding: 8px;">
                </div>
                <button type="submit" style="background: #7c3aed; color: white; padding: 10px 15px; border: none; width: 100%;">Login</button>
            </form>
        </div>
        ';
        exit;
    }
    $_SESSION['admin_logged_in'] = true;
}

$servername = "localhost";
$usernameDB = "financial";
$passwordDB = "UbrdRDvrHRAyHiA]";
$dbname = "financial_db";

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_token'])) {
        $token = $_POST['token'];
        $token_name = $_POST['token_name'];
        $department = $_POST['department'];
        $description = $_POST['description'];
        $expires_at = $_POST['expires_at'] ?: null;
        
        $stmt = $conn->prepare("INSERT INTO department_tokens (token, token_name, department, description, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $token, $token_name, $department, $description, $expires_at);
        $stmt->execute();
    } elseif (isset($_POST['toggle_token'])) {
        $token_id = $_POST['token_id'];
        $is_active = $_POST['is_active'];
        
        $stmt = $conn->prepare("UPDATE department_tokens SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $token_id);
        $stmt->execute();
    } elseif (isset($_POST['delete_token'])) {
        $token_id = $_POST['token_id'];
        
        $stmt = $conn->prepare("DELETE FROM department_tokens WHERE id = ?");
        $stmt->bind_param("i", $token_id);
        $stmt->execute();
    }
}

// Fetch all tokens
$tokens = $conn->query("SELECT * FROM department_tokens ORDER BY department, created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Department Tokens Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Department Tokens Management</h1>
        
        <!-- Add Token Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Add New Token</h2>
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Token</label>
                        <input type="text" name="token" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Token Name</label>
                        <input type="text" name="token_name" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Department</label>
                        <select name="department" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="">Select Department</option>
                            <option>Administrative</option>
                            <option>Core-1</option>
                            <option>Core-2</option>
                            <option>Human Resource-1</option>
                            <option>Human Resource-2</option>
                            <option>Human Resource-3</option>
                            <option>Human Resource-4</option>
                            <option>Logistic-1</option>
                            <option>Logistic-2</option>
                            <option>Financial</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Expiration Date (optional)</label>
                        <input type="datetime-local" name="expires_at" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea name="description" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <button type="submit" name="add_token" class="bg-blue-600 text-white px-4 py-2 rounded-lg">Add Token</button>
            </form>
        </div>
        
        <!-- Tokens List -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Existing Tokens</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b">ID</th>
                            <th class="py-2 px-4 border-b">Token</th>
                            <th class="py-2 px-4 border-b">Token Name</th>
                            <th class="py-2 px-4 border-b">Department</th>
                            <th class="py-2 px-4 border-b">Status</th>
                            <th class="py-2 px-4 border-b">Created</th>
                            <th class="py-2 px-4 border-b">Expires</th>
                            <th class="py-2 px-4 border-b">Last Used</th>
                            <th class="py-2 px-4 border-b">Usage Count</th>
                            <th class="py-2 px-4 border-b">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($token = $tokens->fetch_assoc()): ?>
                        <tr>
                            <td class="py-2 px-4 border-b"><?= $token['id'] ?></td>
                            <td class="py-2 px-4 border-b font-mono"><?= substr($token['token'], 0, 10) ?>...</td>
                            <td class="py-2 px-4 border-b"><?= $token['token_name'] ?></td>
                            <td class="py-2 px-4 border-b"><?= $token['department'] ?></td>
                            <td class="py-2 px-4 border-b">
                                <span class="px-2 py-1 rounded-full text-xs <?= $token['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $token['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="py-2 px-4 border-b"><?= $token['created_at'] ?></td>
                            <td class="py-2 px-4 border-b"><?= $token['expires_at'] ?: 'Never' ?></td>
                            <td class="py-2 px-4 border-b"><?= $token['last_used_at'] ?: 'Never' ?></td>
                            <td class="py-2 px-4 border-b"><?= $token['usage_count'] ?></td>
                            <td class="py-2 px-4 border-b">
                                <form method="POST" class="inline-block">
                                    <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $token['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" name="toggle_token" class="text-blue-600 hover:text-blue-800 mr-2">
                                        <?= $token['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this token?');">
                                    <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                                    <button type="submit" name="delete_token" class="text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>