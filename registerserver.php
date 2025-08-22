<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $errors = [];
    $old = [
        'username' => $username,
        'email' => $email,
        'phone' => $phone
    ];
    if (!$username) $errors['username'] = 'Username is required.';
    if (!$email) $errors['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    if (!$phone) $errors['phone'] = 'Phone number is required.';
    if (!$password) $errors['password'] = 'Password is required.';
    elseif (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
    if (!$confirm) $errors['confirm'] = 'Please confirm your password.';
    elseif ($password !== $confirm) $errors['confirm'] = 'Passwords do not match.';
    if (!$errors) {
        require_once 'db.php';
        // Check if username or email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors['register'] = 'Username or email already exists.';
        } else {
            // Insert new user, always provide phone (empty string if not set)
            $stmt = $pdo->prepare('INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['success'] = 'Registration successful! Please log in.';
            header('Location: login.php');
            exit;
        }
    }
    if ($errors) {
        $_SESSION['register_errors'] = $errors;
        $_SESSION['register_old'] = $old;
        header('Location: register.php');
        exit;
    }
}
