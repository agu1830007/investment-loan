<?php
session_start();
require_once __DIR__ . '/db.php';

// Use username from session (like dashboard.php)
$username = $_SESSION['username'] ?? null;
if (!$username) {
    header('Location: login.php');
    exit();
}
// Fetch user info from database
$user_id = null;
$email = '';
$phone = '';
if (isset($pdo)) {
    $stmt = $pdo->prepare('SELECT id, email, phone FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $user_id = $row['id'];
        $email = $row['email'];
        $phone = $row['phone'];
    }
}
if (!$user_id) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    if ($new_username && $new_email && $new_phone) {
        $stmt = $pdo->prepare('UPDATE users SET username=?, email=?, phone=? WHERE id=?');
        $stmt->execute([$new_username, $new_email, $new_phone, $user_id]);
        $_SESSION['success'] = 'Profile updated successfully!';
        $_SESSION['username'] = $new_username;
        $username = $new_username;
        $email = $new_email;
        $phone = $new_phone;
    } else {
        $_SESSION['error'] = 'All fields are required.';
    }
    header('Location: dashboard.php#profile');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body style="background:#f8fafd;">
    <div style="max-width:420px;margin:3rem auto;background:#fff;padding:2rem 2.2rem 1.5rem 2.2rem;border-radius:1.2rem;box-shadow:0 2px 16px rgba(44,62,80,0.10);">
        <h2 style="margin-bottom:1.5rem;">Edit Profile</h2>
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
            <label for="username" style="font-weight:600;color:#232946;">Username:</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required style="padding:0.7rem 1rem;margin-bottom:1rem;border-radius:0.5rem;border:1.5px solid #d1d9e6;font-size:1rem;width:100%;background:#f8fafd;">
            <label for="email" style="font-weight:600;color:#232946;">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required style="padding:0.7rem 1rem;margin-bottom:1rem;border-radius:0.5rem;border:1.5px solid #d1d9e6;font-size:1rem;width:100%;background:#f8fafd;">
            <label for="phone" style="font-weight:600;color:#232946;">Phone:</label>
            <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($phone); ?>" required style="padding:0.7rem 1rem;margin-bottom:1.5rem;border-radius:0.5rem;border:1.5px solid #d1d9e6;font-size:1rem;width:100%;background:#f8fafd;">
            <button type="submit" style="padding:0.9rem 0;width:100%;background:#1a237e;color:#fff;border:none;border-radius:0.6rem;font-size:1.1rem;font-weight:600;letter-spacing:0.5px;transition:background 0.2s;cursor:pointer;">Save Changes</button>
        </form>
        
    </div>
</body>
</html>
