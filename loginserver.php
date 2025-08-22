<?php
session_start();
require_once 'auth-messages.php';
require_once 'db.php';

$errors = [];
$success = '';
$username = '';

// Restore errors/success from session if redirected
if (isset($_SESSION['login_errors'])) {
    $errors = $_SESSION['login_errors'];
    unset($_SESSION['login_errors']);
}
if (isset($_SESSION['login_success'])) {
    $success = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
if (isset($_SESSION['login_old'])) {
    $username = $_SESSION['login_old'];
    unset($_SESSION['login_old']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $hasError = false;
    if ($username === '') {
        $errors['username'] = 'Username is required.';
        $hasError = true;
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
        $hasError = true;
    }
    // Detect AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$hasError) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            if ($isAjax) {
                echo json_encode(['success'=>false, 'message'=>'Database error.']);
                exit;
            } else {
                $errors['login'] = 'Database error.';
            }
        }
        if (isset($user) && $user && password_verify($password, $user['password'])) {
            // Set session variables for admin and user
            if (isset($user['is_admin']) && $user['is_admin'] == 1) {
                $_SESSION['user'] = $username;
                $_SESSION['is_admin'] = 1;
                unset($_SESSION['username']); // Remove user session var for admin
                if ($isAjax) {
                    echo json_encode(['success'=>true, 'redirect'=>'admin/admindashboard.php', 'is_admin'=>true]);
                    exit;
                } else {
                    header('Location: admin/admindashboard.php');
                    exit;
                }
            } else {
                $_SESSION['username'] = $username;
                $_SESSION['email'] = isset($user['email']) ? $user['email'] : '';
                $_SESSION['phone'] = isset($user['phone']) ? $user['phone'] : '';
                unset($_SESSION['user']);
                unset($_SESSION['is_admin']);
                if ($isAjax) {
                    echo json_encode(['success'=>true, 'redirect'=>'dashboard.php', 'is_admin'=>false]);
                    exit;
                } else {
                    header('Location: dashboard.php');
                    exit;
                }
            }
        } else {
            if ($isAjax) {
                echo json_encode(['success'=>false, 'message'=>'Invalid username or password.']);
                exit;
            }
            $errors['login'] = 'Invalid username or password.';
            if (!isset($errors['username'])) $errors['username'] = '';
            if (!isset($errors['password'])) $errors['password'] = '';
        }
    }
    if ($errors) {
        if ($isAjax) {
            echo json_encode(['success'=>false, 'message'=>implode(' ', $errors)]);
            exit;
        }
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_old'] = $username;
        header('Location: login.php');
        exit;
    }
}
?>
