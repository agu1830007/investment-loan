<?php

// Disable error reporting/output for AJAX JSON endpoints
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
global $_SESSION;
file_put_contents(__DIR__ . '/dashboardserver.log', date('Y-m-d H:i:s') . " [TOP] dashboardserver.php loaded\n", FILE_APPEND);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    file_put_contents(__DIR__ . '/dashboardserver.log', date('Y-m-d H:i:s') . " [GET] _GET: " . json_encode($_GET) . ", SESSION: " . json_encode($_SESSION) . "\n", FILE_APPEND);
}
session_start();

require_once __DIR__ . '/db.php';
// --- Debug logging for backend AJAX/API actions ---
$debug_log_file = __DIR__ . '/dashboardserver.log';
function log_dashboard_debug($msg)
{
    global $debug_log_file, $_SESSION;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($debug_log_file, "[$ts] $msg\n", FILE_APPEND);
}
// --- Paystack Payment Verification ---
if (isset($_POST['paystack_reference']) && !empty($_POST['paystack_reference'])) {
    // User/session validation
    if (!isset($_SESSION['username'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
        exit;
    }
    $username = $_SESSION['username'];
    // Get user_id from session or DB
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
        if ($user_id) {
            $_SESSION['user_id'] = $user_id;
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
    }

    $reference = $_POST['paystack_reference'];
    $amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $account_name = isset($_POST['account_name']) ? $_POST['account_name'] : '';
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $payment_note = isset($_POST['payment_note']) ? $_POST['payment_note'] : '';
    // Handle payment proof upload
    $payment_proof_filename = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/payment_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $basename = uniqid('proof_', true) . '.' . $ext;
        $target = $upload_dir . $basename;
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target)) {
            $payment_proof_filename = 'payment_proofs/' . $basename;
        }
    }

    $paystack_secret_key = 'sk_test_c4dc39f131a8c4fd6b239c2bd31a3bf7ec546903'; // Replace with your Paystack secret key
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/verify/' . urlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $paystack_secret_key,
        'Cache-Control: no-cache',
    ]);
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result = json_decode($result, true);

    if ($httpcode == 200 && isset($result['status']) && $result['status'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
        // Optionally check amount and currency match
        $paid_amount = $result['data']['amount'] / 100.0; // Paystack returns amount in kobo
        $paid_currency = $result['data']['currency'];
        if ($paid_amount >= $amount && $paid_currency === 'NGN') {
            // Record payment in DB
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    amount DECIMAL(12,2),
                    email VARCHAR(255),
                    reference VARCHAR(100),
                    status VARCHAR(20),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $stmt = $pdo->prepare('INSERT INTO payments (user_id, amount, email, reference, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$user_id, $paid_amount, $email, $reference, 'confirmed']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Payment verified but DB error: ' . $e->getMessage()]);
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'Deposit verified successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Amount or currency mismatch.']);
            exit;
        }
    } else {
        $msg = isset($result['message']) ? $result['message'] : 'Verification failed.';
        echo json_encode(['success' => false, 'message' => 'Paystack verification failed: ' . $msg]);
        exit;
    }
}


// Always ensure user_id is set in session for every request if username is present
if (isset($_SESSION['username'])) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $username = $_SESSION['username'];
        try {
            $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt_id->execute([$username]);
            $user_id = $stmt_id->fetchColumn();
            if ($user_id) {
                $_SESSION['user_id'] = $user_id;
                log_dashboard_debug("Session user_id set for username '$username' (user_id=$user_id)");
            } else {
                log_dashboard_debug("Username '$username' not found in users table when setting session user_id.");
            }
        } catch (Exception $e) {
            log_dashboard_debug("Exception when setting session user_id for username '$username': " . $e->getMessage());
        }
    }
}
// --- AJAX: Get User Withdrawal History for dashboard.php ---
if (isset($_POST['ajax_get_withdrawals']) && isset($_SESSION['username'])) {
    log_dashboard_debug("AJAX: ajax_get_withdrawals called by username='" . $_SESSION['username'] . "'.");
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();
    try {
        $stmt = $pdo->prepare('SELECT amount, bank_name, account_number, account_name, status, created_at, processed_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user_id]);
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Format output
        foreach ($withdrawals as &$w) {
            $w['amount'] = isset($w['amount']) ? floatval($w['amount']) : 0;
            $w['bank_name'] = htmlspecialchars($w['bank_name']);
            $w['account_number'] = htmlspecialchars($w['account_number']);
            $w['account_name'] = htmlspecialchars($w['account_name']);
            $w['status'] = htmlspecialchars(ucfirst($w['status']));
            $w['created_at'] = $w['created_at'] ? date('Y-m-d', strtotime($w['created_at'])) : '-';
            $w['processed_at'] = $w['processed_at'] ? date('Y-m-d', strtotime($w['processed_at'])) : '-';
        }
        echo json_encode(['withdrawals' => $withdrawals]);
    } catch (Exception $e) {
        echo json_encode(['withdrawals' => []]);
    }
    exit();
}

// --- AJAX: Get User Notifications ---
if (isset($_GET['get_notifications']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user VARCHAR(100),
            type VARCHAR(50),
            user_id INT,
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_read TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $stmt = $pdo->prepare('SELECT id, type, user_id, message, created_at, is_read FROM notifications WHERE user = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$username]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Format output
        foreach ($notifications as &$n) {
            $n['message'] = htmlspecialchars($n['message']);
            $n['created_at'] = $n['created_at'] ? date('Y-m-d H:i', strtotime($n['created_at'])) : '-';
            $n['is_read'] = (int) $n['is_read'];
        }
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'notifications' => []]);
    }
    exit();
}

// --- AJAX: Mark Notification as Read ---
if (isset($_POST['mark_notification_read']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    $notifId = intval($_POST['mark_notification_read']);
    try {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user = ?');
        $stmt->execute([$notifId, $username]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit();
}
// --- PERIODIC: Update Due Status and Send Notifications for Loans and Investments ---
// Call this endpoint via cron or manually: ?run_due_status_update=1&admin_secret=YOUR_SECRET
if (isset($_GET['run_due_status_update']) && isset($_GET['admin_secret']) && $_GET['admin_secret'] === 'YOUR_SECRET') {
    header('Content-Type: application/json');
    $results = [
        'loans_due_updated' => 0,
        'loans_notified' => 0,
        'investments_matured_updated' => 0,
        'investments_notified' => 0
    ];
    // --- Loans: Mark as due and notify if due_date <= today and not repaid ---
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id, user_id, due_date, status FROM loans WHERE status != 'repaid' AND status != 'due' AND due_date <= ?");
    $stmt->execute([$today]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($loans as $loan) {
        // Update status to 'due'
        $stmt2 = $pdo->prepare("UPDATE loans SET status = 'due' WHERE id = ?");
        $stmt2->execute([$loan['id']]);
        $results['loans_due_updated']++;
        // Check if notification already sent
        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user = ? AND type = 'loan_due' AND user_id = ?");
        $stmt3->execute([$loan['user_id'], $loan['id']]);
        if ($stmt3->fetchColumn() == 0) {
            $msg = "Your loan is now due. Please repay as soon as possible.";
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user VARCHAR(100),
                type VARCHAR(50),
                user_id INT,
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $stmt4 = $pdo->prepare("INSERT INTO notifications (user, type, user_id, message) VALUES (?, 'loan_due', ?, ?)");
            $stmt4->execute([$loan['user_id'], $loan['id'], $msg]);
            $results['loans_notified']++;
        }
    }
    // --- Investments: Mark as matured and notify if maturity date <= today and status is active ---
    $stmt = $pdo->prepare("SELECT id, user_id, next_payout, status FROM investments WHERE status = 'active' AND next_payout <= ?");
    $stmt->execute([$today]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($investments as $inv) {
        // Update status to 'matured'
        $stmt2 = $pdo->prepare("UPDATE investments SET status = 'matured' WHERE id = ?");
        $stmt2->execute([$inv['id']]);
        $results['investments_matured_updated']++;
        // Check if notification already sent
        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user = ? AND type = 'investment_matured' AND user_id = ?");
        $stmt3->execute([$inv['user_id'], $inv['id']]);
        file_put_contents(__DIR__ . '/dashboardserver.log', date('Y-m-d H:i:s') . " [TOP] dashboardserver.php loaded\n", FILE_APPEND);
        if ($stmt3->fetchColumn() == 0) {
            $msg = "Your investment has matured. Please check your account for payout details.";
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user VARCHAR(100),
                type VARCHAR(50),
                user_id INT,
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $stmt4 = $pdo->prepare("INSERT INTO notifications (user, type, user_id, message) VALUES (?, 'investment_matured', ?, ?)");
            $stmt4->execute([$inv['user_id'], $inv['id'], $msg]);
            $results['investments_notified']++;
        }
    }
    echo json_encode(['success' => true] + $results);
    exit();
}

// Handle AJAX requests for investments

if (isset($_POST['ajax_get_investments'])) {
    log_dashboard_debug("AJAX: ajax_get_investments called. Session: " . json_encode($_SESSION));
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $username = $_SESSION['username'] ?? '';
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    $stmt = $pdo->prepare('SELECT plan, amount, interest_rate, total_repayment, status, created_at, next_payout FROM investments WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $investments = [];
    foreach ($rows as $row) {
        $investments[] = [
            'plan' => htmlspecialchars($row['plan']),
            'amount' => floatval($row['amount']),
            'interest_rate' => htmlspecialchars($row['interest_rate']),
            'total_repayment' => floatval($row['total_repayment']),
            'status' => htmlspecialchars(ucfirst($row['status'])),
            'created_at' => $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '-',
            'next_payout' => $row['next_payout'] ? date('Y-m-d', strtotime($row['next_payout'])) : '-'
        ];
    }
    // Also return updated user balance for frontend
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = "confirmed"');
    $stmt->execute([$user_id]);
    $totalPayments = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "completed")');
    $stmt->execute([$user_id]);
    $totalWithdrawals = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM investments WHERE user_id = ? AND status = "active"');
    $stmt->execute([$user_id]);
    $activeInvestments = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM repayments WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $totalRepayments = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;
    $user_balance = $totalPayments - $totalWithdrawals - $activeInvestments - $totalRepayments;
    echo json_encode(['investments' => $investments, 'user_balance' => $user_balance]);
    exit;
}

// Handle AJAX investment request
if (isset($_POST['get_portfolio_summary'])) {
    log_dashboard_debug("AJAX: get_portfolio_summary called. Session: " . json_encode($_SESSION));
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $user_id = null;
    if ($username) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user_id = $stmt->fetchColumn();
        if (!$user_id) {
            log_dashboard_debug("get_portfolio_summary: Username '$username' not found in users table.");
        }
    } else {
        log_dashboard_debug("get_portfolio_summary: Username is empty in session.");
    }
    $summary = [
        'total_payments' => 0,
        'total_withdrawals' => 0,
        'total_invested' => 0,
        'total_investment_interest' => 0,
        'total_loan_principal' => 0,
        'total_loan_interest' => 0,
        'total_repayments' => 0,
        'portfolio_value' => 0
    ];
    if ($user_id) {
        // Total Payments
        $stmt = $pdo->prepare('SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = "confirmed"');
        $stmt->execute([$user_id]);
        $summary['total_payments'] = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;

        $stmt = $pdo->prepare('SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "completed")');
        $stmt->execute([$user_id]);
        $summary['total_withdrawals'] = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;

        // Total Invested and Investment Interest (active investments)
        $stmt = $pdo->prepare('SELECT amount, interest_rate, duration FROM investments WHERE user_id = ? AND status = "active"');
        $stmt->execute([$user_id]);
        $summary['total_invested'] = 0;
        $summary['total_investment_interest'] = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inv) {
            $amt = floatval($inv['amount']);
            $rate = floatval($inv['interest_rate']);
            $duration = isset($inv['duration']) ? floatval($inv['duration']) : 1;
            $summary['total_invested'] += $amt;
            $summary['total_investment_interest'] += $amt * ($rate / 100) * $duration;
        }

        // Outstanding Loan Principal and Interest (not fully repaid)
        $stmt = $pdo->prepare('SELECT amount, interest_rate, duration FROM loans WHERE user_id = ? AND status != "repaid"');
        $stmt->execute([$user_id]);
        $summary['total_loan_principal'] = 0;
        $summary['total_loan_interest'] = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $loan) {
            $amt = floatval($loan['amount']);
            $rate = floatval($loan['interest_rate']);
            $duration = isset($loan['duration']) ? floatval($loan['duration']) : 1;
            $summary['total_loan_principal'] += $amt;
            $summary['total_loan_interest'] += $amt * ($rate / 100) * $duration;
        }

        $stmt = $pdo->prepare('SELECT SUM(amount) FROM repayments WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $summary['total_repayments'] = $stmt->fetchColumn() !== null ? floatval($stmt->fetchColumn()) : 0;

        // Cash Balance
        $summary['cash_balance'] = $summary['total_payments'] - $summary['total_withdrawals'] - $summary['total_invested'] - $summary['total_repayments'];

        // Portfolio Value
        $summary['portfolio_value'] = $summary['cash_balance'] + $summary['total_investment_interest'] - $summary['total_loan_principal'] - $summary['total_loan_interest'];

        log_dashboard_debug("get_portfolio_summary: user_id=$user_id, summary=" . json_encode($summary));
    } else {
        log_dashboard_debug("get_portfolio_summary: user_id is not set for username '$username'.");
    }
    echo json_encode(['success' => true, 'summary' => $summary]);
    exit;
}
// ...existing code...
// Handle AJAX withdrawal request
if ((isset($_POST['ajax_withdrawal']) || isset($_POST['ajax_withdraw_funds'])) && isset($_SESSION['username'])) {
    while (ob_get_level())
        ob_end_clean();
    if (!headers_sent())
        header('Content-Type: application/json');
    $username = $_SESSION['username'];
    $amount = floatval($_POST['withdraw_amount'] ?? 0);
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    // Accept both 'account_name' and 'account_holder' for compatibility
    $account_name = trim($_POST['account_name'] ?? ($_POST['account_holder'] ?? ''));
    $note = trim($_POST['withdraw_note'] ?? '');
    // Get user_id from session or lookup
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    $errors = [];
    if ($amount < 1000)
        $errors[] = 'Minimum withdrawal is ₦1,000.';
    if (!preg_match('/^\d{10}$/', $account_number))
        $errors[] = 'Account number must be 10 digits.';
    if (!$bank_name)
        $errors[] = 'Bank name is required.';
    if (!$account_name)
        $errors[] = 'Account name is required.';
    if ($errors) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit();
    }
    try {
        // Create withdrawals table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            account_number VARCHAR(20) NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            note TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        // Insert withdrawal request
        $stmt = $pdo->prepare("INSERT INTO withdrawals (user_id, amount, account_number, bank_name, account_name, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $amount, $account_number, $bank_name, $account_name, $note]);
        // Notify admin of new withdrawal request
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50),
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $msg = sprintf('New withdrawal request from %s: ₦%s, Bank: %s, Account: %s.', $username, number_format($amount, 2), $bank_name, $account_number);
            $stmt = $pdo->prepare("INSERT INTO admin_notifications (type, message) VALUES (?, ?)");
            $stmt->execute(['withdrawal', $msg]);
        } catch (Exception $e) {
        }
        echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted! Await admin approval.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}
// --- Ensure clean JSON output for AJAX ---
// dashboardserver.php - Handles AJAX requests for the dashboard
// Remove duplicate session_start and require_once 'db.php' to avoid session_start() warning
// ob_start();
// session_start();
// require_once 'db.php';

// --- AJAX: Portfolio Overview (all values and breakdown) ---
// --- AJAX: Breakdown Modal ---
if (isset($_GET['get_breakdown'])) {
    if (!isset($_SESSION['username'])) {
        echo json_encode(['html' => '<span style="color:#b71c1c;">Not logged in.</span>']);
        exit;
    }
    global $pdo;
    $username = $_SESSION['username'];
    $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();
    $totalPayments = 0;
    $totalWithdrawals = 0;
    $activeInvestments = 0;
    $loans = 0;
    $outstandingLoans = 0;
    // Get total payments (only confirmed)
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM payments WHERE user_id = ? AND status = "confirmed"');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPayments = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get total withdrawals (approved, confirmed, and completed)
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "confirmed" OR status = "completed")');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalWithdrawals = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get active investments
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM investments WHERE user_id = ? AND status = "active"');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeInvestments = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get total loans
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM loans WHERE user_id = ? AND (status = "approved" OR status = "active")');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $loans = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get outstanding loans (not fully repaid)
    $stmt = $pdo->prepare('SELECT SUM(amount + (amount * 0.05 * duration)) as total FROM loans WHERE user_id = ? AND status != "repaid"');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $outstandingLoans = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get total repayments
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM repayments WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRepayments = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Calculate breakdown percentages
    $allParts = [
        'Total Payments' => $totalPayments,
        'Invested' => $activeInvestments,
        'Loan Received' => $loans,
        'Withdrawn' => $totalWithdrawals,
        'Repaid Loans' => $totalRepayments
    ];
    $portfolioTotal = array_sum($allParts);
    if ($portfolioTotal > 0) {
        $percentages = [];
        $sumPercent = 0;
        foreach ($allParts as $label => $val) {
            $percent = $portfolioTotal > 0 ? round(($val / $portfolioTotal) * 100) : 0;
            $percentages[$label] = $percent;
            $sumPercent += $percent;
        }
        // Adjust so total is always 100% (fix rounding)
        if ($sumPercent !== 100) {
            $diff = 100 - $sumPercent;
            $maxLabel = array_keys($percentages, max($percentages))[0];
            $percentages[$maxLabel] += $diff;
        }
        $breakdownHtml = '';
        $tooltips = [
            'Total Payments' => 'All money you have paid into your account',
            'Invested' => 'Money you have put into investments',
            'Loan Received' => 'Money you have borrowed (loaned to you)',
            'Withdrawn' => 'Money you have withdrawn from your account',
            'Repaid Loans' => 'Total amount you have repaid for loans'
        ];
        $cssMap = [
            'Total Payments' => 'balance',
            'Invested' => 'investments',
            'Loan Received' => 'loans',
            'Withdrawn' => 'withdrawals',
            'Repaid Loans' => 'repayments'
        ];
        foreach ($percentages as $label => $percent) {
            $css = isset($cssMap[$label]) ? $cssMap[$label] : '';
            $tooltip = isset($tooltips[$label]) ? $tooltips[$label] : '';
            $breakdownHtml .= '<span class="breakdown-pill breakdown-' . $css . '" title="' . htmlspecialchars($tooltip) . '">' . $label . ': ' . $percent . '%</span>';
        }
    } else {
        $breakdownHtml = '<span style="color:#888;">No data</span>';
    }
    // Calculate cashBalance and portfolioValue for frontend (subtract repayments)
    $cashBalance = $totalPayments - $totalWithdrawals - $activeInvestments - $totalRepayments;
    $portfolioValue = $cashBalance + $activeInvestments - $outstandingLoans;
    echo json_encode([
        'html' => $breakdownHtml,
        'activeInvestments' => $activeInvestments,
        'outstandingLoans' => $outstandingLoans,
        'totalPayments' => $totalPayments,
        'totalWithdrawals' => $totalWithdrawals,
        'totalRepayments' => $totalRepayments,
        'cashBalance' => $cashBalance,
        'portfolioValue' => $portfolioValue
    ]);
    exit;
}
if (isset($_GET['get_portfolio_overview'])) {
    if (!isset($_SESSION['username'])) {
        echo json_encode(['error' => 'Not logged in']);
        ob_end_flush();
        exit;
    }
    global $pdo;
    $username = $_SESSION['username'];
    $totalPayments = 0;
    $totalWithdrawals = 0;
    $activeInvestments = 0;
    $loans = 0;
    // Get user_id from username if not set
    if (!isset($user_id) || !$user_id) {
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    // Get total payments (only confirmed)
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM payments WHERE user_id = ? AND status = "confirmed"');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPayments = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get total withdrawals
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "completed")');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalWithdrawals = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get active investments
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM investments WHERE user_id = ? AND status = "active"');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeInvestments = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Get total loans (only approved or active loans count in breakdown)
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM loans WHERE user_id = ? AND (status = "approved" OR status = "active")');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $loans = ($row && isset($row['total'])) ? floatval($row['total']) : 0;
    // Calculate balance: payments - investments - loans - withdrawals
    $balance = $totalPayments - $activeInvestments - $loans - $totalWithdrawals;
    $displayBalance = $balance;
    // Always include all four parts for clarity, but never show negative cash balance
    $allParts = [
        'Total Payments' => $totalPayments,
        'Invested' => $activeInvestments,
        'Loan Received' => $loans,
        'Withdrawn' => $totalWithdrawals
    ];
    $portfolioTotal = array_sum($allParts);
    if ($portfolioTotal > 0) {
        $percentages = [];
        $sumPercent = 0;
        foreach ($allParts as $label => $val) {
            $percent = $portfolioTotal > 0 ? round(($val / $portfolioTotal) * 100) : 0;
            $percentages[$label] = $percent;
            $sumPercent += $percent;
        }
        // Adjust so total is always 100% (fix rounding)
        if ($sumPercent !== 100) {
            $diff = 100 - $sumPercent;
            $maxLabel = array_keys($percentages, max($percentages))[0];
            $percentages[$maxLabel] += $diff;
        }
        $breakdownHtml = '';
        $tooltips = [
            'Total Payments' => 'All money you have paid into your account',
            'Invested' => 'Money you have put into investments',
            'Loan Received' => 'Money you have borrowed (loaned to you)',
            'Withdrawn' => 'Money you have withdrawn from your account'
        ];
        $cssMap = [
            'Total Payments' => 'balance',
            'Invested' => 'investments',
            'Loan Received' => 'loans',
            'Withdrawn' => 'withdrawals'
        ];
        foreach ($percentages as $label => $percent) {
            $css = isset($cssMap[$label]) ? $cssMap[$label] : '';
            $tooltip = isset($tooltips[$label]) ? $tooltips[$label] : '';
            $breakdownHtml .= '<span class="breakdown-pill breakdown-' . $css . '" title="' . htmlspecialchars($tooltip) . '">' . $label . ': ' . $percent . '%</span>';
        }
    } else {
        $breakdownHtml = '<span style="color:#888;">No data</span>';
    }
    echo json_encode([
        'balance' => $displayBalance,
        'total_payments' => $totalPayments,
        'total_withdrawals' => $totalWithdrawals,
        'active_investments' => $activeInvestments,
        'loans' => $loans,
        'breakdown_html' => $breakdownHtml
    ]);
    ob_end_flush();
    exit;
}

// Withdrawals Overview Endpoint
if (isset($_GET['get_withdrawals_overview']) && isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    try {
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status IN ('completed','approved')");
        $stmt->execute([$user_id]);
        $total = floatval($stmt->fetchColumn());
        echo json_encode(['total' => $total]);
    } catch (Exception $e) {
        echo json_encode(['total' => 0]);
    }
    exit();
}

// Handle AJAX request for balance breakdown
if (isset($_GET['get_balance_breakdown']) && isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $result = [
        'success' => false,
        'payments' => 0,
        'outstanding_loans' => 0,
        'withdrawals' => 0,
        'total' => 0
    ];

    // 1. Confirmed Payments (by username)
    $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'confirmed'");
    $stmt->execute([$user_id]);
    $result['payments'] = floatval($stmt->fetchColumn());


    // Calculate matured investments and accrued interest
    $result['matured_investments'] = 0;
    $result['accrued_interest'] = 0;
    $now = new DateTime();
    $stmt = $pdo->prepare("SELECT amount, interest_rate, status, created_at FROM investments WHERE username = ?");
    $stmt = $pdo->prepare("SELECT amount, interest_rate, status, created_at FROM investments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    while ($inv = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $principal = isset($inv['amount']) ? floatval($inv['amount']) : 0;
        $rate = isset($inv['interest_rate']) && is_numeric($inv['interest_rate']) ? floatval($inv['interest_rate']) : 0;
        $created = isset($inv['created_at']) ? new DateTime($inv['created_at']) : $now;
        $months = max(0, $created->diff($now)->m + 12 * $created->diff($now)->y);
        $interest = $principal * $rate * $months / 100;
        if ($inv['status'] === 'matured') {
            $result['matured_investments'] += $principal;
            $result['accrued_interest'] += $interest;
        } elseif ($inv['status'] === 'active') {
            $result['accrued_interest'] += $interest;
        }
    }


    // 4. Outstanding Loans (principal + accrued interest, unpaid, by user_id)
    $stmt = $pdo->prepare("SELECT amount, interest_rate, created_at, duration FROM loans WHERE user_id = ? AND status IN ('active','pending')");
    $stmt->execute([$user_id]);
    $outstanding = 0;
    while ($loan = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $principal = isset($loan['amount']) ? floatval($loan['amount']) : 0;
        $rate = isset($loan['interest_rate']) && is_numeric($loan['interest_rate']) ? floatval($loan['interest_rate']) : 5.0;
        $duration = isset($loan['duration']) && is_numeric($loan['duration']) ? floatval($loan['duration']) : 4;
        $start = isset($loan['created_at']) ? new DateTime($loan['created_at']) : $now;
        $weeks = min($duration, max(0, $start->diff($now)->days / 7));
        $interest = $principal * $rate * $weeks / 100;
        $outstanding += $principal + $interest;
    }
    $result['outstanding_loans'] = $outstanding;

    // 5. Withdrawals (completed, by user_id)
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $result['withdrawals'] = floatval($stmt->fetchColumn());

    // Total balance formula
    $result['total'] = $result['payments']
        + $result['matured_investments']
        + $result['accrued_interest']
        - $result['outstanding_loans']
        - $result['withdrawals'];

    $result['success'] = true;
    echo json_encode($result);
    exit;
}

// Return user loans for AJAX request
if (isset($_GET['get_loans']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $username = $_SESSION['username'];
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    try {
        $loans = [];
        if ($user_id) {
            $stmt = $pdo->prepare('SELECT id, type, amount, status, due_date, created_at, duration FROM loans WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$user_id]);
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Add interest and total_repayment fields for each loan
            foreach ($loans as &$loan) {
                $amount = isset($loan['amount']) ? floatval($loan['amount']) : 0;
                $duration = isset($loan['duration']) ? intval($loan['duration']) : 0;
                $interest = $amount * 0.05 * $duration;
                $loan['interest'] = $interest;
                $loan['total_repayment'] = $amount + $interest;
            }
        }
        echo json_encode(['loans' => $loans]);
    } catch (Exception $e) {
        echo json_encode(['loans' => []]);
    }
    exit();
}
// Handle AJAX POST for loans (dashboard.php)
if (isset($_POST['ajax_get_loans']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    // Always fetch user_id from DB for current username to avoid session leaks
    $username = $_SESSION['username'];
    $user_id = null;
    if ($username) {
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
        $_SESSION['user_id'] = $user_id;
    }
    try {
        $loans = [];
        if ($user_id) {
            $stmt = $pdo->prepare('SELECT id, type, amount, status, due_date, created_at, duration FROM loans WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$user_id]);
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Add interest and total_repayment fields for each loan, matching frontend keys
            foreach ($loans as &$loan) {
                $amount = isset($loan['amount']) ? floatval($loan['amount']) : 0;
                $duration = isset($loan['duration']) ? intval($loan['duration']) : 0;
                $interest_rate = 5.0; // Default interest rate
                $interest = $amount * ($interest_rate / 100) * $duration;
                $loan['interest'] = $interest;
                $loan['total_repayment'] = $amount + $interest;
                $loan['interest_rate'] = $interest_rate;
            }
        }
        log_dashboard_debug('AJAX: ajax_get_loans for user_id=' . $user_id . ', loans=' . json_encode($loans));
        echo json_encode(['loans' => $loans]);
    } catch (Exception $e) {
        log_dashboard_debug('AJAX: ajax_get_loans error for user_id=' . $user_id . ': ' . $e->getMessage());
        echo json_encode(['loans' => []]);
    }
    exit();
}
// --- AJAX: Get User Repayment History for dashboard.php ---
if (isset($_POST['ajax_get_repayments']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    // Always use session user_id if set, fallback to username lookup
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $username = $_SESSION['username'];
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    try {
        $stmt = $pdo->prepare('SELECT loan_id, amount, repaid_at FROM repayments WHERE user_id = ? ORDER BY repaid_at DESC');
        $stmt->execute([$user_id]);
        $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Format output
        foreach ($repayments as &$r) {
            $r['amount'] = isset($r['amount']) ? floatval($r['amount']) : 0;
            $r['repaid_at'] = $r['repaid_at'] ? date('Y-m-d', strtotime($r['repaid_at'])) : '-';
        }
        log_dashboard_debug('AJAX: ajax_get_repayments for user_id=' . $user_id . ', repayments=' . json_encode($repayments));
        echo json_encode(['repayments' => $repayments]);
    } catch (Exception $e) {
        log_dashboard_debug('AJAX: ajax_get_repayments error for user_id=' . $user_id . ': ' . $e->getMessage());
        echo json_encode(['repayments' => []]);
    }
    exit();
}

// Return user investments for AJAX request
if (isset($_GET['get_investments']) && isset($_SESSION['username'])) {
    if (ob_get_level())
        ob_clean();
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $username = $_SESSION['username'];
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    try {
        $stmt = $pdo->prepare('SELECT plan, amount, interest_rate, status, created_at, next_payout FROM investments WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user_id]);
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['investments' => $investments]);
    } catch (Exception $e) {
        echo json_encode(['investments' => []]);
    }
    exit();
}

// Handle AJAX investment request
if (isset($_POST['ajax_invest']) && isset($_SESSION['username'])) {
    while (ob_get_level())
        ob_end_clean();
    if (!headers_sent())
        header('Content-Type: application/json');
    try {
        $username = $_SESSION['username'];
        $plan = $_POST['invest_plan'] ?? '';
        $amount = floatval($_POST['invest_amount'] ?? 0);
        $note = trim($_POST['invest_note'] ?? '');
        $plans = [
            'StarterBoost' => ['min' => 1000, 'max' => 4999, 'rate' => 5],
            'ProGrow' => ['min' => 5000, 'max' => 19999, 'rate' => 7],
            'EliteMax' => ['min' => 20000, 'max' => 1000000, 'rate' => 10]
        ];
        if (!isset($plans[$plan])) {
            echo json_encode(['success' => false, 'message' => 'Invalid plan selected.']);
            exit();
        }
        $min = $plans[$plan]['min'];
        $max = $plans[$plan]['max'];
        $rate = $plans[$plan]['rate'];
        if ($amount < $min || $amount > $max) {
            echo json_encode(['success' => false, 'message' => "Amount for $plan must be between ₦" . number_format($min) . " and ₦" . number_format($max) . "."]);
            exit();
        }
        // Get user_id from session or lookup
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt_id->execute([$username]);
            $user_id = $stmt_id->fetchColumn();
        }
        // Calculate user's available cash balance using only confirmed payments, approved/completed withdrawals, active investments, and repayments
        $stmt = $pdo->prepare('SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = "confirmed"');
        $stmt->execute([$user_id]);
        $totalPayments = $stmt->fetchColumn();
        $totalPayments = $totalPayments !== null ? floatval($totalPayments) : 0;
        $stmt = $pdo->prepare('SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "completed")');
        $stmt->execute([$user_id]);
        $totalWithdrawals = $stmt->fetchColumn();
        $totalWithdrawals = $totalWithdrawals !== null ? floatval($totalWithdrawals) : 0;
        $stmt = $pdo->prepare('SELECT SUM(amount) FROM investments WHERE user_id = ? AND status = "active"');
        $stmt->execute([$user_id]);
        $activeInvestments = $stmt->fetchColumn();
        $activeInvestments = $activeInvestments !== null ? floatval($activeInvestments) : 0;
        $stmt = $pdo->prepare('SELECT SUM(amount) FROM repayments WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $totalRepayments = $stmt->fetchColumn();
        $totalRepayments = $totalRepayments !== null ? floatval($totalRepayments) : 0;
        $cashBalance = $totalPayments - $totalWithdrawals - $activeInvestments - $totalRepayments;
        if ($amount > $cashBalance) {
            echo json_encode(['success' => false, 'message' => 'You cannot invest more than your available cash balance (₦' . number_format($cashBalance, 2) . ').']);
            exit();
        }
        $stmt = $pdo->prepare("INSERT INTO investments (user_id, plan, amount, interest_rate, total_repayment, status, created_at, next_payout, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $total_repayment = $amount + ($amount * $rate * 4 / 100); // Assuming 4 is duration in months
        $status = 'active';
        $created_at = date('Y-m-d H:i:s');
        $next_payout = date('Y-m-d H:i:s', strtotime('+1 month'));
        $stmt->execute([$user_id, $plan, $amount, $rate, $total_repayment, $status, $created_at, $next_payout, $note]);
        // Fetch updated investments and balance
        $stmt2 = $pdo->prepare('SELECT plan, amount, interest_rate, total_repayment, status, created_at, next_payout FROM investments WHERE user_id = ? ORDER BY created_at DESC');
        $stmt2->execute([$user_id]);
        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $investments = [];
        foreach ($rows as $row) {
            $investments[] = [
                'plan' => htmlspecialchars($row['plan']),
                'amount' => floatval($row['amount']),
                'interest_rate' => htmlspecialchars($row['interest_rate']),
                'total_repayment' => floatval($row['total_repayment']),
                'status' => htmlspecialchars(ucfirst($row['status'])),
                'created_at' => $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '-',
                'next_payout' => $row['next_payout'] ? date('Y-m-d', strtotime($row['next_payout'])) : '-'
            ];
        }
        $stmt3 = $pdo->prepare('SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = "confirmed"');
        $stmt3->execute([$user_id]);
        $totalPayments = $stmt3->fetchColumn() !== null ? floatval($stmt3->fetchColumn()) : 0;
        $stmt4 = $pdo->prepare('SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "completed")');
        $stmt4->execute([$user_id]);
        $totalWithdrawals = $stmt4->fetchColumn() !== null ? floatval($stmt4->fetchColumn()) : 0;
        $stmt5 = $pdo->prepare('SELECT SUM(amount) FROM investments WHERE user_id = ? AND status = "active"');
        $stmt5->execute([$user_id]);
        $activeInvestments = $stmt5->fetchColumn() !== null ? floatval($stmt5->fetchColumn()) : 0;
        $stmt6 = $pdo->prepare('SELECT SUM(amount) FROM repayments WHERE user_id = ?');
        $stmt6->execute([$user_id]);
        $totalRepayments = $stmt6->fetchColumn() !== null ? floatval($stmt6->fetchColumn()) : 0;
        $user_balance = $totalPayments - $totalWithdrawals - $activeInvestments - $totalRepayments;
        // file_put_contents(__DIR__ . '/user_debug.log', ...) removed to avoid accidental output
        echo json_encode([
            'success' => true,
            'message' => 'Investment created successfully!',
            'investments' => $investments,
            'user_balance' => $user_balance
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.', 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle withdrawal request
if (isset($_POST['withdraw_funds']) && isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $amount = floatval($_POST['withdraw_amount'] ?? 0);
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $note = trim($_POST['withdraw_note'] ?? '');
    $errors = [];
    // Get user_id from session or lookup
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
    }
    if ($amount < 1000)
        $errors[] = 'Minimum withdrawal is ₦1,000.';
    if (!preg_match('/^\d{10}$/', $account_number))
        $errors[] = 'Account number must be 10 digits.';
    if (!$bank_name)
        $errors[] = 'Bank name is required.';
    if (!$account_name)
        $errors[] = 'Account name is required.';
    if ($errors) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit();
    }
    try {
        // Create withdrawals table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            account_number VARCHAR(20) NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            note TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        // Insert withdrawal request
        $stmt = $pdo->prepare("INSERT INTO withdrawals (username, amount, account_number, bank_name, account_name, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$username, $amount, $account_number, $bank_name, $account_name, $note]);
        // Notify admin of new withdrawal request
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50),
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $msg = sprintf('New withdrawal request from %s: ₦%s, Bank: %s, Account: %s.', $username, number_format($amount, 2), $bank_name, $account_number);
            $stmt = $pdo->prepare("INSERT INTO admin_notifications (type, message) VALUES (?, ?)");
            $stmt->execute(['withdrawal', $msg]);
        } catch (Exception $e) {
        }
        echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted! Await admin approval.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}

// Loan Repayment Endpoint
if (isset($_POST['ajax_repay_loan']) && isset($_POST['loan_id']) && isset($_SESSION['username'])) {
    $loanId = intval($_POST['loan_id']);
    // Always fetch user_id from session or username for safety
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $username = $_SESSION['username'];
        $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt_id->execute([$username]);
        $user_id = $stmt_id->fetchColumn();
        if ($user_id) {
            $_SESSION['user_id'] = $user_id;
        }
    }
    // If still no user_id, log and abort
    if (!$user_id) {
        error_log('Repayment error: user_id missing for username ' . ($_SESSION['username'] ?? 'UNKNOWN'));
        echo json_encode(['success' => false, 'message' => 'User not found. Please log in again.', 'error' => 'user_id missing']);
        exit;
    }
    // Fetch loan and check ownership and status
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ?");
    $stmt->execute([$loanId, $user_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found.']);
        exit;
    }
    if ($loan['status'] === 'repaid') {
        echo json_encode(['success' => false, 'message' => 'Loan already repaid.']);
        exit;
    }
    // Extra check: prevent duplicate repayments for the same loan
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM repayments WHERE loan_id = ?");
    $stmt->execute([$loanId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This loan has already been repaid (repayment record exists).']);
        exit;
    }
    // Calculate total repayment
    $amount = floatval($loan['amount']);
    $duration = intval($loan['duration']);
    $interest = $amount * 0.05 * $duration;
    $total = $amount + $interest;

    // Check user balance
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalPayment = floatval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $totalWithdrawal = floatval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT SUM(amount) FROM investments WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $activeInvestments = floatval($stmt->fetchColumn());

    // Subtract total repayments from cash balance
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM repayments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalRepayments = floatval($stmt->fetchColumn());
    $cashBalance = $totalPayment - $totalWithdrawal - $activeInvestments - $totalRepayments;
    if ($cashBalance < $total) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance to repay loan.']);
        exit;
    }

    // Mark loan as repaid
    $stmt = $pdo->prepare("UPDATE loans SET status = 'repaid', repaid_at = NOW() WHERE id = ?");
    $stmt->execute([$loanId]);

    // Insert repayment record
    $stmt = $pdo->prepare("INSERT INTO repayments (user_id, loan_id, amount, repaid_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $loanId, $total]);

    // Optionally, insert a notification
    $msg = "You have successfully repaid your loan of ₦" . number_format($total, 2) . ".";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, user_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, 'loan_repaid', $user_id, $msg]);
    echo json_encode(['success' => true, 'message' => 'Loan repaid successfully!']);
    exit;
}

if (!isset($_SESSION['username']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated: session missing. Please log in again.']);
    exit();
}
$username = $_SESSION['username'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Edit Profile (AJAX only)
if (isset($_POST['ajax_edit_profile']) && isset($_SESSION['username'])) {
    while (ob_get_level())
        ob_end_clean();
    if (!headers_sent())
        header('Content-Type: application/json');
    $oldUsername = $_SESSION['username'];
    $user_id = $_SESSION['user_id'] ?? null;
    $newUsername = trim($_POST['edit_username'] ?? $oldUsername);
    $email = trim($_POST['edit_email'] ?? '');
    $phone = trim($_POST['edit_phone'] ?? '');
    // Username validation
    if (!$newUsername || strlen($newUsername) < 3 || strlen($newUsername) > 32 || !preg_match('/^[a-zA-Z0-9_ ]+$/', $newUsername)) {
        echo json_encode(['success' => false, 'message' => 'Invalid username. Use 3-32 letters, numbers, underscores, or spaces.']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit();
    }
    if (!preg_match('/^\+?\d{7,15}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
        exit();
    }
    // Check for duplicate username (if changed)
    if ($newUsername !== $oldUsername) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$newUsername]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already taken.']);
            exit();
        }
    }
    try {
        // Update main user table using user_id
        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?');
        $success = $stmt->execute([$newUsername, $email, $phone, $user_id]);
        if ($success) {
            // Update username in all related tables if username changed
            if ($newUsername !== $oldUsername) {
                $tables = ['payments', 'withdrawals', 'loans', 'investments', 'notifications'];
                foreach ($tables as $table) {
                    // Check if table has a username column before updating by username
                    $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
                    if ($table === 'notifications') {
                        // notifications: update 'user' column if it exists
                        if (in_array('user', $cols)) {
                            $stmt = $pdo->prepare("UPDATE $table SET user = ? WHERE user = ?");
                            $stmt->execute([$newUsername, $oldUsername]);
                        }
                    } else {
                        // Other tables: update 'username' column if it exists
                        if (in_array('username', $cols)) {
                            $stmt = $pdo->prepare("UPDATE $table SET username = ? WHERE username = ?");
                            $stmt->execute([$newUsername, $oldUsername]);
                        }
                        // Also update by user_id if both columns exist
                        if (in_array('user_id', $cols) && in_array('username', $cols)) {
                            $stmt = $pdo->prepare("UPDATE $table SET username = ? WHERE user_id = ?");
                            $stmt->execute([$newUsername, $user_id]);
                        }
                    }
                }
            }
            // Update session variables
            $_SESSION['username'] = $newUsername;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            // Also update user_id in session in case username changed
            $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt_id->execute([$newUsername]);
            $user_id = $stmt_id->fetchColumn();
            if ($user_id) {
                $_SESSION['user_id'] = $user_id;
            }
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Profile update failed.']);
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $msg]);
    }
    exit();
}

// Change Password (AJAX and normal)
if (isset($_POST['change_password']) || isset($_POST['ajax_change_password'])) {
    while (ob_get_level())
        ob_end_clean();
    if (!headers_sent())
        header('Content-Type: application/json');
    // Support both field names for compatibility
    $current = $_POST['current_password'] ?? $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($new) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit();
    }
    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }
    try {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($current, $row['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit();
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE username = ?');
        $stmt->execute([$hash, $username]);
        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}



// Handle AJAX payment request from dashboard.php
if (isset($_POST['ajax_make_payment']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();
    $amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
    $method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $note = isset($_POST['payment_note']) ? trim($_POST['payment_note']) : '';
    $name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    // Handle payment proof image upload
    $payment_proof_path = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file = $_FILES['payment_proof'];
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image type. Only JPG, PNG, GIF, and WEBP allowed.']);
            exit();
        }
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Image too large. Max 2MB allowed.']);
            exit();
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_ext = strtolower($ext);
        if (!in_array($safe_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid file extension.']);
            exit();
        }
        $upload_dir = __DIR__ . '/payment_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $basename = uniqid('pay_', true) . '.' . $safe_ext;
        $target = $upload_dir . $basename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save payment proof image.']);
            exit();
        }
        $payment_proof_path = 'payment_proofs/' . $basename;
    }
    // Validation
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User not found. Payment not saved.']);
        exit();
    }
    if ($amount < 100) {
        echo json_encode(['success' => false, 'message' => 'Minimum payment amount is ₦100.']);
        exit();
    }
    if (!$method) {
        echo json_encode(['success' => false, 'message' => 'Payment method is required.']);
        exit();
    }
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Account name is required.']);
        exit();
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid payee email.']);
        exit();
    }
    try {
        // Add payment_proof column if not exists
        $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(255) NULL");
        // Insert with all expected columns for compatibility
        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            method VARCHAR(50) NOT NULL,
            note TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            payee_email VARCHAR(100),
            name VARCHAR(100),
            email VARCHAR(100),
            payment_proof VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, method, note, status, created_at, name, email, payment_proof) VALUES (?, ?, ?, ?, 'pending', NOW(), ?, ?, ?)");
        $stmt->execute([$user_id, $amount, $method, $note, $name, $email, $payment_proof_path]);

        // Notify admin (email and admin_notifications table)
        $adminEmail = 'admin@example.com';
        $subject = "[Excel Investments] New Payment Submitted by $username";
        $proofLink = $payment_proof_path ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $payment_proof_path : 'No proof image uploaded.';
        $msg = "A new payment has been submitted.\n\nUser: $username\nAccount Name: $name\nEmail: $email\nAmount: ₦$amount\nMethod: $method\nNote: $note\n\n";
        if ($payment_proof_path) {
            $msg .= "Payment Proof: $proofLink\n";
        } else {
            $msg .= "Payment Proof: None\n";
        }
        $msg .= "\nPlease review and confirm in the admin panel.";
        @mail($adminEmail, $subject, $msg);

        // Store in admin_notifications table (with payment proof link if available)
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50),
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_read TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $adminMsg = sprintf(
            'New payment from %s: ₦%s, Method: %s, Account Name: %s, Email: %s. %s',
            $username,
            number_format($amount, 2),
            $method,
            $name,
            $email,
            $payment_proof_path ? ("Payment Proof: " . $proofLink) : "No payment proof uploaded."
        );
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (type, message) VALUES (?, ?)");
        $stmt->execute(['payment', $adminMsg]);

        echo json_encode(['success' => true, 'message' => 'Payment submitted successfully!']);
    } catch (Exception $e) {
        $errMsg = $e->getMessage();
        file_put_contents(__DIR__ . '/payment_error.log', date('Y-m-d H:i:s') . ' ' . $errMsg . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $errMsg]);
    }
    exit();
}

// Return user balance for AJAX request
if (isset($_GET['get_balance']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    try {
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $balance = $row && isset($row['balance']) ? $row['balance'] : 0;
        echo json_encode(['balance' => $balance]);
    } catch (Exception $e) {
        echo json_encode(['balance' => 0]);
    }
    exit();
}

// --- AJAX: Return user payment history for payment section table ---
if (isset($_GET['get_payments']) && isset($_SESSION['username'])) {
    file_put_contents(__DIR__ . '/dashboardserver.log', date('Y-m-d H:i:s') . " [ENTER] get_payments block, _GET: " . json_encode($_GET) . ", SESSION: " . json_encode($_SESSION) . "\n", FILE_APPEND);
    if (ob_get_level())
        ob_clean();
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();
    try {
        $stmt = $pdo->prepare('SELECT amount, email, reference, status, created_at FROM payments WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payments as &$p) {
            $p['amount'] = isset($p['amount']) ? floatval($p['amount']) : 0;
            $p['email'] = isset($p['email']) ? htmlspecialchars($p['email']) : '-';
            $p['reference'] = isset($p['reference']) ? htmlspecialchars($p['reference']) : '-';
            $p['status'] = isset($p['status']) ? htmlspecialchars(ucfirst(strtolower($p['status']))) : '-';
            $p['created_at'] = $p['created_at'] ? date('Y-m-d', strtotime($p['created_at'])) : '-';
        }
        // Debug log payments array
        file_put_contents(__DIR__ . '/dashboardserver.log', date('Y-m-d H:i:s') . " [GET_PAYMENTS] user_id=$user_id, payments=" . json_encode($payments) . "\n", FILE_APPEND);
        echo json_encode(['payments' => $payments]);
    } catch (Exception $e) {
        echo json_encode(['payments' => []]);
    }
    exit();
}

// --- AJAX: Loan Application Endpoint ---
if (isset($_POST['ajax_loan']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    $stmt_id = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();
    $loan_type = trim($_POST['loan_type'] ?? '');
    $amount = isset($_POST['loan_amount']) ? floatval($_POST['loan_amount']) : 0;
    $duration = isset($_POST['loan_duration']) ? intval($_POST['loan_duration']) : 0;
    $purpose = trim($_POST['loan_purpose'] ?? '');
    $id_type = trim($_POST['id_type'] ?? '');
    $id_value = trim($_POST['id_value'] ?? '');
    $errors = [];
    if (!$user_id)
        $errors[] = 'User not found.';
    if (!$loan_type)
        $errors[] = 'Loan type is required.';
    if ($amount < 1000)
        $errors[] = 'Minimum loan amount is ₦1,000.';
    if ($duration < 1 || $duration > 52)
        $errors[] = 'Duration must be between 1 and 52 weeks.';
    if (!$purpose)
        $errors[] = 'Loan purpose is required.';
    if (!$id_type || !in_array($id_type, ['BVN', 'NIN']))
        $errors[] = 'Valid ID type is required.';
    if (!$id_value || strlen($id_value) < 6)
        $errors[] = 'Valid BVN or NIN is required.';
    if ($errors) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit();
    }
    // Calculate interest and total repayment (5% per week)
    $interest = $amount * 0.05 * $duration;
    $total_repayable = $amount + $interest;
    $status = 'pending';
    $created_at = date('Y-m-d H:i:s');
    $due_date = date('Y-m-d', strtotime("+$duration week"));
    try {
        $stmt = $pdo->prepare("INSERT INTO loans (user_id, type, amount, interest, total_repayment, duration, purpose, id_type, id_value, status, created_at, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $loan_type, $amount, $interest, $total_repayable, $duration, $purpose, $id_type, $id_value, $status, $created_at, $due_date]);
        echo json_encode(['success' => true, 'message' => 'Loan application submitted successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// --- Removed duplicate AJAX: Loan Repayment Endpoint ---

if (!headers_sent()) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}
