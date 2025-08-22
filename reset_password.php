<?php
// reset_password.php
require_once 'db.php';
session_start();
$token = $_GET['token'] ?? '';
$error = $success = '';
$new_password = $confirm_password = '';

if (!$token) {
    $error = 'Invalid or missing token.';
} else {
    // Check token validity using PDO
    $stmt = $pdo->prepare('SELECT email, expires_at FROM password_resets WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || strtotime($row['expires_at']) < time()) {
        $error = 'This password reset link is invalid or has expired.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Update user password using PDO
            $email = $row['email'];
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt2 = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
            $stmt2->execute([$hashed, $email]);
            // Remove used token
            $stmt3 = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
            $stmt3->execute([$token]);
            $success = 'Your password has been reset. <a href="login.php">Login</a>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8f9fa;">
        <form method="post" action="" id="resetForm" style="background:#fff;padding:2rem 2.5rem;border-radius:1rem;box-shadow:0 2px 12px rgba(0,0,0,0.08);min-width:220px;max-width:350px;">
            <h2 style="text-align:center;margin-bottom:1.5rem;">Reset Password</h2>
            <?php if ($error): ?>
                <div class="input-error-message" style="text-align:center;"> <?php echo $error; ?> </div>
            <?php elseif ($success): ?>
                <div class="input-success-message" style="text-align:center;"> <?php echo $success; ?> </div>
            <?php else: ?>
                <input type="password" name="new_password" placeholder="New password" required minlength="6" style="width:100%;padding:0.85rem 1rem;margin-bottom:1.1rem;border:1.5px solid #e0e3eb;border-radius:0.6rem;font-size:1rem;background:#f8fafc;">
                <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="6" style="width:100%;padding:0.85rem 1rem;margin-bottom:1.1rem;border:1.5px solid #e0e3eb;border-radius:0.6rem;font-size:1rem;background:#f8fafc;">
                <button type="submit" style="width:100%;padding:0.8rem 0;background:#007bff;color:#fff;border:none;border-radius:0.5rem;font-size:1rem;">Reset Password</button>
            <?php endif; ?>
            <p style="margin-top:1.2rem;text-align:center;font-size:0.98rem;">
                <a href="login.php" style="color:#007bff;">Back to Login</a>
            </p>
        </form>
    </main>
</body>
</html>
