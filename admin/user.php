<?php
require_once '../db.php';
header('Content-Type: text/html; charset=UTF-8');

// Fetch all users
$users = $pdo->query('SELECT id, username, email, phone, registered, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="dashboard-section" id="users">
    <h2>All Users</h2>
    <table class="dashboard-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registered</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody id="users-body">
        <?php if (count($users) === 0): ?>
            <tr><td colspan="6" style="text-align:center; color:#888;">No users found.</td></tr>
        <?php else: foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['phone']) ?></td>
                <td><?= htmlspecialchars($user['registered']) ?></td>
                <td><?= htmlspecialchars($user['role']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <button onclick="exportTable('users')" style="float:right;">Export CSV</button>
</div>
<script>
// Simple export to CSV for users table
function exportTable(tableId) {
    var table = document.querySelector('.dashboard-table');
    var rows = table.querySelectorAll('tr');
    var csv = [];
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('th, td');
        for (var j = 0; j < cols.length; j++)
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        csv.push(row.join(','));
    }
    var csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    var downloadLink = document.createElement('a');
    downloadLink.download = 'users.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>
</body>
</html>
