<?php
session_start();

// Check if it's an API call for notification operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleNotificationAction();
    exit();
}

// Regular notification display functionality
displayNotifications();

function handleNotificationAction() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    $servername = "localhost";
    $usernameDB = "financial";
    $passwordDB = "UbrdRDvrHRAyHiA]";
    $dbname = "financial_db";

    $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }

    $action = $_POST['action'];
    $notificationId = $_POST['id'] ?? 0;

    switch ($action) {
        case 'mark_all_read':
            // Mark all notifications as read for the current user
            $userId = $_SESSION['user_id'] ?? 0;
            $sql = "UPDATE requestor_notif SET is_read = 1 WHERE user_id = ? AND is_read = 0";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            break;

        case 'mark_read':
            // Mark specific notification as read
            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
                exit();
            }
            $sql = "UPDATE requestor_notif SET is_read = 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $notificationId);
            break;

        case 'delete':
            // Delete specific notification
            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
                exit();
            }
            $sql = "DELETE FROM requestor_notif WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $notificationId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}

function displayNotifications() {
    $role = $_SESSION['user_role'] ?? '';
    $req_name = $_SESSION['givenname'] . ' ' . $_SESSION['surname'];

    $servername = "localhost";
    $usernameDB = "financial";
    $passwordDB = "UbrdRDvrHRAyHiA]";
    $dbname = "financial_db";
    $conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

    if ($conn->connect_error) { 
        echo '<div class="text-gray-400 text-sm text-center py-8">Database connection error.</div>';
        exit();
    }

    // Fetch the latest 15 notifications
    $sql = "SELECT * FROM requestor_notif ORDER BY timestamp DESC LIMIT 15";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($n = $result->fetch_assoc()) {
            $isUnread = !isset($n['is_read']) || $n['is_read'] == 0;
            $bgColor = $isUnread ? 'bg-violet-50' : 'bg-white';
            $unreadClass = $isUnread ? 'unread' : '';
            
            echo '<div class="notification-item '.$bgColor.' '.$unreadClass.' p-3 rounded-lg border border-gray-100 cursor-pointer transition-colors duration-200" data-id="'.$n['id'].'">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="font-medium text-gray-800 text-sm mb-1">'.htmlspecialchars($n['message']).'</div>
                        <div class="text-xs text-gray-500">'.date("F j, Y, g:i a", strtotime($n['timestamp'])).'</div>
                    </div>
                    <div class="dropdown-container relative">
                        <button class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100" onclick="event.stopPropagation(); toggleDropdown(this)">
                            <i class="fas fa-ellipsis-v text-xs"></i>
                        </button>
                        <div class="dropdown-menu hidden absolute right-0 mt-1 w-32 bg-white rounded-md shadow-lg py-1 z-10 border border-gray-200">
                            <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" onclick="event.stopPropagation(); deleteNotification('.$n['id'].')">
                                <i class="fas fa-trash mr-2 text-xs"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>';
        }
    } else {
        echo '<div class="text-gray-400 text-sm text-center py-8">No notifications.</div>';
    }
    $conn->close();
}
?>