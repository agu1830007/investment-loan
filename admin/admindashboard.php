<?php
// Suppress errors for AJAX endpoints and AJAX POST admin_action
if (isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'], $_POST['type'], $_POST['id']))) {
  ini_set('display_errors', 0);
  error_reporting(0);

  // --- AJAX ENDPOINTS: MUST BE BEFORE ANY HTML OUTPUT ---
  require_once __DIR__ . '/../db.php';
  switch ($_GET['action']) {
    case 'admin_notifications':
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
    case 'all_payments':
      $payments = $pdo->query("SELECT payments.*, users.username FROM payments LEFT JOIN users ON payments.user_id = users.id ORDER BY payments.id ASC")->fetchAll(PDO::FETCH_ASSOC);
      foreach ($payments as &$pay) {
        if (!isset($pay['username'])) $pay['username'] = '';
      }
      header('Content-Type: application/json');
      echo json_encode(['payments' => $payments]);
      exit();
    case 'all_loans':
      // Show loans oldest first (by id as fallback if created_at is null)
      $loans = $pdo->query("SELECT loans.*, users.username FROM loans LEFT JOIN users ON loans.user_id = users.id ORDER BY COALESCE(loans.created_at, loans.id) ASC")->fetchAll(PDO::FETCH_ASSOC);
      // Debug log: number of loans, their statuses, and DB info
      $dbinfo = '';
      try {
        $attr = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        $dbname = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $dbinfo = " | DB: $dbname | Host: $attr";
      } catch (Exception $e) { $dbinfo = ' | DB info unavailable'; }
      $log = date('Y-m-d H:i:s') . $dbinfo . " | Loans fetched: " . count($loans);
      foreach ($loans as $l) {
        $log .= " | ID: {$l['id']} Status: {$l['status']}";
      }
      file_put_contents(__DIR__ . '/admin_debug.log', $log . "\n", FILE_APPEND);
      header('Content-Type: application/json');
      echo json_encode(['loans' => $loans]);
      exit();
    case 'all_investments':
      // Show investments oldest first (by id as fallback if created_at is null)
      $investments = $pdo->query("SELECT investments.*, users.username FROM investments LEFT JOIN users ON investments.user_id = users.id ORDER BY COALESCE(investments.created_at, investments.id) ASC")->fetchAll(PDO::FETCH_ASSOC);
      header('Content-Type: application/json');
      echo json_encode(['investments' => $investments]);
      exit();
    case 'all_withdrawals':
      $withdrawals = $pdo->query("SELECT withdrawals.*, users.username FROM withdrawals LEFT JOIN users ON withdrawals.user_id = users.id ORDER BY withdrawals.id ASC")->fetchAll(PDO::FETCH_ASSOC);
      header('Content-Type: application/json');
      echo json_encode(['withdrawals' => $withdrawals]);
      exit();
    case 'loan_overview':
      $row = $pdo->query("SELECT SUM(amount) AS total FROM loans WHERE status IN ('active','confirmed')")->fetch(PDO::FETCH_ASSOC);
      $total = isset($row['total']) ? (float)$row['total'] : 0.0;
      header('Content-Type: application/json');
      echo json_encode(['loan_overview' => $total]);
      exit();
    case 'loan_detail':
      $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      $loan = $pdo->prepare("SELECT loans.*, users.username FROM loans LEFT JOIN users ON loans.user_id = users.id WHERE loans.id=?");
      $loan->execute([$id]);
      $data = $loan->fetch(PDO::FETCH_ASSOC);
      if ($data && !isset($data['username'])) $data['username'] = '';
      header('Content-Type: application/json');
      echo json_encode($data ?: new stdClass());
      exit();
    case 'payment_detail':
      $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      $pay = $pdo->prepare("SELECT payments.*, users.username FROM payments LEFT JOIN users ON payments.user_id = users.id WHERE payments.id=?");
      $pay->execute([$id]);
      $data = $pay->fetch(PDO::FETCH_ASSOC);
      if ($data && !isset($data['username'])) $data['username'] = '';
      header('Content-Type: application/json');
      echo json_encode($data ?: new stdClass());
      exit();
    case 'withdrawal_detail':
      $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      $wd = $pdo->prepare("SELECT withdrawals.*, users.username FROM withdrawals LEFT JOIN users ON withdrawals.user_id = users.id WHERE withdrawals.id=?");
      $wd->execute([$id]);
      $data = $wd->fetch(PDO::FETCH_ASSOC);
      if ($data && !isset($data['username'])) $data['username'] = '';
      header('Content-Type: application/json');
      echo json_encode($data ?: new stdClass());
      exit();
    case 'investment_detail':
      $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      $inv = $pdo->prepare("SELECT investments.*, users.username FROM investments LEFT JOIN users ON investments.user_id = users.id WHERE investments.id=?");
      $inv->execute([$id]);
      $data = $inv->fetch(PDO::FETCH_ASSOC);
      if ($data && !isset($data['username'])) $data['username'] = '';
      header('Content-Type: application/json');
      echo json_encode($data ?: new stdClass());
      exit();
  }
  // --- END AJAX ENDPOINTS ---
  // Handle admin investment actions (approve, reject, mature)
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'], $_POST['type'], $_POST['id'])) {
    header('Content-Type: application/json');
    $type = $_POST['type'];
    $action = $_POST['admin_action'];
    $id = intval($_POST['id']);
    $response = ['success' => false];
    try {
        if ($type === 'investment') {
            if ($action === 'approve') {
                $stmt = $pdo->prepare('UPDATE investments SET status = "active" WHERE id = ?');
                $stmt->execute([$id]);
                $response['success'] = true;
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare('UPDATE investments SET status = "rejected" WHERE id = ?');
                $stmt->execute([$id]);
                $response['success'] = true;
            } elseif ($action === 'mature') {
                $stmt = $pdo->prepare('UPDATE investments SET status = "matured" WHERE id = ?');
                $stmt->execute([$id]);
                $response['success'] = true;
            }
        } else if ($type === 'payment') {
            if ($action === 'approve') {
                $stmt = $pdo->prepare('UPDATE payments SET status = "confirmed" WHERE id = ?');
                if ($stmt->execute([$id])) {
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Failed to update payment status.';
                }
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare('UPDATE payments SET status = "rejected" WHERE id = ?');
                if ($stmt->execute([$id])) {
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Failed to update payment status.';
                }
            }
        }
        // You can add more types here (e.g., loans, withdrawals)
        else if ($type === 'withdrawal') {
            if ($action === 'approve') {
                $stmt = $pdo->prepare('UPDATE withdrawals SET status = "confirmed", processed_at = NOW() WHERE id = ?');
                $stmt->execute([$id]);
                $response['success'] = true;
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare('UPDATE withdrawals SET status = "rejected", processed_at = NOW() WHERE id = ?');
                $stmt->execute([$id]);
                $response['success'] = true;
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit();
  }
}

// Session logic and rest of PHP code below
// ...existing code...
?>
<script>
  // Wait for fetchAdminUsers to be defined before setting up nav tab switching
  function setupDashboardNavTabs() {
    const navLinks = document.querySelectorAll('.dashboard-nav .nav-link');
    const sections = document.querySelectorAll('.dashboard-section');
    navLinks.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        // Remove active from all links
        navLinks.forEach(l => l.classList.remove('active'));
        // Hide all sections
        sections.forEach(s => s.classList.remove('active'));
        // Activate clicked link
        this.classList.add('active');
        // Show corresponding section
        const target = this.getAttribute('href').replace('#', '');
        const section = document.getElementById(target);
        if (section) section.classList.add('active');
        // If switching to Users tab, fetch users
        if (target === 'users' && typeof fetchAdminUsers === 'function') {
          fetchAdminUsers();
        }
      });
    });
    // If Users section is active on page load, fetch users
    const usersSection = document.getElementById('users');
    if (usersSection && usersSection.classList.contains('active') && typeof fetchAdminUsers === 'function') {
      fetchAdminUsers();
    }
  }
  // Wait for DOMContentLoaded and fetchAdminUsers to be defined
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof fetchAdminUsers === 'function') {
      setupDashboardNavTabs();
    } else {
      // Try again after a short delay
      setTimeout(setupDashboardNavTabs, 200);
    }
  });
</script>

<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
  header('Location: /Investment/dashboard.php');
  exit();
}
require_once __DIR__ . '/../db.php';
// --- BALANCE UPDATE LOGIC ---
function update_user_balance($pdo, $username)
{
  // Get user id from username
  $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!isset($user['id'])) {
    return;
  }
  $user_id = $user['id'];
  // Calculate total payments (confirmed)
  $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id=? AND (status='confirmed' OR status='approved' OR status='completed' OR status='pending')");
  $stmt->execute([$user_id]);
  $total_payments = (float) ($stmt->fetchColumn() ?: 0);
  // Calculate total investments (active or confirmed)
  $stmt = $pdo->prepare("SELECT SUM(amount) FROM investments WHERE user_id=? AND (status='active' OR status='confirmed')");
  $stmt->execute([$user_id]);
  $total_investments = (float) ($stmt->fetchColumn() ?: 0);
  // Calculate total loans (active or confirmed)
  $stmt = $pdo->prepare("SELECT SUM(amount) FROM loans WHERE user_id=? AND (status='active' OR status='confirmed')");
  $stmt->execute([$user_id]);
  $total_loans = (float) ($stmt->fetchColumn() ?: 0);
  // Calculate total withdrawals (confirmed)
  $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id=? AND (status='confirmed' OR status='approved' OR status='completed')");
  $stmt->execute([$user_id]);
  $total_withdrawals = (float) ($stmt->fetchColumn() ?: 0);
  // User balance = payments + loans - investments - withdrawals
  $balance = $total_payments + $total_loans - $total_investments - $total_withdrawals;
  // Ensure users table has a balance column
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS balance DECIMAL(12,2) DEFAULT 0");
  // Update user's balance
  $update = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
  $update->execute([$balance, $user_id]);
}

// Confirm payment logic
if (isset($_GET['confirm']) && is_numeric($_GET['confirm'])) {
  $paymentId = intval($_GET['confirm']);
  // Confirm the payment
  $stmt = $pdo->prepare("UPDATE payments SET status='confirmed' WHERE id=?");
  $stmt->execute([$paymentId]);

  // Get payment details to update user balance
  $pay = $pdo->prepare("SELECT users.username FROM payments LEFT JOIN users ON payments.user_id = users.id WHERE payments.id=?");
  $pay->execute([$paymentId]);
  $payment = $pay->fetch(PDO::FETCH_ASSOC);
  if ($payment && isset($payment['username'])) {
    update_user_balance($pdo, $payment['username']);
  }
  header('Location: admindashboard.php?success=1');
  exit();
}

// Reject payment logic
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
  $paymentId = intval($_GET['reject']);
  // Reject the payment
  $stmt = $pdo->prepare("UPDATE payments SET status='rejected' WHERE id=?");
  $stmt->execute([$paymentId]);
  header('Location: admindashboard.php?rejected=1');
  exit();
}
// --- END BALANCE UPDATE LOGIC ---
// Fetch pending payments
$pending = $pdo->query("SELECT payments.*, users.username FROM payments LEFT JOIN users ON payments.user_id = users.id WHERE payments.status='pending' ORDER BY payments.id ASC")->fetchAll(PDO::FETCH_ASSOC);
// Ensure username key always exists
foreach ($pending as &$pay) {
  if (!isset($pay['username'])) $pay['username'] = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="admin.css">
</head>

<body>
  <div class="dashboard-container">
    <nav class="dashboard-nav">
      <ul>
        <li><a href="#overview" class="nav-link active">Overview</a></li>
        <li><a href="#users" class="nav-link">Users</a></li>
                <li><a href="#payments" class="nav-link">Deposits</a></li>
        <li><a href="#investments" class="nav-link">Investments</a></li>
        <li><a href="#loans" class="nav-link">Loans</a></li>
        <li><a href="#withdrawals" class="nav-link">Withdrawals</a></li>
        
        <li><a href="logout.php" class="nav-link">Logout</a></li>
      </ul>
      <!-- Withdrawals section moved to <main> below for proper layout -->
      <?php
      // Handle withdrawal admin actions (approve/reject)
      if (isset($_GET['withdrawal_action'], $_GET['id']) && in_array($_GET['withdrawal_action'], ['approve', 'reject']) && is_numeric($_GET['id'])) {
        $wid = intval($_GET['id']);
        $action = $_GET['withdrawal_action'];
        $status = $action === 'approve' ? 'confirmed' : 'rejected';
        $stmt = $pdo->prepare("UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?");
        $stmt->execute([$status, $wid]);
        // Optionally update user balance here if needed
        header('Location: admindashboard.php#withdrawals');
        exit();
      }
      ?>
    </nav>
    <main class="dashboard-main">
      <section class="dashboard-section" id="withdrawals">
        <div class="withdrawals-card"
          style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.07); padding:2rem; margin-bottom:2rem; max-width:1000px; margin-left:auto; margin-right:auto;">
          <h2 style="margin-top:0; color:#2d3e50;">Withdrawals Overview</h2>
          <div id="withdrawal-confirmation"></div>
          <div style="overflow-x:auto;">
            <table class="dashboard-table withdrawals-table" style="width:100%; border-collapse:collapse;">
              <thead style="background:#f7f7fa;">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Amount</th>
                  <th>Account Number</th>
                  <th>Bank Name</th>
                  <th>Account Name</th>
                  <th>Status</th>
                  <th>Note</th>
                  <th>Requested</th>
                  <th>Processed</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="withdrawals-body"></tbody>
            </table>
          </div>
          <button onclick="exportTableCSV('withdrawals-body')"
            style="float:right; margin-top:1rem; background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.5rem 1.2rem; font-weight:600; cursor:pointer;">Export
            CSV</button>
        </div>
        <div class="pending-withdrawals-card"
          style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.07); padding:2rem; margin-bottom:2rem; max-width:1000px; margin-left:auto; margin-right:auto;">
          <h3 style="margin-top:0; color:#2d3e50; font-size:1.4rem;">Pending Withdrawals - Admin Confirmation</h3>
          <div style="overflow-x:auto;">
            <table class="dashboard-table pending-table" style="width:100%; border-collapse:collapse;">
              <thead style="background:#f7f7fa;">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Amount</th>
                  <th>Account Number</th>
                  <th>Bank Name</th>
                  <th>Account Name</th>
                  <th>Note</th>
                  <th>Requested</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="pending-withdrawals-body"></tbody>
            </table>
          </div>
        </div>
        <script>
          // Fetch and display all withdrawals for admin (styled like other sections)
          function fetchAdminWithdrawals() {
            fetch('adminserver.php?action=all_withdrawals', { credentials: 'include' })
              .then(response => {
                // Parse raw response
                return response.text().then(text => {
                  try {
                    const data = JSON.parse(text);
                    return data;
                  } catch (e) {
                    console.error('Withdrawals response not valid JSON:', text);
                    throw new Error('Invalid JSON: ' + text);
                  }
                });
              })
              .then(data => {
                const wBody = document.getElementById('withdrawals-body');
                const pendingBody = document.getElementById('pending-withdrawals-body');
                wBody.innerHTML = '';
                pendingBody.innerHTML = '';
                if (Array.isArray(data.withdrawals) && data.withdrawals.length > 0) {
                  // Sort withdrawals by id ascending before rendering
                  data.withdrawals.sort(function(a, b) { return a.id - b.id; });
                  data.withdrawals.forEach(wd => {
                    const tr = document.createElement('tr');
                    let wActions = '<button class="btn-details" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.5rem; cursor:pointer; font-weight:500;" onclick="showDetail(\'withdrawal\',' + wd.id + ')">Details</button>';
                    if (wd.status === 'pending') {
                      wActions += ' <button class="btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;" onclick="adminWithdrawalAction(' + wd.id + ',\'approve\')">Approve</button>';
                      wActions += ' <button class="btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;" onclick="adminWithdrawalAction(' + wd.id + ',\'reject\')">Reject</button>';
                    }
                    let statusColor = '#888';
                    if (wd.status === 'pending') statusColor = '#e67e22';
                    else if (wd.status === 'approved') statusColor = '#27ae60';
                    else if (wd.status === 'rejected') statusColor = '#e74c3c';
                    tr.innerHTML =
                      '<td>' + (wd.id ? wd.id : '') + '</td>' +
                      '<td>' + (wd.username ? wd.username : '') + '</td>' +
                      '<td>₦' + Number(wd.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                      '<td>' + (wd.account_number ? wd.account_number : '') + '</td>' +
                      '<td>' + (wd.bank_name ? wd.bank_name : '') + '</td>' +
                      '<td>' + (wd.account_name ? wd.account_name : '') + '</td>' +
                      '<td><span style="font-weight:600; color:' + statusColor + '">' + (wd.status ? wd.status.charAt(0).toUpperCase() + wd.status.slice(1) : '') + '</span></td>' +
                      '<td>' + (wd.note ? wd.note.replace(/\n/g, '<br>') : '') + '</td>' +
                      '<td>' + (wd.created_at ? wd.created_at : '') + '</td>' +
                      '<td>' + (wd.processed_at ? wd.processed_at : '') + '</td>' +
                      '<td>' + wActions + '</td>';
                    wBody.appendChild(tr);
                  });
                  // Pending withdrawals table
                  let pendingWithdrawals = data.withdrawals.filter(wd => wd.status === 'pending');
                  if (pendingWithdrawals.length > 0) {
                    pendingWithdrawals.forEach(wd => {
                      const tr2 = document.createElement('tr');
                      let actions = '<button class="btn-details" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.5rem; cursor:pointer; font-weight:500;" onclick="showDetail(\'withdrawal\',' + wd.id + ')">Details</button>';
                      actions += ' <button class="btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;" onclick="adminWithdrawalAction(' + wd.id + ',\'approve\')">Approve</button>';
                      actions += ' <button class="btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;" onclick="adminWithdrawalAction(' + wd.id + ',\'reject\')">Reject</button>';
                      tr2.innerHTML =
                        '<td>' + (wd.id ? wd.id : '') + '</td>' +
                        '<td>' + (wd.username ? wd.username : '') + '</td>' +
                        '<td>₦' + Number(wd.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                        '<td>' + (wd.account_number ? wd.account_number : '') + '</td>' +
                        '<td>' + (wd.bank_name ? wd.bank_name : '') + '</td>' +
                        '<td>' + (wd.account_name ? wd.account_name : '') + '</td>' +
                        '<td>' + (wd.note ? wd.note.replace(/\n/g, '<br>') : '') + '</td>' +
                        '<td>' + (wd.created_at ? wd.created_at : '') + '</td>' +
                        '<td>' + actions + '</td>';
                      pendingBody.appendChild(tr2);
                    });
                  } else {
                    pendingBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No pending withdrawals.</td></tr>';
                  }
                } else {
                  wBody.innerHTML = '<tr><td colspan="11" style="text-align:center;">No withdrawals found.</td></tr>';
                  pendingBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No pending withdrawals.</td></tr>';
                }
              })
              .catch(err => {
                // Only show red error if AJAX/network/server fails
                const wBody = document.getElementById('withdrawals-body');
                const pendingBody = document.getElementById('pending-withdrawals-body');
                if (wBody) wBody.innerHTML = '<tr><td colspan="11" style="text-align:center; color:red;">Failed to load withdrawals (server error).</td></tr>';
                if (pendingBody) pendingBody.innerHTML = '<tr><td colspan="9" style="text-align:center; color:red;">Failed to load pending withdrawals (server error).</td></tr>';
                console.error('Failed to fetch withdrawals:', err);
              });
          }

          // AJAX withdrawal approval/rejection
          function adminWithdrawalAction(withdrawalId, action) {
            if (!confirm('Are you sure you want to ' + action + ' this withdrawal?')) return;
            fetch('adminserver.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'admin_withdrawal_action=' + encodeURIComponent(action) + '&withdrawal_id=' + encodeURIComponent(withdrawalId)
            })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Show styled confirmation FIRST
                  showAdminConfirmation('Withdrawal ' + action + 'd successfully!', action);
                  // Delay table refresh so message is visible
                  setTimeout(fetchAdminWithdrawals, 120);
                } else {
                  alert('Failed: ' + (data.message || 'Unknown error'));
                }
              })
              .catch(err => {
                alert('Error: ' + err);
              });
          }

          // Styled admin confirmation (shared for payment/withdrawal)
          function showAdminConfirmation(msg, type) {
            let color = type === 'approve' ? '#27ae60' : '#e74c3c';
            let bg = type === 'approve' ? '#eafaf1' : '#ffeaea';
            let border = type === 'approve' ? '#b7e4c7' : '#f5c6cb';
            let div = document.createElement('div');
            div.style.cssText = 'color:' + color + '; background:' + bg + '; border:1px solid ' + border + '; border-radius:6px; text-align:center; margin:1rem 0; padding:0.7rem 0; font-weight:600; z-index:9999;';
            div.textContent = msg;
            let conf = document.getElementById('withdrawal-confirmation');
            if (conf) {
                conf.innerHTML = '';
                conf.appendChild(div);
            } else {
                // Fallback: insert at top of body
                document.body.insertAdjacentElement('afterbegin', div);
            }
            setTimeout(() => { if (conf) conf.innerHTML = ''; else div.remove(); }, 3000);
          }

          // Initial fetch for withdrawals and on-demand refresh
          document.addEventListener('DOMContentLoaded', function () {
            fetchAdminWithdrawals();
          });
        </script>
      </section>
      <section class="dashboard-section" id="admin-notifications">
        <h2>Admin Notifications</h2>
        <table class="dashboard-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Message</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="admin-notifications-body"></tbody>
        </table>
        <button onclick="fetchAdminNotifications()" style="float:right;">Refresh</button>
        <script>
          function fetchAdminNotifications() {
            fetch('adminserver.php?action=admin_notifications', { credentials: 'include' })
              .then(response => response.json())
              .then(data => {
                const nBody = document.getElementById('admin-notifications-body');
                nBody.innerHTML = '';
                if (Array.isArray(data.notifications) && data.notifications.length > 0) {
                  data.notifications.forEach(n => {
                    let statusColor = n.is_read == 1 ? '#888' : '#27ae60';
                    let statusText = n.is_read == 1 ? 'Read' : 'New';
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                      '<td>' + (n.type ? n.type : '') + '</td>' +
                      '<td>' + (n.message ? n.message : '') + '</td>' +
                      '<td>' + (n.created_at ? n.created_at : '') + '</td>' +
                      '<td><span style="font-weight:600; color:' + statusColor + '">' + statusText + '</span></td>';
                    nBody.appendChild(tr);
                  });
                } else {
                  nBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No notifications found.</td></tr>';
                }
              })
              .catch(err => {
                document.getElementById('admin-notifications-body').innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Failed to load notifications.</td></tr>';
                console.error('Failed to fetch notifications:', err);
              });
          }
          document.addEventListener('DOMContentLoaded', function () {
            fetchAdminNotifications();
          });
        </script>
      </section>
      <script>
        // Fetch and display admin dashboard summary data
        fetch('ad.php')
          .then(response => response.json())
          .then((data) => {
            document.getElementById('total-users').textContent = data.total_users;
            document.getElementById('total-investments').textContent = '₦' + Number(data.total_investments).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total-loans').textContent = '₦' + Number(data.total_loans).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total-payment').textContent = '₦' + Number(data.total_payments).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total-withdrawals').textContent = '₦' + Number(data.total_withdrawals).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          })
          .catch(err => {
            console.error('Failed to fetch admin summary:', err);
          });
      </script>
      <?php
      // ...existing code...
      ?>
      <section class="dashboard-section active" id="overview">
        <h1>Admin Dashboard</h1>
        <div class="dashboard-cards" id="admin-cards">
          <div class="dashboard-card">
            <h3>Total Users</h3>
            <p id="total-users">0</p>
          </div>
          <div class="dashboard-card">
            <h3>Total Investments</h3>
            <p id="total-investments">&#8358;0.00</p>
          </div>
          <div class="dashboard-card">
            <h3>Total Loans</h3>
            <p id="total-loans">&#8358;0.00</p>
          </div>
          <div class="dashboard-card">
            <h3>Total Deposits</h3>
            <p id="total-payment">&#8358;0.00</p>
          </div>
          <div class="dashboard-card">
            <h3>Total Withdrawals</h3>
            <p id="total-withdrawals">&#8358;0.00</p>
          </div>
        </div>
      </section>
      <section class="dashboard-section" id="users">
        <h2>All Users</h2>
        <table class="dashboard-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Registered</th>
              <th>Role</th>
              <th>Investments</th>
              <th>Loans</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="users-body"></tbody>
        </table>
        <button onclick="exportTableCSV('users-body')"
          style="float:right; margin-top:1rem; background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.5rem 1.2rem; font-weight:600; cursor:pointer;">Export CSV</button>
        <!-- Edit User Modal -->
        <div id="edit-user-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:9999; align-items:center; justify-content:center;">
          <div style="background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); padding:2rem; max-width:400px; margin:auto; position:relative;">
            <h2 style="margin-top:0; color:#2d3e50; font-size:1.3rem;">Edit User</h2>
            <form id="edit-user-form">
              <input type="hidden" name="id" id="edit-user-id">
              <div style="margin-bottom:1rem;"><label>Username:<br><input type="text" name="username" id="edit-username" style="width:100%;"></label></div>
              <div style="margin-bottom:1rem;"><label>Email:<br><input type="email" name="email" id="edit-email" style="width:100%;"></label></div>
              <div style="margin-bottom:1rem;"><label>Phone:<br><input type="text" name="phone" id="edit-phone" style="width:100%;"></label></div>
              <div style="margin-bottom:1rem;"><label>Role:<br><input type="text" name="role" id="edit-role" style="width:100%;"></label></div>
              <div style="margin-bottom:1rem;"><label>Is Admin:<br>
                <select name="is_admin" id="edit-is-admin" style="width:100%;">
                  <option value="0">No</option>
                  <option value="1">Yes</option>
                </select>
              </label></div>
              <div style="text-align:right;">
                <button type="button" onclick="closeEditUserModal()" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.7rem; cursor:pointer; font-weight:500;">Cancel</button>
                <button type="submit" style="background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;">Save</button>
              </div>
              <div id="edit-user-error" style="color:red; margin-top:0.7rem; display:none;"></div>
            </form>
          </div>
        </div>
        <script>
          // Fetch and display user section info from userserver.php
          function fetchAdminUsers() {
            fetch('userserver.php', { credentials: 'include' })
              .then(response => response.json())
              .then(data => {
                const usersBody = document.getElementById('users-body');
                usersBody.innerHTML = '';
                if (Array.isArray(data.users) && data.users.length > 0) {
                  data.users.forEach(user => {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                      '<td>' + (user.id ? user.id : '') + '</td>' +
                      '<td>' + (user.username ? user.username : '') + '</td>' +
                      '<td>' + (user.email ? user.email : '') + '</td>' +
                      '<td>' + (user.phone ? user.phone : '') + '</td>' +
                      '<td>' + (user.registered ? user.registered : '') + '</td>' +
                      '<td>' + (user.role ? user.role : '') + '</td>' +
                      '<td>' + (user.investments ? user.investments : 0) + '</td>' +
                      '<td>' + (user.loans ? user.loans : 0) + '</td>' +
                      '<td>' +
                        '<button onclick="showDetail(\'user\',' + user.id + ')">Details</button> ' +
                        '<button onclick="openEditUserModal(' + user.id + ')" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-left:0.5rem; cursor:pointer; font-weight:500;">Edit</button>' +
                      '</td>';
                    usersBody.appendChild(tr);
                  });
                } else {
                  usersBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No users found.</td></tr>';
                }
              })
              .catch(err => {
                document.getElementById('users-body').innerHTML = '<tr><td colspan="9" style="text-align:center; color:red;">Failed to load users.</td></tr>';
                console.error('Failed to fetch user section data:', err);
              });
          }

          // Edit user modal logic
          function openEditUserModal(userId) {
            // Fetch user details
            fetch('userserver.php?detail=' + encodeURIComponent(userId), { credentials: 'include' })
              .then(response => response.json())
              .then(user => {
                document.getElementById('edit-user-id').value = user.id || '';
                document.getElementById('edit-username').value = user.username || '';
                document.getElementById('edit-email').value = user.email || '';
                document.getElementById('edit-phone').value = user.phone || '';
                document.getElementById('edit-role').value = user.role || '';
                document.getElementById('edit-is-admin').value = user.is_admin ? '1' : '0';
                document.getElementById('edit-user-modal').style.display = 'flex';
                document.getElementById('edit-user-error').style.display = 'none';
              })
              .catch(err => {
                alert('Failed to load user details: ' + err);
              });
          }

          function closeEditUserModal() {
            document.getElementById('edit-user-modal').style.display = 'none';
          }

          document.getElementById('edit-user-form').onsubmit = function(e) {
            e.preventDefault();
            const id = document.getElementById('edit-user-id').value;
            const username = document.getElementById('edit-username').value;
            const email = document.getElementById('edit-email').value;
            const phone = document.getElementById('edit-phone').value;
            const role = document.getElementById('edit-role').value;
            const is_admin = document.getElementById('edit-is-admin').value;
            const errorDiv = document.getElementById('edit-user-error');
            errorDiv.style.display = 'none';
            fetch('userserver.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'action=update_user&id=' + encodeURIComponent(id) +
                '&username=' + encodeURIComponent(username) +
                '&email=' + encodeURIComponent(email) +
                '&phone=' + encodeURIComponent(phone) +
                '&role=' + encodeURIComponent(role) +
                '&is_admin=' + encodeURIComponent(is_admin)
            })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  closeEditUserModal();
                  fetchAdminUsers();
                } else {
                  errorDiv.textContent = data.message || 'Failed to update user.';
                  errorDiv.style.display = 'block';
                }
              })
              .catch(err => {
                errorDiv.textContent = 'Error: ' + err;
                errorDiv.style.display = 'block';
              });
          };

          // Initial fetch for users on page load
          document.addEventListener('DOMContentLoaded', function () {
            fetchAdminUsers();
          });
        </script>
      </section>
      <section class="dashboard-section" id="investments">
        <h2>All Investments</h2>
        <table class="dashboard-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Plan</th>
              <th>Amount</th>
              <th>Interest Rate</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Next Payout</th>
              <th>Note</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="admin-investments-body"></tbody>
        </table>
        <button onclick="exportTableCSV('admin-investments-body')"
          style="float:right; margin-top:1rem; background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.5rem 1.2rem; font-weight:600; cursor:pointer;">Export CSV</button>
        <script>
          // Fetch and display all investments for admin
          function fetchAdminInvestments() {
            fetch('admindashboard.php?action=all_investments')
              .then(response => response.json())
              .then(data => {
                const invBody = document.getElementById('admin-investments-body');
                invBody.innerHTML = '';
                if (Array.isArray(data.investments) && data.investments.length > 0) {
                  data.investments.forEach(inv => {
                    const tr = document.createElement('tr');
                    let investActions = '<button onclick="showDetail(\'investment\',' + inv.id + ')" class="btn-details" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-bottom:0.7rem; cursor:pointer; font-weight:500;">Details</button>';
                    if (inv.status === 'pending') investActions += ' <button onclick="adminAction(\'investment\',' + inv.id + ',\'approve\')" class="btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;">Approve</button> <button onclick="adminAction(\'investment\',' + inv.id + ',\'reject\')" class="btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;">Reject</button>';
                    if (inv.status === 'active') investActions += '<br><button onclick="adminAction(\'investment\',' + inv.id + ',\'mature\')" class="btn-mature" style="background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-top:0.7rem; cursor:pointer; font-weight:500;">Mark Matured</button>';
                    tr.innerHTML =
                      '<td>' + (inv.id ? inv.id : '') + '</td>' +
                      '<td>' + (inv.username ? inv.username : '') + '</td>' +
                      '<td>' + (inv.plan ? inv.plan : '') + '</td>' +
                      '<td>₦' + Number(inv.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                      '<td>' + (inv.interest_rate !== undefined && inv.interest_rate !== null ? inv.interest_rate + '%' : '<span style="color:#b71c1c;">N/A</span>') + '</td>' +
                      '<td>' + (inv.status ? inv.status : '') + '</td>' +
                      '<td>' + (inv.created_at ? inv.created_at : '') + '</td>' +
                      '<td>' + (inv.next_payout ? inv.next_payout : '') + '</td>' +
                      '<td>' + (inv.note ? inv.note.replace(/\n/g, '<br>') : '') + '</td>' +
                      '<td>' + investActions + '</td>';
                    invBody.appendChild(tr);
                  });
                } else {
                  invBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">No investments found.</td></tr>';
                }
              })
              .catch(err => {
                document.getElementById('admin-investments-body').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Failed to load investments.</td></tr>';
                console.error('Failed to fetch investments:', err);
              });
          }

          // Admin action handler for investments (approve, reject, mature)
          function adminAction(type, id, action) {
            if (!confirm('Are you sure you want to ' + action + ' this ' + type + '?')) return;
            fetch('admindashboard.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'admin_action=' + encodeURIComponent(action) + '&type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id)
            })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  fetchAdminInvestments();
                  alert(type.charAt(0).toUpperCase() + type.slice(1) + ' ' + action + 'd successfully!');
                } else {
                  alert('Failed: ' + (data.message || 'Unknown error'));
                }
              })
              .catch(err => {
                alert('Error: ' + err);
              });
          }

          // Initial fetch for investments on page load
          document.addEventListener('DOMContentLoaded', function () {
            fetchAdminInvestments();
          });
        </script>
      </section>
      <section class="dashboard-section" id="loans">
        <h2>Loans Overview</h2>
        <table class="dashboard-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Duration</th>
              <th>Purpose</th>
              <th>ID Type</th>
              <th>ID Value</th>
              <th>Status</th>
              <th>Applied</th>
              <th>Due Date</th>
              <th>Processed</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="loans-body"></tbody>
        </table>
        <button onclick="exportTableCSV('loans-body')"
  style="float:right; margin-top:1rem; background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.5rem 1.2rem; font-weight:600; cursor:pointer;">Export CSV</button>
        <div class="pending-loans-card"
          style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.07); padding:2rem; margin:2rem 0; max-width:1000px; margin-left:auto; margin-right:auto;">
          <h3 style="margin-top:0; color:#2d3e50; font-size:1.4rem;">Pending Loans - Admin Approval</h3>
          <div style="overflow-x:auto;">
            <table class="dashboard-table pending-table" style="width:100%; border-collapse:collapse;">
              <thead style="background:#f7f7fa;">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Duration</th>
                  <th>Purpose</th>
                  <th>ID Type</th>
                  <th>ID Value</th>
                  <th>Status</th>
                  <th>Applied</th>
                  <th>Due Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="pending-loans-body"></tbody>
            </table>
          </div>
        </div>
        <script>
          // Fetch and display all loans for admin
          function fetchAdminLoans() {
            fetch('admindashboard.php?action=all_loans&_ts=' + Date.now(), { credentials: 'include' })
              .then(response => response.json())
              .then(data => {
                const loansBody = document.getElementById('loans-body');
                const pendingBody = document.getElementById('pending-loans-body');
                loansBody.innerHTML = '';
                pendingBody.innerHTML = '';
                if (Array.isArray(data.loans) && data.loans.length > 0) {
                  data.loans.forEach(loan => {
                    const tr = document.createElement('tr');
                    let actions = '<button class="btn-details" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.5rem; cursor:pointer; font-weight:500;" onclick="showDetail(\'loan\',' + loan.id + ')">Details</button>';
                    if (loan.status === 'pending') {
                      actions += ' <button class="btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;" onclick="adminLoanAction(' + loan.id + ',\'approve\')">Approve</button>';
                      actions += ' <button class="btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;" onclick="adminLoanAction(' + loan.id + ',\'reject\')">Reject</button>';
                    }
                    tr.innerHTML =
                      '<td>' + (loan.id ? loan.id : '') + '</td>' +
                      '<td>' + (loan.username ? loan.username : '') + '</td>' +
                      '<td>' + (loan.type ? loan.type : '') + '</td>' +
                      '<td>₦' + Number(loan.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                      '<td>' + (loan.duration ? loan.duration + ' weeks' : '') + '</td>' +
                      '<td>' + (loan.purpose ? loan.purpose : '') + '</td>' +
                      '<td>' + (loan.id_type ? loan.id_type : '') + '</td>' +
                      '<td>' + (loan.id_value ? loan.id_value : '') + '</td>' +
                      '<td>' + (loan.status ? loan.status : '') + '</td>' +
                      '<td>' + (loan.created_at ? loan.created_at : '') + '</td>' +
                      '<td>' + (loan.due_date ? loan.due_date : '') + '</td>' +
                    '<td>' + (loan.processed_at ? new Date(loan.processed_at).toLocaleString() : '') + '</td>' +
                      '<td>' + actions + '</td>';
                    loansBody.appendChild(tr);
                  });
                  // Only show pending loans in the pending card
                  let pendingLoans = data.loans.filter(loan => loan.status === 'pending');
                  if (pendingLoans.length > 0) {
                    pendingLoans.forEach(loan => {
                      const tr2 = document.createElement('tr');
                      let actions = '<button class="btn-details" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.5rem; cursor:pointer; font-weight:500;" onclick="showDetail(\'loan\',' + loan.id + ')">Details</button>';
                      actions += ' <button class="btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;" onclick="adminLoanAction(' + loan.id + ',\'approve\')">Approve</button>';
                      actions += ' <button class="btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;" onclick="adminLoanAction(' + loan.id + ',\'reject\')">Reject</button>';
                      tr2.innerHTML =
                        '<td>' + (loan.id ? loan.id : '') + '</td>' +
                        '<td>' + (loan.username ? loan.username : '') + '</td>' +
                        '<td>' + (loan.type ? loan.type : '') + '</td>' +
                        '<td>₦' + Number(loan.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                        '<td>' + (loan.duration ? loan.duration + ' weeks' : '') + '</td>' +
                        '<td>' + (loan.purpose ? loan.purpose : '') + '</td>' +
                        '<td>' + (loan.id_type ? loan.id_type : '') + '</td>' +
                        '<td>' + (loan.id_value ? loan.id_value : '') + '</td>' +
                        '<td>' + (loan.status ? loan.status : '') + '</td>' +
                        '<td>' + (loan.created_at ? loan.created_at : '') + '</td>' +
                        '<td>' + (loan.due_date ? loan.due_date : '') + '</td>' +
                        '<td>' + actions + '</td>';
                      pendingBody.appendChild(tr2);
                    });
                  } else {
                    pendingBody.innerHTML = '<tr><td colspan="13" style="text-align:center;">No pending loans.</td></tr>';
                  }
                } else {
                  loansBody.innerHTML = '<tr><td colspan="12" style="text-align:center;">No loans found.</td></tr>';
                  pendingBody.innerHTML = '<tr><td colspan="12" style="text-align:center;">No pending loans.</td></tr>';
                }
              })
              .catch(err => {
                document.getElementById('loans-body').innerHTML = '<tr><td colspan="12" style="text-align:center; color:red;">Failed to load loans.</td></tr>';
                document.getElementById('pending-loans-body').innerHTML = '<tr><td colspan="12" style="text-align:center; color:red;">Failed to load pending loans.</td></tr>';
                console.error('Failed to fetch loans:', err);
              });
          }
          // ...existing code...
          // Approve/Reject loan
          function adminLoanAction(loanId, action) {
            if (!confirm('Are you sure you want to ' + action + ' this loan?')) return;
            fetch('adminserver.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'admin_loan_action=' + encodeURIComponent(action) + '&loan_id=' + encodeURIComponent(loanId)
            })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  fetchAdminLoans();
                  alert('Loan ' + action + 'd successfully!');
                } else {
                  alert('Failed: ' + (data.message || 'Unknown error'));
                }
              })
              .catch(err => {
                alert('Error: ' + err);
              });
          }
          // Initial fetch for loans
          document.addEventListener('DOMContentLoaded', function () {
            fetchAdminLoans();
          });
        </script>
      </section>
      <section class="dashboard-section" id="payments">
        <div class="payments-card"
          style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.07); padding:2rem; margin-bottom:2rem;">
          <h2 style="margin-top:0; color:#2d3e50;">Deposits Overview</h2>
          <div style="overflow-x:auto;">
            <table class="dashboard-table payments-table" style="width:100%; border-collapse:collapse;">
              <thead style="background:#f7f7fa;">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Email</th>
                  <th>Amount</th>
                  <th>Reference</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="payments-body"></tbody>
            </table>
          </div>
          <button onclick="exportTableCSV('payments-body')"
            style="float:right; margin-top:1rem; background:#2d8cff; color:#fff; border:none; border-radius:4px; padding:0.5rem 1.2rem; font-weight:600; cursor:pointer;">Export
            CSV</button>
        </div>
        <script>
          // Fetch and display all payments in the payments overview table
          function fetchAdminPayments() {
            fetch('adminserver.php?action=all_payments', { credentials: 'include' })
              .then(response => response.json())
              .then(data => {
                const paymentsBody = document.getElementById('payments-body');
                paymentsBody.innerHTML = '';
                if (Array.isArray(data.payments) && data.payments.length > 0) {
                  data.payments.forEach(pay => {
                    const tr = document.createElement('tr');
                    let payActions = '<button class="btn-details" style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.5rem; cursor:pointer; font-weight:500;" onclick="showDetail(\'payment\',' + pay.id + ')">Details</button>';
                    if (pay.status === 'pending') payActions += ' <button class="btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;" onclick="adminAction(\'payment\',' + pay.id + ',\'approve\')">Approve</button> <button class="btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;" onclick="adminAction(\'payment\',' + pay.id + ',\'reject\')">Reject</button>';
                    let statusColor = '#888';
                    if (pay.status === 'pending') statusColor = '#e67e22';
                    else if (pay.status === 'confirmed') statusColor = '#27ae60';
                    else if (pay.status === 'rejected') statusColor = '#e74c3c';
                    tr.innerHTML =
                      '<td>' + (pay.id ? pay.id : '') + '</td>' +
                      '<td>' + (pay.username ? pay.username : '') + '</td>' +
                      '<td>' + (pay.email ? pay.email : '') + '</td>' +
                      '<td>₦' + Number(pay.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                      '<td>' + (pay.reference ? pay.reference : '') + '</td>' +
                      '<td><span style="font-weight:600; color:' + statusColor + '">' + (pay.status ? pay.status.charAt(0).toUpperCase() + pay.status.slice(1) : '') + '</span></td>' +
                      '<td>' + (pay.created_at ? pay.created_at : '') + '</td>' +
                      '<td>' + payActions + '</td>';
                    paymentsBody.appendChild(tr);
                  });
                } else {
                  paymentsBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No payments found.</td></tr>';
                }
              })
              .catch(err => {
                document.getElementById('payments-body').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Failed to load payments.</td></tr>';
                console.error('Failed to fetch payments:', err);
              });
          }
          // Initial fetch for payments and on-demand refresh
          document.addEventListener('DOMContentLoaded', function () {
            fetchAdminPayments();
            var btn = document.getElementById('refresh-payments');
            if (btn) btn.addEventListener('click', fetchAdminPayments);
          });
        </script>

        <h3 style="margin-top:2rem;">Pending Payments - Admin Confirmation</h3>
        <?php if (isset($_GET['success'])): ?>
          <div class="success" style="color:green; text-align:center; margin:1rem 0;">Payment confirmed successfully!
          </div>
        <?php endif;
        ?>

        <div class="pending-payments-card"
          style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.07); padding:2rem; margin-bottom:2rem; max-width:1000px; margin-left:auto; margin-right:auto;">
          <h3 style="margin-top:0; color:#2d3e50; font-size:1.4rem;">Pending Payments - Admin Confirmation</h3>
          <?php if (isset($_GET['success'])): ?>
            <div class="success"
              style="color:#27ae60; background:#eafaf1; border:1px solid #b7e4c7; border-radius:6px; text-align:center; margin:1rem 0; padding:0.7rem 0; font-weight:600;">
              Payment confirmed successfully!</div>
          <?php endif; ?>
          <div style="overflow-x:auto;">
            <table class="dashboard-table pending-table" style="width:100%; border-collapse:collapse;">
              <thead style="background:#f7f7fa;">
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Amount</th>
                  <th>Method</th>
                  <th>Note</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($pending) === 0): ?>
                  <tr>
                    <td colspan="9" style="text-align:center; color:#888;">No pending payments.</td>
                  </tr>
                <?php else:
                  foreach ($pending as $pay): ?>
                    <tr style="background:#fcfcfd; border-bottom:1px solid #f0f0f0;">
                      <td><?= htmlspecialchars($pay['id']) ?></td>
                      <td><?= htmlspecialchars(isset($pay['username']) ? $pay['username'] : '') ?></td>
                      <td><?= htmlspecialchars($pay['name']) ?></td>
                      <td><?= htmlspecialchars($pay['email']) ?></td>
                      <td><span style="font-weight:600; color:#2d8cff;">₦<?= number_format($pay['amount'], 2) ?></span></td>
                      <td><?= htmlspecialchars($pay['method']) ?></td>
                      <td><?= nl2br(htmlspecialchars($pay['note'])) ?></td>
                      <td><?= htmlspecialchars($pay['created_at']) ?></td>
                      <td>
                        <button onclick="showDetail('payment',<?= $pay['id'] ?>)" class="btn-details"
                          style="background:#f5f7fa; color:#2d8cff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-bottom:0.3rem; cursor:pointer; font-weight:500;">Details</button>
                        <a href="admindashboard.php?confirm=<?= $pay['id'] ?>" class="btn btn-approve" style="background:#27ae60; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; margin-right:0.3rem; cursor:pointer; font-weight:500;">Approve</a>
                        <a href="admindashboard.php?reject=<?= $pay['id'] ?>" class="btn btn-reject" style="background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;">Reject</a>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

    </main>
  </div>
  <footer class="dashboard-footer">
    <!-- Footer content here, removed invalid JS template string code -->
    <p>&copy; <?php echo date("Y"); ?> Excel Investments. All rights reserved.</p>
    <!-- Removed duplicate dashboard summary fetch to prevent overwriting formatted values -->

    <script>
      // Export CSV utility for tables
      function exportTableCSV(tbodyId) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        let table = tbody.closest('table');
        if (!table) return;
        let rows = Array.from(table.rows);
        let csv = [];
        rows.forEach(row => {
          let cols = Array.from(row.cells).map(cell => '"' + cell.innerText.replace(/"/g, '""') + '"');
          csv.push(cols.join(','));
        });
        let csvContent = csv.join('\n');
        let blob = new Blob([csvContent], { type: 'text/csv' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'export_' + tbodyId + '_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }
    </script>

    <!-- Add this script before </body> to enable working Details button for all tables -->
    <script>
    function showDetail(type, id) {
      let endpoint = '';
      let label = '';
      let icon = '';
      let fieldMap = {
        user: {
          id: 'User ID', username: 'Username', email: 'Email Address', phone: 'Phone Number', registered: 'Date Registered', role: 'User Role', investments: 'Total Investments', loans: 'Total Loans'
        },
        investment: {
          id: 'Investment ID', username: 'Investor', plan: 'Investment Plan', amount: 'Amount Invested', interest_rate: 'Interest Rate (%)', status: 'Status', created_at: 'Date Created', next_payout: 'Next Payout Date', note: 'Admin Note', matured_at: 'Date Matured'
        },
        loan: {
          id: 'Loan ID', username: 'Borrower', type: 'Loan Type', amount: 'Loan Amount', duration: 'Duration (months)', purpose: 'Purpose', id_type: 'ID Type', id_value: 'ID Value', status: 'Status', created_at: 'Date Applied', due_date: 'Due Date', processed_at: 'Date Processed'
        },
        payment: {
          id: 'Payment ID', username: 'User', name: 'Name', email: 'Email', amount: 'Amount Paid', method: 'Payment Method', status: 'Status', note: 'Admin Note', payment_proof: 'Payment Proof', created_at: 'Date Paid'
        },
        withdrawal: {
          id: 'Withdrawal ID', username: 'User', amount: 'Amount Withdrawn', account_number: 'Account Number', bank_name: 'Bank Name', account_name: 'Account Name', status: 'Status', note: 'Admin Note', created_at: 'Date Requested', processed_at: 'Date Processed'
        }
      };
      switch(type) {
        case 'user':
          endpoint = 'userserver.php?detail=' + encodeURIComponent(id);
          label = 'User Details';
          icon = '👤';
          break;
        case 'investment':
          endpoint = 'adminserver.php?action=investment_detail&id=' + encodeURIComponent(id);
          label = 'Investment Details';
          icon = '💼';
          break;
        case 'loan':
          endpoint = 'adminserver.php?action=loan_detail&id=' + encodeURIComponent(id);
          label = 'Loan Details';
          icon = '💳';
          break;
        case 'payment':
          endpoint = 'adminserver.php?action=payment_detail&id=' + encodeURIComponent(id);
          label = 'Payment Details';
          icon = '💰';
          break;
        case 'withdrawal':
          endpoint = 'adminserver.php?action=withdrawal_detail&id=' + encodeURIComponent(id);
          label = 'Withdrawal Details';
          icon = '🏦';
          break;
        default:
          alert('Unknown detail type');
          return;
      }
      fetch(endpoint, { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
          let html = '<div style="background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); padding:2rem; max-width:500px; margin:2rem auto; position:relative; z-index:9999;">';
          html += '<h2 style="margin-top:0; color:#2d3e50; font-size:1.5rem; display:flex; align-items:center; gap:0.7rem;">' + icon + ' ' + label + '</h2>';
          html += '<button onclick="this.parentElement.remove()" style="position:absolute; top:10px; right:10px; background:#e74c3c; color:#fff; border:none; border-radius:4px; padding:0.3rem 0.8rem; cursor:pointer; font-weight:500;">Close</button>';
          if (typeof data === 'object' && data !== null) {
            let map = fieldMap[type] || {};
            // Group fields for clarity
            if (type === 'investment') {
              html += '<div style="margin-bottom:1.2rem;">';
              html += '<span style="font-weight:600; color:#2d8cff;">Plan:</span> ' + (data.plan || 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Amount:</span> ₦' + (data.amount ? Number(data.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Interest:</span> ' + (data.interest_rate !== undefined && data.interest_rate !== null ? data.interest_rate + '%' : 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Status:</span> ' + (data.status || 'N/A');
              html += '</div>';
            }
            if (type === 'loan') {
              html += '<div style="margin-bottom:1.2rem;">';
              html += '<span style="font-weight:600; color:#2d8cff;">Type:</span> ' + (data.type || 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Amount:</span> ₦' + (data.amount ? Number(data.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Status:</span> ' + (data.status || 'N/A');
              html += '</div>';
            }
            if (type === 'payment') {
              html += '<div style="margin-bottom:1.2rem;">';
              html += '<span style="font-weight:600; color:#2d8cff;">Method:</span> ' + (data.method || 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Amount:</span> ₦' + (data.amount ? Number(data.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Status:</span> ' + (data.status || 'N/A');
              html += '</div>';
            }
            if (type === 'withdrawal') {
              html += '<div style="margin-bottom:1.2rem;">';
              html += '<span style="font-weight:600; color:#2d8cff;">Bank:</span> ' + (data.bank_name || 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Account:</span> ' + (data.account_number || 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Amount:</span> ₦' + (data.amount ? Number(data.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'N/A') + ' | ';
              html += '<span style="font-weight:600; color:#2d8cff;">Status:</span> ' + (data.status || 'N/A');
              html += '</div>';
            }
            html += '<table style="width:100%; border-collapse:collapse;">';
            for (let key in map) {
              let labelText = map[key] || key.replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase());
              let value = (key in data && data[key] !== null && data[key] !== '') ? data[key] : 'Not Provided';
              // Format amount and dates
              if (key.match(/amount/) && value !== 'Not Provided') value = '₦' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              if (key.match(/date|created_at|registered|next_payout|matured_at|processed_at/) && value !== 'Not Provided') value = new Date(value).toLocaleString();
              // Show payment proof as image or link
              if (type === 'payment' && key === 'payment_proof') {
                if (value !== 'Not Provided') {
                  let ext = value.split('.').pop().toLowerCase();
                  let isImg = ['jpg','jpeg','png','gif','webp'].includes(ext);
                  let proofUrl = value.startsWith('http') ? value : ('../' + value);
                  if (isImg) {
                    value = '<a href="' + proofUrl + '" target="_blank"><img src="' + proofUrl + '" alt="Proof" style="max-width:120px; max-height:120px; border-radius:4px; border:1px solid #eee;" /></a>';
                  } else {
                    value = '<a href="' + proofUrl + '" target="_blank">View</a>';
                  }
                } else {
                  value = '<span style="color:#888;">None</span>';
                }
              }
              html += '<tr><td style="font-weight:600; color:#2d8cff; padding:0.4rem 0.7rem;">' + labelText + '</td><td style="padding:0.4rem 0.7rem;">' + String(value).replace(/\n/g,'<br>') + '</td></tr>';
            }
            html += '</table>';
          } else {
            html += '<div style="color:#888;">No details found.</div>';
          }
          html += '</div>';
          let modal = document.createElement('div');
          modal.innerHTML = html;
          modal.style.position = 'fixed';
          modal.style.top = '0';
          modal.style.left = '0';
          modal.style.width = '100vw';
          modal.style.height = '100vh';
          modal.style.background = 'rgba(0,0,0,0.18)';
          modal.style.zIndex = '9999';
          modal.onclick = function(e) { if (e.target === modal) modal.remove(); };
          document.body.appendChild(modal);
        })
        .catch(err => {
          alert('Failed to load details: ' + err);
        });
    }
    </script>

    <script>
      window.fetchAdminLoans = fetchAdminLoans;
    </script>

</body>

</html>