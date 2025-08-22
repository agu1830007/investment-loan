<?php
// forgot_password.php
session_start();
$success = $error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require_once 'db.php'; // sets up $pdo (PDO)
        global $pdo;
        if (!isset($pdo) || !$pdo) {
            die('Database connection failed. Please check db.php.');
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            // User exists, generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            // Remove any old tokens for this email
            $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
            // Insert new token
            $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)')->execute([$email, $token, $expires]);
            // Send email
            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";
            $subject = 'Password Reset Request';
            $message = "Hello,\n\nTo reset your password, click the link below or paste it into your browser:\n$resetLink\n\nThis link will expire in 1 hour. If you did not request a password reset, you can ignore this email.";
            $headers = 'From: no-reply@' . $_SERVER['HTTP_HOST'];
            @mail($email, $subject, $message, $headers);
        }
        // Always show the same message for security
        $success = 'If this email is registered, a password reset link has been sent.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8f9fa;">
        <form method="post" action="" id="forgotForm" style="background:#fff;padding:2rem 2.5rem;border-radius:1rem;box-shadow:0 2px 12px rgba(0,0,0,0.08);min-width:220px;max-width:350px;">
            <h2 style="text-align:center;margin-bottom:1.5rem;">Forgot Password</h2>
            <?php if ($success): ?>
                <div class="input-success-message" style="text-align:center;"> <?php echo $success; ?> </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="input-error-message" style="text-align:center;"> <?php echo $error; ?> </div>
            <?php endif; ?>
            <input type="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email); ?>" required autofocus style="width:100%;padding:0.85rem 1rem;margin-bottom:1.1rem;border:1.5px solid #e0e3eb;border-radius:0.6rem;font-size:1rem;background:#f8fafc;">
            <button type="submit" style="width:100%;padding:0.8rem 0;background:#007bff;color:#fff;border:none;border-radius:0.5rem;font-size:1rem;">Send Reset Link</button>
            <p style="margin-top:1.2rem;text-align:center;font-size:0.98rem;">
                <a href="login.php" style="color:#007bff;">Back to Login</a>
            </p>
        </form>
    </main>
</body>
</html>
