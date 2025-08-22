<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/db.php';

// Use username from session (like dashboard.php)
$username = $_SESSION['username'] ?? null;
if (!$username) {
    header('Location: login.php');
    exit();
}
// Fetch user ID from database
$user_id = null;
if (isset($pdo)) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $user_id = $row['id'];
    }
}
if (!$user_id) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!isset($pdo)) {
        $_SESSION['error'] = 'Database connection ($pdo) is not set.';
    } elseif ($new !== $confirm) {
        $_SESSION['error'] = 'New passwords do not match.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($old, $row['password'])) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                if ($updateStmt->execute([$hash, $user_id])) {
                    $_SESSION['success'] = 'Password changed successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update password.';
                }
            } else {
                $_SESSION['error'] = 'Old password is incorrect.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
    }
    // Do not redirect, stay on this page and display messages
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body style="background:#f8fafd;">
    <div style="max-width:420px;margin:3rem auto;background:#fff;padding:2rem 2.2rem 1.5rem 2.2rem;border-radius:1.2rem;box-shadow:0 2px 16px rgba(44,62,80,0.10);">
        <h2 style="margin-bottom:1.5rem;">Change Password</h2>
        <?php
        if (isset($_SESSION['error'])) {
            echo '<div style="color:#b71c1c;font-weight:600;margin-bottom:1rem;">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div style="color:#26734d;font-weight:600;margin-bottom:1rem;">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>
        <form method="post" action="">
            <label for="old_password" style="font-weight:600;color:#232946;">Current Password:</label>
            <input type="password" name="old_password" id="old_password" required style="padding:0.7rem 1rem;margin-bottom:1rem;border-radius:0.5rem;border:1.5px solid #d1d9e6;font-size:1rem;width:100%;background:#f8fafd;">
            <label for="new_password" style="font-weight:600;color:#232946;">New Password:</label>
            <input type="password" name="new_password" id="new_password" required style="padding:0.7rem 1rem;margin-bottom:1rem;border-radius:0.5rem;border:1.5px solid #d1d9e6;font-size:1rem;width:100%;background:#f8fafd;">
            <label for="confirm_password" style="font-weight:600;color:#232946;">Confirm New Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required style="padding:0.7rem 1rem;margin-bottom:1.5rem;border-radius:0.5rem;border:1.5px solid #d1d9e6;font-size:1rem;width:100%;background:#f8fafd;">
            <button type="submit" style="padding:0.9rem 0;width:100%;background:#1a237e;color:#fff;border:none;border-radius:0.6rem;font-size:1.1rem;font-weight:600;letter-spacing:0.5px;transition:background 0.2s;cursor:pointer;">Change Password</button>
        </form>
        <!-- Back to Dashboard link removed as user stays on this page -->
    </div>
</body>
</html>
