<?php
// Add this to dashboardserver.php
if (isset($_GET['get_breakdown']) && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $username = $_SESSION['username'];
    // Example: fetch totals from DB (replace with your queries)
    $totalPayments = 120000; // fetch from payments table
    $totalInvestments = 80000; // fetch from investments table
    $totalLoans = 20000; // fetch from loans table
    $totalWithdrawals = 10000; // fetch from withdrawals table
    $balance = $totalPayments - $totalInvestments - $totalLoans - $totalWithdrawals;
    $breakdown = [
        'Payments' => $totalPayments,
        'Investments' => $totalInvestments,
        'Loans' => $totalLoans,
        'Withdrawals' => $totalWithdrawals,
        'Balance' => $balance
    ];
    $totalForPercent = $totalPayments + $totalInvestments + $totalLoans + $totalWithdrawals + $balance;
    $percent = [];
    foreach ($breakdown as $key => $val) {
        $percent[$key] = $totalForPercent > 0 ? ($val / $totalForPercent * 100) : 0;
    }
    echo json_encode([
        'success' => true,
        'breakdown' => $breakdown,
        'percent' => $percent
    ]);
    exit();
}
?>
