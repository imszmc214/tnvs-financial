<?php
session_start();
$servername = "localhost";
$usernameDB = "financial";
$passwordDB = "UbrdRDvrHRAyHiA]";
$dbname = "financial_db";
$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$type = $_GET['type'] ?? 'budget';
$tab = $_GET['tab'] ?? 'recent';
$search = $_GET['search'] ?? '';
$req_name = $_SESSION['givenname'] . ' ' . $_SESSION['surname'];

$tableInfo = [
    'budget' => [
        'table' => 'budget_request',
        'refCol' => 'reference_id',
        'dateCol' => 'created_at',
        'catCol' => 'expense_categories',
        'descCol' => 'description',
        'docCol' => 'document',
        'amtCol' => 'amount',
        'userCol' => 'account_name',
        'statusCol' => 'status'
    ],
    'petty_cash' => [
        'table' => 'pettycash',
        'refCol' => 'reference_id',
        'dateCol' => 'created_at',
        'catCol' => 'expense_categories',
        'descCol' => 'description',
        'docCol' => 'document',
        'amtCol' => 'amount',
        'userCol' => 'account_name',
        'statusCol' => 'status'
    ],
    'payable' => [
        'table' => 'accounts_payable',
        'refCol' => 'invoice_id',
        'dateCol' => 'created_at',
        'catCol' => 'department',
        'descCol' => 'description',
        'docCol' => 'document',
        'amtCol' => 'amount',
        'userCol' => 'account_name',
        'statusCol' => 'status'
    ],
    'emergency' => [
        'table' => 'pa',
        'refCol' => 'reference_id',
        'dateCol' => 'requested_at',
        'catCol' => 'expense_categories',
        'descCol' => 'description',
        'docCol' => 'document',
        'amtCol' => 'amount',
        'userCol' => 'account_name',
        'statusCol' => 'status'
    ]
];

$info = $tableInfo[$type];
$table = $info['table'];
$refCol = $info['refCol'];
$dateCol = $info['dateCol'];
$catCol = $info['catCol'];
$descCol = $info['descCol'];
$docCol = $info['docCol'];
$amtCol = $info['amtCol'];
$userCol = $info['userCol'];
$statusCol = $info['statusCol'];

$whereUser = "$userCol = '".$conn->real_escape_string($req_name)."'";
$searchClause = $search ? "AND ($refCol LIKE '%$search%' OR $catCol LIKE '%$search%' OR $amtCol LIKE '%$search%' OR $descCol LIKE '%$search%')" : "";

if ($tab == 'recent') {
    $where = "$whereUser AND TIMESTAMPDIFF(DAY, $dateCol, NOW()) < 7 $searchClause";
} else {
    $where = "$whereUser AND TIMESTAMPDIFF(DAY, $dateCol, NOW()) >= 7 $searchClause";
}

$sql = "SELECT * FROM $table WHERE $where ORDER BY $dateCol DESC LIMIT 20";
$result = $conn->query($sql);

echo '<div class="overflow-x-auto w-full">';
echo '<table class="w-full table-auto bg-white mt-4 rounded-xl border">';
echo '<thead>
<tr class="text-blue-800 uppercase text-sm leading-normal text-left">
    <th class="pl-10 py-2">Reference ID</th>
    <th class="px-4 py-2">Category</th>
    <th class="px-4 py-2">Amount</th>
    <th class="px-4 py-2">Description</th>
    <th class="px-4 py-2">Document</th>
    <th class="px-4 py-2">Status</th>
    <th class="px-4 py-2">Created At</th>
    <th class="px-4 py-2">Actions</th>
</tr>
</thead>';
echo '<tbody class="text-gray-900 text-sm font-light">';
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $refid = $row[$refCol] ?? '';
        $cat = $row[$catCol] ?? '';
        $amt = $row[$amtCol] ?? '';
        $desc = $row[$descCol] ?? '';
        $doc = $row[$docCol] ?? '';
        $status = $row[$statusCol] ?? 'pending';
        $statColor = $status === 'approved' ? 'text-green-700' : ($status === 'rejected' ? 'text-red-700' : 'text-yellow-700');
        $created = $row[$dateCol] ?? '';
        $showAppeal = ($status === 'rejected');
        echo "<tr>
            <td class='pl-10 py-2 font-mono'>$refid</td>
            <td class='px-4 py-2'>$cat</td>
            <td class='px-4 py-2'>₱" . number_format($amt,2) . "</td>
            <td class='px-4 py-2'>$desc</td>
            <td class='px-4 py-2'>";
        if (!empty($doc)) {
            $fileUrl = urlencode($doc);
            echo "<a href='view_pdf.php?file={$fileUrl}' target='_blank' class='font-semibold text-blue-700 px-2 py-1 rounded hover:text-purple-600'>View File</a>";
        } else {
            echo "<span class='text-gray-400 italic'>No document available</span>";
        }
        echo "</td>
            <td class='px-4 py-2 font-semibold $statColor'>".ucfirst($status)."</td>
            <td class='px-4 py-2'>" . ($created ? date('Y-m-d H:i', strtotime($created)) : '') . "</td>
            <td class='px-4 py-2'>";
        if ($showAppeal) {
            echo '<button class="bg-blue-600 text-white px-3 py-1 rounded appeal-btn" onclick="alert(\'Appeal logic here (implement as needed).\')"><i class="fas fa-undo"></i> Appeal</button>';
        } else {
            echo '-';
        }
        echo "</td></tr>";
    }
} else {
    echo "<tr><td colspan='8' class='text-center py-4'>No records found</td></tr>";
}
echo '</tbody>';
echo '</table>';
echo '</div>';


$conn->close();