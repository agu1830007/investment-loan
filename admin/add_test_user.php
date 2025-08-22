<?php
require_once __DIR__ . '/../db.php';
// Simple test user insert script
$username = 'testuser';
$email = 'testuser@example.com';
$phone = '08012345678';
$password = password_hash('testpass', PASSWORD_DEFAULT); // hashed password
$role = 'user';

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $phone, $password, $role]);
    echo "Test user created successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "Test user already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
