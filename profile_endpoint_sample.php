<?php
// Add this to dashboardserver.php
if (isset($_GET['get_profile']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    // Example: fetch user info from DB (replace with your queries)
    $email = 'user@email.com'; // fetch from users table
    $phone = '08012345678'; // fetch from users table
    echo json_encode([
        'success' => true,
        'username' => $username,
        'email' => $email,
        'phone' => $phone
    ]);
    exit();
}
?>
