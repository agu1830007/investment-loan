<?php
// userserver.php - Returns all users as JSON for admin dashboard, with debug logging
session_start();
header('Content-Type: application/json; charset=UTF-8');

function log_debug($msg) {
    file_put_contents(__DIR__ . '/userserver_debug.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

if (
    (!isset($_SESSION['user']) && !isset($_SESSION['username']))
    || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1
) {
    http_response_code(403);
    log_debug('Unauthorized access. Session: ' . json_encode($_SESSION));
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../db.php';

try {
    // Admin: Update user info (username, email, phone, role, is_admin)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $fields = [];
        $params = [];
        if (isset($_POST['username'])) { $fields[] = 'username = ?'; $params[] = trim($_POST['username']); }
        if (isset($_POST['email'])) { $fields[] = 'email = ?'; $params[] = trim($_POST['email']); }
        if (isset($_POST['phone'])) { $fields[] = 'phone = ?'; $params[] = trim($_POST['phone']); }
        if (isset($_POST['role'])) { $fields[] = 'role = ?'; $params[] = trim($_POST['role']); }
        if (isset($_POST['is_admin'])) { $fields[] = 'is_admin = ?'; $params[] = intval($_POST['is_admin']); }
        if (count($fields) > 0) {
            $params[] = $id;
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute($params);
            log_debug('Admin updated user id ' . $id . ': ' . json_encode($fields));
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
        }
        exit();
    }
    // If ?detail=<id> is provided, return details for that user
    if (isset($_GET['detail']) && is_numeric($_GET['detail'])) {
        $id = intval($_GET['detail']);
        $stmt = $pdo->prepare('SELECT id, username, email, phone, created_at AS registered, role FROM users WHERE id=?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $uid = $user['id'];
            // Count investments
            $istmt = $pdo->prepare('SELECT COUNT(*) FROM investments WHERE user_id = ?');
            $istmt->execute([$uid]);
            $user['investments'] = (int)$istmt->fetchColumn();
            // Count loans
            $lstmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ?');
            $lstmt->execute([$uid]);
            $user['loans'] = (int)$lstmt->fetchColumn();
            echo json_encode($user);
        } else {
            echo json_encode(new stdClass());
        }
        log_debug('Returned user detail for id: ' . $id);
        exit();
    }

    // Otherwise, return all users and their investments/loans count
    $stmt = $pdo->query('SELECT id, username, email, phone, created_at AS registered, role FROM users');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_debug('Fetched users: ' . count($users));
    foreach ($users as &$user) {
        $uid = $user['id'];
        $istmt = $pdo->prepare('SELECT COUNT(*) FROM investments WHERE user_id = ?');
        $istmt->execute([$uid]);
        $user['investments'] = (int)$istmt->fetchColumn();
        $lstmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ?');
        $lstmt->execute([$uid]);
        $user['loans'] = (int)$lstmt->fetchColumn();
    }
    echo json_encode(['users' => $users]);
    log_debug('Returned users JSON.');
} catch (Exception $e) {
    http_response_code(500);
    log_debug('Error: ' . $e->getMessage());
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
?>