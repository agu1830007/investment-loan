<?php
// ad.php - Admin dashboard summary data for JS fetch()
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

try {
    // Total users
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    // Total investments (sum of confirmed/active)
    $total_investments = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM investments WHERE status IN ('active','confirmed')")->fetchColumn();
    // Total loans (sum of confirmed/active)
    $total_loans = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM loans WHERE status IN ('active','confirmed')")->fetchColumn();
    // Total payments (sum of confirmed)
    $total_payments = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='confirmed'")->fetchColumn();
    // Total withdrawals (sum of confirmed/active)
    $total_withdrawals = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status IN ('approved')")->fetchColumn();

    echo json_encode([
        'total_users' => (int)$total_users,
        'total_investments' => (float)$total_investments,
        'total_loans' => (float)$total_loans,
        'total_payments' => (float)$total_payments,
        'total_withdrawals' => (float)$total_withdrawals
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
