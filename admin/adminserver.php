<?php
// --- ADMIN NOTIFICATIONS ENDPOINT ---
if (isset($_GET['action']) && $_GET['action'] === 'admin_notifications') {
    ini_set('display_errors', 0);
    error_reporting(0);
    require_once __DIR__ . '/../db.php';
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50),
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $notifications = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['notifications' => $notifications]);
    exit();
}
session_start();
require_once __DIR__ . '/../db.php';
// If action=all_withdrawals, return all withdrawals for admin dashboard
if (isset($_GET['action']) && $_GET['action'] === 'all_withdrawals') {
    ini_set('display_errors', 0);
    error_reporting(0);
    $stmt = $pdo->query("SELECT withdrawals.*, users.username FROM withdrawals LEFT JOIN users ON withdrawals.user_id = users.id ORDER BY withdrawals.created_at DESC");
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($withdrawals as &$wd) {
        if (!isset($wd['username'])) $wd['username'] = '';
    }
    header('Content-Type: application/json');
    echo json_encode(['withdrawals' => $withdrawals]);
    exit();
}
// If action=pending_withdrawals, return only pending withdrawals for admin confirmation table
if (isset($_GET['action']) && $_GET['action'] === 'pending_withdrawals') {
    $stmt = $pdo->query("SELECT * FROM withdrawals WHERE status='pending' ORDER BY created_at DESC");
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['withdrawals' => $withdrawals]);
    exit();
}
header('Content-Type: application/json');

// Admin: Get user balance breakdown (same logic as user dashboard)
if (isset($_GET['get_user_balance_breakdown']) && isset($_GET['username'])) {
    $username = $_GET['username'];
    $result = [
        'success' => false,
        'payments' => 0,
        'matured_investments' => 0,
        'accrued_interest' => 0,
        'outstanding_loans' => 0,
        'withdrawals' => 0,
        'total' => 0
    ];
    // Get user id
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode($result); exit;
    }
    $user_id = $user['id'];
    // 1. Confirmed Payments
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'confirmed'");
    $stmt->execute([$user_id]);
    $result['payments'] = floatval($stmt->fetchColumn());
    // 2. Matured Investments (principal + interest for completed investments)
    $stmt = $pdo->prepare("SELECT amount, interest_rate FROM investments WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $matured = 0;
    while ($inv = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $principal = floatval($inv['amount']);
        $rate = floatval($inv['interest_rate']);
        $weeks = 4; // Always 1 month = 4 weeks
        $interest = $principal * $rate * $weeks / 100;
        $matured += $principal + $interest;
    }
    $result['matured_investments'] = $matured;
    // 3. Accrued Interest (active investments, up to today, max 4 weeks)
    $stmt = $pdo->prepare("SELECT amount, interest_rate, created_at FROM investments WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $accrued = 0;
    $now = new DateTime();
    while ($inv = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $start = new DateTime($inv['created_at']);
        $weeks = min(4, max(0, $start->diff($now)->days / 7)); // Max 4 weeks
        $interest = floatval($inv['amount']) * floatval($inv['interest_rate']) * $weeks / 100;
        $accrued += $interest;
    }
    $result['accrued_interest'] = $accrued;
    // 4. Outstanding Loans (principal + accrued interest, unpaid)
    $stmt = $pdo->prepare("SELECT amount, interest_rate, created_at, duration FROM loans WHERE user_id = ? AND status IN ('active','pending')");
    $stmt->execute([$user_id]);
    $outstanding = 0;
    while ($loan = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $start = new DateTime($loan['created_at']);
        $weeks = min(floatval($loan['duration']), max(0, $start->diff($now)->days / 7));
        $interest = floatval($loan['amount']) * floatval($loan['interest_rate']) * $weeks / 100;
        $outstanding += floatval($loan['amount']) + $interest;
    }
    $result['outstanding_loans'] = $outstanding;
    // 5. Withdrawals (completed)
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
    echo json_encode($result); exit;
}


// Auth check
if (!isset($_SESSION['user']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Return totals for overview cards
if (isset($_GET['get_totals'])) {
    $totals = [
        'total_payments' => 0,
        'total_withdrawals' => 0
    ];
    // Total confirmed payments
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status='confirmed'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['total'] !== null) {
        $totals['total_payments'] = (float)$row['total'];
    }
    // Total confirmed withdrawals
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM withdrawals WHERE status='confirmed'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['total'] !== null) {
        $totals['total_withdrawals'] = (float)$row['total'];
    }
    echo json_encode($totals);
    exit();
}

// If action=all_loans, return all loans for admin loans table
if (isset($_GET['action']) && $_GET['action'] === 'all_loans') {
    ini_set('display_errors', 0);
    error_reporting(0);
    $stmt = $pdo->query("SELECT loans.*, users.username FROM loans LEFT JOIN users ON loans.user_id = users.id ORDER BY loans.created_at DESC");
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($loans as &$loan) {
        if (!isset($loan['username'])) $loan['username'] = '';
    }
    header('Content-Type: application/json');
    echo json_encode(['loans' => $loans]);
    exit();
}

// Admin: Approve/Reject/Mark Loan as Paid
if (isset($_POST['admin_loan_action']) && isset($_POST['loan_id']) && isset($_SESSION['user'])) {
    ob_start();
    try {
        $loan_id = intval($_POST['loan_id']);
        $action = $_POST['admin_loan_action'];
        $admin = $_SESSION['user'];
        $allowed = ['approve','reject','mark_paid'];
        header('Content-Type: application/json');
        if (!in_array($action, $allowed)) {
            echo json_encode(['success'=>false,'message'=>'Invalid action.']); exit();
        }
        $status = $action === 'approve' ? 'active' : ($action === 'reject' ? 'rejected' : 'paid');
        // Check if admin_action_by, admin_action_at, and processed_at columns exist in loans table
        $pdo->exec("ALTER TABLE loans ADD COLUMN IF NOT EXISTS admin_action_by VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS admin_action_at DATETIME NULL, ADD COLUMN IF NOT EXISTS processed_at DATETIME NULL");
        $stmt = $pdo->prepare("UPDATE loans SET status=?, admin_action_by=?, admin_action_at=NOW(), processed_at=NOW() WHERE id=?");
        $stmt->execute([$status, $admin, $loan_id]);
        // Audit log
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit (id INT AUTO_INCREMENT PRIMARY KEY, admin VARCHAR(100), action VARCHAR(50), target_type VARCHAR(20), target_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $log = $pdo->prepare("INSERT INTO admin_audit (admin, action, target_type, target_id) VALUES (?, ?, 'loan', ?)");
        $log->execute([$admin, $action, $loan_id]);
        // Notify user (optional: email or notification table)
        ob_end_clean();
        echo json_encode(['success'=>true]);
        exit();
    } catch (Throwable $e) {
        ob_end_clean();
        file_put_contents(__DIR__ . '/admin_loan_error.log', date('Y-m-d H:i:s') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>'Server error','details'=>'Check admin_loan_error.log for details.']);
        exit();
    }
}

// If action=all_investments, return all investments for admin investments table
if (isset($_GET['action']) && $_GET['action'] === 'all_investments') {
    ini_set('display_errors', 0);
    error_reporting(0);
    $stmt = $pdo->query("SELECT investments.*, users.username FROM investments LEFT JOIN users ON investments.user_id = users.id ORDER BY investments.created_at DESC");
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($investments as &$inv) {
        if (!isset($inv['username'])) $inv['username'] = '';
    }
    header('Content-Type: application/json');
    echo json_encode(['investments' => $investments]);
    exit();
}

// Admin: Approve/Reject/Mature Investment
if (isset($_POST['admin_invest_action']) && isset($_POST['invest_id']) && isset($_SESSION['user'])) {
    $invest_id = intval($_POST['invest_id']);
    $action = $_POST['admin_invest_action'];
    $admin = $_SESSION['user'];
    $allowed = ['approve','reject','mature'];
    if (!in_array($action, $allowed)) {
        echo json_encode(['success'=>false,'message'=>'Invalid action.']); exit();
    }
    $status = $action === 'approve' ? 'active' : ($action === 'reject' ? 'rejected' : 'completed');
    $stmt = $pdo->prepare("UPDATE investments SET status=?, admin_action_by=?, admin_action_at=NOW() WHERE id=?");
    $stmt->execute([$status, $admin, $invest_id]);
    // Audit log
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit (id INT AUTO_INCREMENT PRIMARY KEY, admin VARCHAR(100), action VARCHAR(50), target_type VARCHAR(20), target_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $log = $pdo->prepare("INSERT INTO admin_audit (admin, action, target_type, target_id) VALUES (?, ?, 'investment', ?)");
    $log->execute([$admin, $action, $invest_id]);
    // Notify user (optional: email or notification table)
    echo json_encode(['success'=>true]); exit();
}

// If action=all_payments, return all payments for payments overview
if (isset($_GET['action']) && $_GET['action'] === 'all_payments') {
    ini_set('display_errors', 0);
    error_reporting(0);
    $stmt = $pdo->query("SELECT payments.*, users.username FROM payments LEFT JOIN users ON payments.user_id = users.id ORDER BY payments.id ASC");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($payments as &$pay) {
        if (!isset($pay['username'])) $pay['username'] = '';
    }
    header('Content-Type: application/json');
    echo json_encode(['payments' => $payments]);
    exit();
}

// Dedicated details endpoints for modal logic in admindashboard.php
if (isset($_GET['action']) && $_GET['action'] === 'investment_detail' && isset($_GET['id'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT investments.*, users.username FROM investments LEFT JOIN users ON investments.user_id = users.id WHERE investments.id=?");
    $stmt->execute([$id]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($inv && !isset($inv['username'])) $inv['username'] = '';
    header('Content-Type: application/json');
    echo json_encode($inv ? $inv : new stdClass());
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'loan_detail' && isset($_GET['id'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT loans.*, users.username FROM loans LEFT JOIN users ON loans.user_id = users.id WHERE loans.id=?");
    $stmt->execute([$id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($loan && !isset($loan['username'])) $loan['username'] = '';
    header('Content-Type: application/json');
    echo json_encode($loan ? $loan : new stdClass());
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'payment_detail' && isset($_GET['id'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT payments.*, users.username FROM payments LEFT JOIN users ON payments.user_id = users.id WHERE payments.id=?");
    $stmt->execute([$id]);
    $pay = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pay && !isset($pay['username'])) $pay['username'] = '';
    header('Content-Type: application/json');
    echo json_encode($pay ? $pay : new stdClass());
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'withdrawal_detail' && isset($_GET['id'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT withdrawals.*, users.username FROM withdrawals LEFT JOIN users ON withdrawals.user_id = users.id WHERE withdrawals.id=?");
    $stmt->execute([$id]);
    $wd = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($wd && !isset($wd['username'])) $wd['username'] = '';
    header('Content-Type: application/json');
    echo json_encode($wd ? $wd : new stdClass());
    exit();
}

// Admin: Approve/Reject Payment
if (isset($_POST['admin_payment_action']) && isset($_POST['payment_id']) && isset($_SESSION['user'])) {
    $payment_id = intval($_POST['payment_id']);
    $action = $_POST['admin_payment_action'];
    $admin = $_SESSION['user'];
    $allowed = ['approve','reject'];
    if (!in_array($action, $allowed)) {
        echo json_encode(['success'=>false,'message'=>'Invalid action.']); exit();
    }
    $status = $action === 'approve' ? 'confirmed' : 'rejected';
    $stmt = $pdo->prepare("UPDATE payments SET status=?, admin_action_by=?, admin_action_at=NOW() WHERE id=?");
    $stmt->execute([$status, $admin, $payment_id]);
    // Audit log
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit (id INT AUTO_INCREMENT PRIMARY KEY, admin VARCHAR(100), action VARCHAR(50), target_type VARCHAR(20), target_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $log = $pdo->prepare("INSERT INTO admin_audit (admin, action, target_type, target_id) VALUES (?, ?, 'payment', ?)");
    $log->execute([$admin, $action, $payment_id]);
    // Notify user (optional: email or notification table)
    echo json_encode(['success'=>true]); exit();
}
// Admin: Get details for user, investment, or loan
if (isset($_GET['detail_type']) && isset($_GET['detail_id'])) {
    $type = $_GET['detail_type'];
    $id = intval($_GET['detail_id']);
    if ($type === 'user') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['user'=>$user]); exit();
    } elseif ($type === 'investment') {
        $stmt = $pdo->prepare("SELECT * FROM investments WHERE id=?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['investment'=>$inv]); exit();
    } elseif ($type === 'loan') {
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE id=?");
        $stmt->execute([$id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['loan'=>$loan]); exit();
    }
}

// Admin: Export table to CSV
if (isset($_GET['export']) && in_array($_GET['export'], ['users','investments','loans','payments'])) {
    $type = $_GET['export'];
    $filename = $type.'_'.date('Ymd_His').'.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    if ($type === 'users') {
        $stmt = $pdo->query("SELECT * FROM users");
        fputcsv($out, array_keys($stmt->fetch(PDO::FETCH_ASSOC)));
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $row);
    } elseif ($type === 'investments') {
        $stmt = $pdo->query("SELECT * FROM investments");
        fputcsv($out, array_keys($stmt->fetch(PDO::FETCH_ASSOC)));
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $row);
    } elseif ($type === 'loans') {
        $stmt = $pdo->query("SELECT * FROM loans");
        fputcsv($out, array_keys($stmt->fetch(PDO::FETCH_ASSOC)));
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $row);
    } elseif ($type === 'payments') {
        $stmt = $pdo->query("SELECT * FROM payments");
        fputcsv($out, array_keys($stmt->fetch(PDO::FETCH_ASSOC)));
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $row);
    }
    fclose($out); exit();
}

// Default: return totals for dashboard cards (legacy fallback)
$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status='confirmed'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_payments = $row && $row['total'] !== null ? $row['total'] : 0;

$stmt2 = $pdo->query("SELECT SUM(amount) as total FROM withdrawals WHERE status='approved' OR status='confirmed'");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
$total_withdrawals = $row2 && $row2['total'] !== null ? $row2['total'] : 0;

// Moved to end of file to prevent extra output after JSON responses

// Add this to adminserver.php to handle Mark Matured investment action
if (isset($_POST['admin_investment_action'], $_POST['investment_id']) && $_POST['admin_investment_action'] === 'mature' && is_numeric($_POST['investment_id'])) {
    $invId = intval($_POST['investment_id']);
    // Update investment status to matured
    $stmt = $pdo->prepare("UPDATE investments SET status='matured', matured_at=NOW() WHERE id=? AND status='active'");
    $stmt->execute([$invId]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unable to mark as matured.']);
    }
    exit();
}
if (isset($_POST['admin_withdrawal_action'], $_POST['withdrawal_id']) && in_array($_POST['admin_withdrawal_action'], ['approve', 'reject']) && is_numeric($_POST['withdrawal_id'])) {
    $wid = intval($_POST['withdrawal_id']);
    $action = $_POST['admin_withdrawal_action'];
    // Use 'approved' for consistency with dashboard logic
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?");
    $success = $stmt->execute([$status, $wid]);
    if ($success) {
        // Optionally update user balance here if needed
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Fallback: always return valid JSON if no endpoint matched
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid or missing endpoint/action.']);
exit();
