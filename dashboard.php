<?php
session_start();
require_once 'db.php'; // Database connection
// --- Extra safety and debug logging for session/user_id logic ---
$debug_log_file = __DIR__ . '/user_debug.log';
function log_user_debug($msg) {
    global $debug_log_file;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($debug_log_file, "[$ts] $msg\n", FILE_APPEND);
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$phone = isset($_SESSION['phone']) ? $_SESSION['phone'] : '';

if (!$username) {
    log_user_debug('No username in session. Session: ' . json_encode($_SESSION));
}

// Get user_id from users table
$user_id = null;
if ($username) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user_id = $stmt->fetchColumn();
    if (!$user_id) {
        log_user_debug("Username '$username' not found in users table.");
    }
} else {
    log_user_debug('Username is empty, cannot fetch user_id.');
}

if (!$user_id) {
    log_user_debug('user_id is null for username: ' . $username);
}

// Handle edit profile form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_username'], $_POST['edit_email'], $_POST['edit_phone'])) {
    $new_username = trim($_POST['edit_username']);
    $new_email = trim($_POST['edit_email']);
    $new_phone = trim($_POST['edit_phone']);
    $update_success = false;
    if ($user_id && $new_username && $new_email && $new_phone) {
        // Update users table
        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?');
        $update_success = $stmt->execute([$new_username, $new_email, $new_phone, $user_id]);
        if ($update_success) {
            // Update session values
            $_SESSION['username'] = $new_username;
            $_SESSION['email'] = $new_email;
            $_SESSION['phone'] = $new_phone;
        }
    }
    // Optionally, set a message for frontend
    echo '<script>document.getElementById("edit-profile-message").textContent = "'.($update_success ? 'Profile updated successfully.' : 'Failed to update profile.').'";</script>';
}

// Portfolio summary calculations (new formula)
// --- New Portfolio Calculation ---
$totalPayment = 0;
$totalWithdrawal = 0;
$totalInvested = 0;
$totalInvestmentInterest = 0;
$totalLoanPrincipal = 0;
$totalLoanInterest = 0;
$totalRepayments = 0;
$cashBalance = 0;
$portfolioValue = 0;
if ($user_id) {
    // Total Payment: sum of user's confirmed payments only
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = "confirmed"');
    $stmt->execute([$user_id]);
    $totalPayment = $stmt->fetchColumn();
    $totalPayment = $totalPayment !== null ? floatval($totalPayment) : 0;

    // Total Withdrawal: sum of user's approved and completed withdrawals
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND (status = "approved" OR status = "completed")');
    $stmt->execute([$user_id]);
    $totalWithdrawal = $stmt->fetchColumn();
    $totalWithdrawal = $totalWithdrawal !== null ? floatval($totalWithdrawal) : 0;

    // Total Invested and Investment Interest (active investments)
    $stmt = $pdo->prepare('SELECT amount FROM investments WHERE user_id = ? AND status = "active"');
    $stmt->execute([$user_id]);
    $totalInvested = 0;
    $totalInvestmentInterest = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inv) {
        $amt = floatval($inv['amount']);
        $rate = 0; // Default to 0 if no interest_rate column
        $duration = 1; // Default to 1 if no duration column
        $totalInvested += $amt;
        $totalInvestmentInterest += $amt * ($rate / 100) * $duration;
    }

    // Outstanding Loan Principal and Interest (not fully repaid)
    // Patch: Only select amount, set rate/duration defaults if columns missing
    $stmt = $pdo->prepare('SELECT amount FROM loans WHERE user_id = ? AND status != "repaid"');
    $stmt->execute([$user_id]);
    $totalLoanPrincipal = 0;
    $totalLoanInterest = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $loan) {
        $amt = floatval($loan['amount']);
        $rate = 0; // Default to 0 if no interest_rate column
        $duration = 1; // Default to 1 if no duration column
        $totalLoanPrincipal += $amt;
        $totalLoanInterest += $amt * ($rate / 100) * $duration;
    }

    // Total Repayments: sum of user's repayments
    $stmt = $pdo->prepare('SELECT SUM(amount) FROM repayments WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $totalRepayments = $stmt->fetchColumn();
    $totalRepayments = $totalRepayments !== null ? floatval($totalRepayments) : 0;

    // Cash Balance = Total Payments - Total Withdrawals - Total Invested - Total Repayments
    $cashBalance = $totalPayment - $totalWithdrawal - $totalInvested - $totalRepayments;

    // Portfolio Value = Cash Balance + Total Investment Interest - Outstanding Loan Principal - Outstanding Loan Interest
    $portfolioValue = $cashBalance + $totalInvestmentInterest - $totalLoanPrincipal - $totalLoanInterest;
} else {
    log_user_debug('Portfolio summary skipped: user_id is not set.');
}
?>
<script>
// Set initial USER_BALANCE from PHP for immediate use in JS
window.USER_BALANCE = <?php echo json_encode($cashBalance); ?>;
</script>


<!-- Repay Loan Modal (single, global, always present at end of body) -->
<div id="repay-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(44,62,80,0.18);z-index:9999;align-items:center;justify-content:center;flex-direction:column;"></div>
<script>
// Repay Loan Modal Logic (global, for all repay-loan-btn)
function bindRepayButtons() {
  document.querySelectorAll('.repay-loan-btn').forEach(btn => {
    btn.onclick = function() {
      const loanId = this.getAttribute('data-loan-id');
      const total = this.getAttribute('data-total');
      const modal = document.getElementById('repay-modal');
      modal.innerHTML = `<div style='background:#fff;padding:2.2rem 2rem 2rem 2rem;border-radius:1.2rem;box-shadow:0 2px 12px rgba(44,62,80,0.13);max-width:400px;width:90vw;display:flex;flex-direction:column;align-items:center;'><h2 style='color:#26734d;font-size:1.3rem;font-weight:800;margin-bottom:1.2rem;'>Repay Loan</h2><div style='margin-bottom:1.2rem;'>Total to Repay: <span style='color:#1a237e;font-weight:700;'>₦${Number(total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</span></div><form id='repay-form' style='width:100%;display:flex;flex-direction:column;gap:0.7rem;'><input type='hidden' name='loan_id' value='${loanId}'><label for='repay_amount' style='font-weight:600;color:#232946;margin-bottom:0.2rem;'>Amount (₦):</label><input type='number' name='repay_amount' id='repay_amount' min='1' max='${total}' required style='padding:0.6rem 1rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;'><div id='repay-error' style='color:#b71c1c;font-weight:600;display:none;'></div><button type='submit' style='margin-top:0.7rem;padding:0.7rem 0;background:linear-gradient(90deg,#1a237e,#26734d);color:#fff;border:none;border-radius:0.7rem;font-size:1.05rem;font-weight:700;cursor:pointer;'>Submit Repayment</button></form><button id='close-repay-modal' style='margin-top:1.2rem;background:none;border:none;color:#b71c1c;font-size:1.1rem;cursor:pointer;'>Cancel</button></div>`;
      modal.style.display = 'flex';
      // Prevent background scroll when modal is open
      document.body.style.overflow = 'hidden';
      document.getElementById('close-repay-modal').onclick = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      };
      // Close modal on outside click
      modal.onclick = function(e) {
        if (e.target === modal) {
          modal.style.display = 'none';
          document.body.style.overflow = '';
        }
      };
      document.getElementById('repay-form').onsubmit = function(e) {
        e.preventDefault();
        const amount = document.getElementById('repay_amount').value;
        if (!amount || isNaN(amount) || amount <= 0 || amount > total) {
          document.getElementById('repay-error').textContent = 'Enter a valid amount.';
          document.getElementById('repay-error').style.display = 'block';
          return;
        }
        document.getElementById('repay-error').style.display = 'none';
        const formData = new FormData();
        formData.append('loan_id', loanId);
        formData.append('amount', amount);
        formData.append('ajax_repay_loan', '1');
        fetch('dashboardserver.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        })
        .then(async res => {
          const text = await res.text();
          try {
            const data = JSON.parse(text);
            if (data.success) {
              modal.style.display = 'none';
              document.body.style.overflow = '';
              showNotification(data.message || 'Loan repaid successfully!', 'success');
              if (typeof refreshPortfolioSummary === 'function') refreshPortfolioSummary();
              setTimeout(loadLoans, 500);
            } else {
              // Show backend error message in modal for debugging
              document.getElementById('repay-error').textContent = (data.message ? data.message : '') + (data.error ? ' [Error: ' + data.error + ']' : '') || 'Repayment failed.';
              document.getElementById('repay-error').style.display = 'block';
              // Also log full response for debugging
              console.error('Repay AJAX error:', data);
            }
          } catch (err) {
            console.error('Repay AJAX raw response:', text);
            document.getElementById('repay-error').textContent = 'Network or server error. See console for details. [' + text + ']';
            document.getElementById('repay-error').style.display = 'block';
          }
        })
        .catch((err) => {
          document.getElementById('repay-error').textContent = 'Network error.';
          document.getElementById('repay-error').style.display = 'block';
          console.error('Repay AJAX network error:', err);
        });
      };
    };
  });
}
</script>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Excel Investments</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
    .nav-dropdown {
        position: relative;
        display: inline-block;
    }
    .nav-dropbtn {
        background: #232946;
        color: #fff;
        border: none;
        font-size: 1rem;
        font-weight: 700;
        padding: 0.7rem 1.2rem;
        border-radius: 0.7rem;
        cursor: pointer;
        margin-right: 0.5rem;
    }
    .nav-dropdown-content {
        display: none;
        position: absolute;
        background-color: #232946;
        min-width: 180px;
        box-shadow: 0 8px 16px rgba(44,62,80,0.13);
        z-index: 9999;
        border-radius: 0.7rem;
        overflow: hidden;
        color: #232946;
    }
    .nav-dropdown-content a {
        color: #232946;
        padding: 0.8rem 1.2rem;
        text-decoration: none;
        display: block;
        font-size: 1rem;
        font-weight: 600;
   
    }
    .nav-dropdown-content a:last-child {
        border-bottom: none;
    }
    .nav-dropdown-content a:hover {
        background: #eafaf1;
        color: #26734d;
    }
    .nav-dropdown:hover .nav-dropdown-content {
        display: block;
    }
    .nav-dropbtn:focus + .nav-dropdown-content {
        display: block;
    }
    
    </style>
</head>

<body>

<!-- Horizontal Welcome Bar -->
<div id="welcome-bar" style="position:fixed;top:0;left:0;width:100vw;background: #f4f6fb;color:#232946;padding:0.9rem 0;text-align:center;font-size:1.15rem;font-weight:700;z-index:2147483646;box-shadow:0 2px 8px rgba(44,62,80,0.10);letter-spacing:0.5px;display:flex;align-items:center;justify-content:center;">
    <div id="notif-bell-fixed" style="position:fixed;top:22px;right:32px;z-index:2147483647;display:flex;align-items:center;">
  <span id="notif-bell-container" style="position:relative;display:inline-block;vertical-align:middle;cursor:pointer;">
    <svg id="notif-bell" xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#FFD600" style="color:#FFD600;vertical-align:middle;">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" fill="#FFD600" />
    </svg>
    <span id="notif-count" style="display:none;position:absolute;top:-7px;right:-7px;background:#b71c1c;color:#fff;font-size:0.85rem;font-weight:700;padding:1px 6px;border-radius:50%;z-index:2;">0</span>
    <div id="notif-dropdown" style="display:none;position:absolute;right:0;top:2.2rem;background:#fff;min-width:320px;max-width:400px;box-shadow:0 4px 18px rgba(44,62,80,0.13);border-radius:0.8rem;z-index:9999;overflow:hidden;max-height:350px;overflow-y:auto;">
      <div id="notif-list" style="max-height:320px;overflow-y:auto;">
        <div id="notif-fallback" style="padding:1.2rem;text-align:center;color:#b71c1c;font-size:1.08rem;background:#fff;max-width:100%;width:100%;">No notifications</div>
      </div>
    </div>
  </span>

<div id="dashboard-notification" style="display:none;position:fixed !important;top:70px;right:20px;left:auto;z-index:2147483647;background:#26734d;color:#fff;padding:1rem 2rem;border-radius:0.8rem;font-size:1.08rem;box-shadow:0 8px 32px rgba(44,62,80,0.25);font-weight:700;letter-spacing:0.5px;min-width:220px;text-align:center;pointer-events:none;opacity:1 !important;outline:3px solid #fff;border:2px solid #232946;"></div>
</div>
  <button id="hamburger-menu" aria-label="Open navigation" style="display:none;background:transparent !important;border:none;padding:0.5rem;cursor:pointer;outline:none;margin-right:0.7rem;">
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="4" y1="7" x2="20" y2="7" />
      <line x1="4" y1="12" x2="20" y2="12" />
      <line x1="4" y1="17" x2="20" y2="17" />
    </svg>
  </button>
  <span class="welcome-text" style="flex:1;text-align:center;">Welcome, <?php echo htmlspecialchars($username ?: 'Investor'); ?>! to your Excel Investments Dashboard.</span>
</div>
<div id="nav-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(44,62,80,0.18);z-index:2147483646;"></div>
<!-- Spacer to prevent content from being hidden under the fixed bar -->
<div style="height:3.2rem;"></div>

<!-- Bell notification icon at top right -->

<script>
// Show notification at top right
function showNotification(msg, type = 'info', duration = 4000) {
  const notif = document.getElementById('dashboard-notification');
  notif.textContent = msg;
  notif.style.background = type === 'success' ? '#14532d' : (type === 'error' ? '#b71c1c' : '#14532d');
  notif.style.display = 'block';
  notif.style.opacity = '1';
  setTimeout(() => {
    notif.style.opacity = '0';
    setTimeout(() => { notif.style.display = 'none'; }, 400);
  }, duration);
}


// Notification bell dropdown logic
document.addEventListener('DOMContentLoaded', function() {
  var bell = document.getElementById('notif-bell');
  var dropdown = document.getElementById('notif-dropdown');
  var container = document.getElementById('notif-bell-container');
  var notifList = document.getElementById('notif-list');
  if (bell && dropdown && container) {
    container.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      // Toggle dropdown
      if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
      } else {
        dropdown.style.display = 'block';
        // Load notifications from backend
        loadNotifications();
      }
    });
    // Hide dropdown when clicking outside
    document.addEventListener('mousedown', function(e) {
      if (!container.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }
});

// Load notifications from backend and display in dropdown
function loadNotifications() {
  var notifList = document.getElementById('notif-list');
  if (notifList) {
    notifList.innerHTML = '<div style="padding:1.2rem;text-align:center;color:#232946;font-size:1.08rem;background:#fff;">Loading...</div>';
    fetch('dashboardserver.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'ajax_get_notifications=1',
      credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
      if (data.success && Array.isArray(data.notifications) && data.notifications.length > 0) {
        notifList.innerHTML = data.notifications.map(function(n) {
          return `<div style="padding:1rem 1.2rem;border-bottom:1px solid #e0e6ef;background:#fff;color:#232946;">
            <span style="font-weight:700;">${n.message}</span><br>
            <span style="font-size:0.92rem;color:#555;">${n.created_at}</span>
          </div>`;
        }).join('');
      } else {
        notifList.innerHTML = '<div id="notif-fallback" style="padding:1.2rem;text-align:center;color:#b71c1c;font-size:1.08rem;background:#fff;max-width:100%;width:100%;">No notifications</div>';
      }
    })
    .catch(() => {
      notifList.innerHTML = '<div id="notif-fallback" style="padding:1.2rem;text-align:center;color:#b71c1c;font-size:1.08rem;background:#fff;max-width:100%;width:100%;">Failed to load notifications</div>';
    });
  }
}

// AJAX investment form submit (single, clean version)
document.addEventListener('DOMContentLoaded', function() {
  const investForm = document.getElementById('invest-form');
  if (investForm) {
    investForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const plan = document.getElementById('invest_plan').value;
      const amount = parseFloat(document.getElementById('invest_amount').value);
      const note = document.getElementById('invest_note').value;
      const errorDiv = document.getElementById('invest-error-message');
      let min = 0, max = 0;
      if (plan === 'StarterBoost') { min = 1000; max = 4999; }
      else if (plan === 'ProGrow') { min = 5000; max = 19999; }
      else if (plan === 'EliteMax') { min = 20000; max = 1000000; }
      if (!plan) {
        errorDiv.textContent = 'Please select a plan.';
        errorDiv.style.display = 'block';
        return;
      }
      if (isNaN(amount) || amount < min || amount > max) {
        errorDiv.textContent = `Enter a valid amount for ${plan} (₦${min.toLocaleString()} - ₦${max.toLocaleString()}).`;
        errorDiv.style.display = 'block';
        return;
      }
      // Check cash balance (window.USER_BALANCE must be set by backend or AJAX)
      if (typeof window.USER_BALANCE !== 'undefined' && amount > window.USER_BALANCE) {
        errorDiv.textContent = 'You cannot invest more than your available cash balance.';
        errorDiv.style.display = 'block';
        return;
      }
      errorDiv.style.display = 'none';
      const formData = new FormData();
      formData.append('invest_plan', plan);
      formData.append('invest_amount', amount);
      formData.append('invest_note', note);
      formData.append('ajax_invest', '1');
      fetch('dashboardserver.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(res => res.json())
      .then(data => {
        console.log('Investment AJAX response:', data);
        if (data.success) {
          showNotification(data.message || 'Investment successful!', 'success');
          investForm.reset();
          // Instantly update investments table and balance using backend response
          if (Array.isArray(data.investments) && data.investments.length > 0) {
            const tbody = document.getElementById('investments-body');
            if (tbody) {
              tbody.innerHTML = data.investments.map(inv => `
                <tr style="background:#fff;">
                    <td style="border-right:1.5px solid #e0e6ef;">${inv.plan}</td>
                    <td style="border-right:1.5px solid #e0e6ef;">₦${Number(inv.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                    <td style="border-right:1.5px solid #e0e6ef;">${inv.interest_rate}</td>
                    <td style="border-right:1.5px solid #e0e6ef;">₦${Number(inv.total_repayment).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                    <td style="border-right:1.5px solid #e0e6ef;">${inv.status}</td>
                    <td style="border-right:1.5px solid #e0e6ef;">${inv.created_at}</td>
                    <td>${inv.next_payout}</td>
                </tr>
              `).join('');
            }
          } else {
            errorDiv.textContent = 'No investments returned from server. Check backend.';
            errorDiv.style.display = 'block';
          }
          if (typeof data.user_balance !== 'undefined') {
            window.USER_BALANCE = data.user_balance;
            const balElem = document.getElementById('cash-balance-amount');
            if (balElem) {
              balElem.textContent = '₦' + Number(data.user_balance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
            }
          }
          if (typeof refreshPortfolioSummary === 'function') refreshPortfolioSummary();
        } else {
          errorDiv.textContent = data.message || 'Investment failed.';
          errorDiv.style.display = 'block';
        }
      })
      .catch(() => {
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.style.display = 'block';
      });
    });
  }
});

</script>


    <div class="dashboard-container">
        <!-- Dashboard Navigation -->
        <nav class="dashboard-nav">
          <!-- X icon for closing nav on mobile -->
          <button id="close-nav-btn" aria-label="Close navigation" style="display:none;position:absolute;top:18px;right:18px;background:transparent;border:none;padding:0.5rem;z-index:2147483649;cursor:pointer;outline:none;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="5" y1="5" x2="19" y2="19" />
              <line x1="19" y1="5" x2="5" y2="19" />
            </svg>
          </button>

            <a href="#portfolio-overview" class="nav-link">Portfolio</a>
            <div class="nav-dropdown">
                <button class="nav-dropbtn">Deposits</button>
                <div class="nav-dropdown-content">
                    <a href="#make-payment" class="nav-link">Make a Deposit</a>
                    <a href="#payments" class="nav-link">Deposits History</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="nav-dropbtn">Investments</button>
                <div class="nav-dropdown-content">
                    <a href="#invest-action" class="nav-link">Make Investment</a>
                    <a href="#investments" class="nav-link">Investments History</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="nav-dropbtn">Loans</button>
                <div class="nav-dropdown-content">
                    <a href="#loan-requirement" class="nav-link">Loan Requirements</a>
                    <a href="#loan-form" class="nav-link">Apply for a Loan</a>
                    <a href="#loans" class="nav-link">Loans History</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="nav-dropbtn">Withdrawals</button>
                <div class="nav-dropdown-content">
                    <a href="#withdrawal" class="nav-link">Withdraw Funds</a>
                    <a href="#withdrawal-history" class="nav-link">Withdrawal History</a>
                </div>
            </div>
            <a href="#profile" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>

            <!-- Bell icon is now fixed at top right, see #notif-bell-fixed above -->

        </nav>
<style>
/* Hamburger only visible on small screens */
@media (max-width: 900px) {
  #welcome-bar {
    display: flex !important;
    align-items: center;
    justify-content: flex-start;
    padding-left: 0.2rem;
    padding-right: 0.2rem;
  }
  #hamburger-menu {
    display: block !important;
    position: static !important;
    margin-top: 0 !important;
    margin-right: 0.7rem !important;
    z-index: 2147483648;
  }
  .welcome-text {
    flex: 1;
    text-align: center;
    font-size: 1.08rem;
  }
  #close-nav-btn {
    display: none;
    /* Will be shown when nav is open */
  }
  .dashboard-nav {
    position: fixed !important;
    top: 0;
    left: 0;
    height: 100vh;
    width: 60vw;
    max-width: 130px;
    background: #232946;
    box-shadow: 2px 0 16px rgba(44,62,80,0.13);
    z-index: 2147483647;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(.4,0,.2,1);
    border-top-right-radius: 1.2rem;
    border-bottom-right-radius: 1.2rem;
    padding-top: 3.2rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    /* Make sure close button is visible when nav is open */
  }
  .dashboard-nav.open #close-nav-btn {
    display: block !important;
    margin-top: -1rem;
  }
  }
  .dashboard-nav.open {
    transform: translateX(0);
    box-shadow: 2px 0 32px rgba(44,62,80,0.18);
  }
  #nav-overlay {
    display: none;
  }
  #nav-overlay.active {
    display: block !important;
  }
 
  


  
 

/*@media (min-width: 901px) {
  #hamburger-menu {
    display: none !important;
  }
  #nav-overlay {
    display: none !important;
  }
  .dashboard-nav {
    position: static !important;
    transform: none !important;
    box-shadow: none !important;
    height: auto !important;
    width: auto !important;
    max-width: none !important;
    border-radius: 0 !important;
    padding-top: 0 !important;
    flex-direction: row !important;
    gap: 0.5rem !important;
  }
}*/
</style>
<script>
// Hamburger menu toggle logic (isolated)
document.addEventListener('DOMContentLoaded', function() {
  const hamburger = document.getElementById('hamburger-menu');
  const nav = document.querySelector('.dashboard-nav');
  const overlay = document.getElementById('nav-overlay');
  const closeBtn = document.getElementById('close-nav-btn');
  function openNav() {
    nav.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeNav() {
    nav.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
  if (hamburger && nav && overlay) {
    hamburger.addEventListener('click', function(e) {
      e.stopPropagation();
      if (!nav.classList.contains('open')) {
        openNav();
      } else {
        closeNav();
      }
    });
    overlay.addEventListener('click', function() {
      closeNav();
    });
    if (closeBtn) {
      closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeNav();
      });
    }
    // Close nav on resize to desktop
    window.addEventListener('resize', function() {
      if (window.innerWidth > 900) {
        closeNav();
      }
    });
    // Optional: close nav on ESC key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeNav();
    });
  }
});
</script>
     
        <!-- Dashboard Main Content -->
        <main class="dashboard-main">
            <div class="dashboard-sections-container">
    <section class="dashboard-section" id="portfolio-overview" style="margin-bottom:2rem;">
        <div style="background:#f6f8fa;border-radius:1.2rem;padding:2.5rem 2.5rem 2.5rem 2.5rem;box-shadow:0 2px 8px rgba(44,62,80,0.07);margin-bottom:2rem;border:1.5px solid #e0e6ef;">
            <h2 style="margin-bottom:1.2rem;font-size:2.2rem;color:#1a237e;font-weight:900;letter-spacing:0.5px;">Portfolio Overview</h2>
            <div style="margin-bottom:1.2rem;font-size:1.09rem;color:#232946;line-height:1.7;">
                Track your investments, payments, loans, and withdrawals at a glance.
            </div>
            <button id="open-breakdown" style="margin-bottom:1.2rem;padding:0.7rem 1.5rem;background:#1a237e;color:#fff;border:none;border-radius:0.7rem;font-size:1.08rem;font-weight:700;box-shadow:0 2px 8px rgba(44,62,80,0.10);cursor:pointer;transition:background 0.2s;">View Breakdown</button>
           <table class="dashboard-table" style="margin-top:0;">
    <thead>
        <tr>
            <th>Metric</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Active Investments</td>
            <td><span data-portfolio-active-investments>₦<?php echo number_format($activeInvestments, 2); ?></span></td>
        </tr>
        <tr>
            <td>Outstanding Loans</td>
            <td><span data-portfolio-outstanding-loans>₦<?php echo number_format($totalOutstandingLoan, 2); ?></span></td>
        </tr>
        <tr>
            <td>Total Deposits</td>
            <td><span data-portfolio-total-payments>₦<?php echo number_format($totalPayment, 2); ?></span></td>
        </tr>
        <tr>
            <td>Total Withdrawals</td>
            <td><span data-portfolio-total-withdrawals>₦<?php echo number_format($totalWithdrawal, 2); ?></span></td>
        </tr>
       
        <tr>
            <td>Cash Balance</td>
            <td><span data-portfolio-cash-balance>₦<?php echo number_format($cashBalance, 2); ?></span></td>
        </tr>
        <tr>
            <td>Portfolio Value</td>
            <td><span data-portfolio-value>₦<?php echo number_format($portfolioValue, 2); ?></span></td>
        </tr>
    </tbody>
    
</table>
            <div style="margin-top:1.5rem;font-size:1.08rem;color:#232946;background:#eafaf1;padding:1.2rem 1.5rem;border-radius:0.8rem;margin-bottom:0.5rem;">
                <strong>How is your Portfolio Value calculated?</strong><br>
                <span style="color:#007bff;">
                    <b>Portfolio Value = Cash Balance + Total Investment Interest - Outstanding Loan Principal - Outstanding Loan Interest</b>
                </span><br>
                <span style="font-size:0.98rem;color:#555;">Cash Balance is your available funds after withdrawals, investments, and repayments. Investment interest is added, and both outstanding loan principal and interest are subtracted.</span>
            </div>
            <!-- Breakdown Modal -->
            <?php $repaid = isset($repaid) ? $repaid : 0; // Ensure $repaid is always defined ?>
            <div id="breakdown-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(44,62,80,0.18);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:2rem 2.5rem;border-radius:1.2rem;box-shadow:0 2px 16px rgba(44,62,80,0.13);max-width:420px;width:90vw;position:relative;">
                    <button id="close-breakdown" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;color:#b71c1c;cursor:pointer;">&times;</button>
                    <h3 style="margin-bottom:1.2rem;color:#232946;">Portfolio Breakdown</h3>
                    <div style="font-size:1.08rem;color:#232946;margin-bottom:1.2rem;">
                        <strong>Portfolio Value Calculation:</strong><br>
                        <span style="color:#007bff;">
                            Portfolio Value = Cash Balance + Total Investment Interest - Outstanding Loan Principal - Outstanding Loan Interest
                        </span><br>
                      
                    </div>
                    <ul style="list-style:none;padding:0;margin:0 0 1rem 0;font-size:1.05rem;">
                        <li style="margin-bottom:0.7rem;"><strong>Cash Balance:</strong> ₦<?php echo number_format($cashBalance, 2); ?></li>
                        <li style="margin-bottom:0.7rem;"><strong>Total Deposits:</strong> ₦<?php echo number_format($totalPayment, 2); ?></li>
                        <li style="margin-bottom:0.7rem;"><strong>Total Withdrawals:</strong> ₦<?php echo number_format($totalWithdrawal, 2); ?></li>
                        
                        <!-- Repaid Loans removed from breakdown as requested -->
                        <li style="margin-bottom:0.7rem;"><strong>Portfolio Value:</strong> ₦<?php echo number_format($portfolioValue, 2); ?></li>
                    </ul>
                </div>
          
<script>
// Portfolio Breakdown Modal Logic
document.addEventListener('DOMContentLoaded', function() {
    var openBtn = document.getElementById('open-breakdown');
    var modal = document.getElementById('breakdown-modal');
    var closeBtn = document.getElementById('close-breakdown');
    if (openBtn && modal && closeBtn) {
        openBtn.addEventListener('click', function() {
            // Fix: Copy portfolio value from overview to breakdown modal
            var overviewValueElem = document.querySelector('[data-portfolio-value]');
            var breakdownValueElem = modal.querySelector('li strong');
            // Find the correct <li> for Portfolio Value
            var breakdownListItems = modal.querySelectorAll('ul li');
            breakdownListItems.forEach(function(li) {
                if (li.textContent.trim().startsWith('Portfolio Value:')) {
                    if (overviewValueElem) {
                        li.innerHTML = '<strong>Portfolio Value:</strong> ' + overviewValueElem.textContent;
                    }
                }
            });
            modal.style.display = 'flex';
        });
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        // Close modal on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    }
});
function refreshPortfolioSummary() {
    fetch('dashboardserver.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'get_portfolio_summary=1',
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        let repaid = 0;
        if (data.success && data.summary) {
            document.querySelector('[data-portfolio-active-investments]').textContent =
                `₦${Number(data.summary.active_investments).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            document.querySelector('[data-portfolio-outstanding-loans]').textContent =
                `₦${Number(data.summary.outstanding_loans).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            document.querySelector('[data-portfolio-total-payments]').textContent =
                `₦${Number(data.summary.total_payments).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            document.querySelector('[data-portfolio-total-withdrawals]').textContent =
                `₦${Number(data.summary.total_withdrawals).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            // Always set repaid loans, fallback to 0.00 if missing
            if (typeof data.summary.total_repayments !== 'undefined' && data.summary.total_repayments !== null) {
                repaid = Number(data.summary.total_repayments);
            }
            document.querySelectorAll('[data-portfolio-repaid-loans]').forEach(function(el) {
                el.textContent = `₦${repaid.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            });
            document.querySelector('[data-portfolio-cash-balance]').textContent =
                `₦${Number(data.summary.cash_balance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            document.querySelector('[data-portfolio-value]').textContent =
                `₦${Number(data.summary.portfolio_value).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
            // Set global USER_BALANCE
            window.USER_BALANCE = Number(data.summary.cash_balance);
            // If investment summary exists, update it
            if (typeof updateInvestSummary === 'function') {
                updateInvestSummary();
            }
        } else {
            // Fallback: set repaid loans to 0.00 if AJAX fails
            document.querySelectorAll('[data-portfolio-repaid-loans]').forEach(function(el) {
                el.textContent = '₦0.00';
            });
        }
        // Always force update repaid loans value after summary (guaranteed refresh)
        fetch('dashboardserver.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax_get_repayments=1',
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.repayments && Array.isArray(data.repayments)) {
                let total = 0;
                data.repayments.forEach(r => {
                    if (r.amount) total += Number(r.amount);
                });
                document.querySelectorAll('[data-portfolio-repaid-loans]').forEach(function(el) {
                    el.textContent = `₦${total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
                });
            }
        });
    });
}
    document.addEventListener('DOMContentLoaded', function() {
        loadLoans();
    });
</script>
</section>
<section class="dashboard-section" id="loans" style="margin-bottom:2rem;">

            <h2>Your Loans</h2>
            <div id="loan-error-log" style="color:#b71c1c;font-weight:600;margin-bottom:0.5rem;"></div>
            <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Loan ID</th>
                        <th>Amount (₦)</th>
                        <th>Interest</th>
                        <th>Total Repayable</th>
                        <th>Duration</th>
                        <th>Created At</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="loans-body">
                    <!-- Loans data will be loaded here -->
                </tbody>
            </table>
            </div>
            <script>
            // AJAX: Load loans table
            function loadLoans() {
                fetch('dashboardserver.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax_get_loans=1',
                    credentials: 'same-origin'
                })
                .then(async res => {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        const tbody = document.getElementById('loans-body');
                        const errorDiv = document.getElementById('loan-error-log');
                        if (!data || !('loans' in data)) {
                            if (tbody) tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#b71c1c;">Error: No loans data returned from server.</td></tr>';
                            if (errorDiv) errorDiv.textContent = 'Error: No loans data returned from server.';
                            return;
                        }
                        if (tbody && Array.isArray(data.loans)) {
                            if (data.loans.length > 0) {
                                tbody.innerHTML = data.loans.map(loan => `
                                    <tr style="background:#fff;">
                                        <td>${loan.id}</td>
                                        <td>₦${Number(loan.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                        <td>${loan.interest}%</td>
                                        <td>₦${Number(loan.total_repayment).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                        <td>${loan.duration} weeks</td>
                                        <td>${loan.created_at || '-'}</td>
                                        <td>${loan.due_date || '-'}</td>
                                        <td>${loan.status}</td>
                                        <td>
                                            ${loan.status === 'pending' ? `<button class="repay-loan-btn" data-loan-id="${loan.id}" data-total="${loan.total_repayment}">Repay</button>` : ''}
                                        </td>
                                    </tr>
                                `).join('');
                                if (errorDiv) errorDiv.textContent = '';
                                // Re-bind repay buttons
                                if (typeof bindRepayButtons === 'function') bindRepayButtons();
                            } else {
                                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#b71c1c;">No loans found.</td></tr>';
                                if (errorDiv) errorDiv.textContent = '';
                            }
                        } else if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#b71c1c;">Error: Loans data is not an array.</td></tr>';
                            if (errorDiv) errorDiv.textContent = 'Error: Loans data is not an array.';
                        }
                    } catch (err) {
                        const tbody = document.getElementById('loans-body');
                        const errorDiv = document.getElementById('loan-error-log');
                        if (tbody) tbody.innerHTML = `<tr><td colspan='9' style='text-align:center;color:#b71c1c;'>Failed to load loans: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
                        if (errorDiv) errorDiv.textContent = 'Failed to load loans: ' + err;
                    }
                })
                .catch((err) => {
                    const tbody = document.getElementById('loans-body');
                    const errorDiv = document.getElementById('loan-error-log');
                    if (tbody) tbody.innerHTML = `<tr><td colspan='9' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
                    if (errorDiv) errorDiv.textContent = 'Network error: ' + err;
                });
            }
            // Auto-load loans when DOM is ready
            document.addEventListener('DOMContentLoaded', loadLoans);
            </script>
</section>
    <!-- End of Portfolio Overview Section -->
        <section class="dashboard-section" id="withdrawal-history" style="margin-bottom:2rem;">
            <h2>Your Withdrawal History</h2>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Amount (₦)</th>
                        <th>Bank Name</th>
                        <th>Account Number</th>
                        <th>Account Name</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Processed</th>
                    </tr>
                </thead>
                <tbody id="withdrawal-history-body">
                    <!-- Withdrawals data will be loaded here -->
                </tbody>
            </table>
            <script>
            // AJAX: Load withdrawal history table
            function loadWithdrawals() {
                fetch('dashboardserver.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax_get_withdrawals=1',
                    credentials: 'same-origin'
                })
                .then(async res => {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        const tbody = document.getElementById('withdrawal-history-body');
                        if (tbody && data && Array.isArray(data.withdrawals) && data.withdrawals.length > 0) {
                            tbody.innerHTML = data.withdrawals.map(w => `
                                <tr style="background:#fff;">
                                    <td>₦${Number(w.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                    <td>${w.bank_name}</td>
                                    <td>${w.account_number}</td>
                                    <td>${w.account_name}</td>
                                    <td>${w.status}</td>
                                    <td>${w.created_at}</td>
                                    <td>${w.processed_at || '-'}</td>
                                </tr>
                            `).join('');
                        } else if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">No withdrawals found.</td></tr>';
                        }
                        if (typeof refreshPortfolioSummary === 'function') refreshPortfolioSummary();
                    } catch (err) {
                        const tbody = document.getElementById('withdrawal-history-body');
                        if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Failed to load withdrawals: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
                        console.error('Withdrawals AJAX error:', text);
                    }
                })
                .catch((err) => {
                    const tbody = document.getElementById('withdrawal-history-body');
                    if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
                    console.error('Withdrawals AJAX network error:', err);
                });
            }
            document.addEventListener('DOMContentLoaded', function() {
                loadWithdrawals();
            });
            </script>
        </section>
    <!-- End of Withdrawal History Section -->
    <section class="dashboard-section" id="payments">
    <h2>Your Deposits</h2>
    <table class="dashboard-table">
        <thead>
            <tr>
                <th style="border-right:1.5px solid #e0e6ef;">Amount (₦)</th>
                <th style="border-right:1.5px solid #e0e6ef;">Email</th>
                <th style="border-right:1.5px solid #e0e6ef;">Reference</th>
                <th style="border-right:1.5px solid #e0e6ef;">Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="payments-body">
            <!-- Payments data will be loaded here -->
        </tbody>
    </table>
    <script>
    // AJAX: Load payments table
    function loadPayments() {
        fetch('dashboardserver.php?get_payments=1', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(async res => {
            const text = await res.text();
            try {
                const data = JSON.parse(text);
                const tbody = document.getElementById('payments-body');
                if (tbody && data && Array.isArray(data.payments) && data.payments.length > 0) {
                    tbody.innerHTML = data.payments.map(pay => `
                        <tr style="background:#fff;">
                            <td style="border-right:1.5px solid #e0e6ef;">₦${parseFloat(pay.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${pay.email ? pay.email : '-'}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${pay.reference ? pay.reference : '-'}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${pay.status ? pay.status : '-'}</td>
                            <td>${pay.created_at ? pay.created_at : '-'}</td>
                        </tr>
                    `).join('');
                } else if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#b71c1c;">No payments found.</td></tr>';
                }
            } catch (err) {
                const tbody = document.getElementById('payments-body');
                if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Failed to load payments: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
                console.error('Payments AJAX error:', text);
            }
        })
        .catch((err) => {
            const tbody = document.getElementById('payments-body');
            if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
            console.error('Payments AJAX network error:', err);
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        loadPayments();
    });
    </script>
    <!-- End of Payments Section -->
     </section>

<!-- Make Payment Section -->
<section class="dashboard-section" id="make-payment">
    <div class="make-payment-card" style="max-width:420px;margin:2rem auto 0 auto;background:#fff;border-radius:1.2rem;box-shadow:0 4px 18px rgba(44,62,80,0.10);padding:1.7rem 1.5rem 1.5rem 1.5rem;position:relative;">
        <h2 class="make-payment-title" style="color:#232946;font-size:1.3rem;font-weight:800;letter-spacing:0.5px;margin-bottom:1.1rem;">Make a Deposit</h2>
        <form id="make-payment-form" class="make-payment-form-modern" autocomplete="off" style="display:flex;flex-direction:column;gap:0.7rem;">
            <label for="payment_amount" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;text-align:left;">Amount (₦):</label>
            <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="100" required placeholder="Enter amount..." style="width:80%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#fff;box-shadow:0 1px 2px rgba(44,62,80,0.04);transition:border 0.2s;outline:none;">
            <label for="email" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;text-align:left;">Your Email:</label>
            <input type="email" name="email" id="email" required placeholder="Your email address" style="width:80%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#fff;box-shadow:0 1px 2px rgba(44,62,80,0.04);transition:border 0.2s;outline:none;">
            <div id="make-payment-message" style="display:none;color:#b71c1c;font-weight:600;margin-bottom:0.5rem;"></div>
            <button type="submit" style="width:50%;margin:1rem auto 0 auto;padding:0.7rem 0;background:linear-gradient(90deg,#1a237e,#26734d);color:#fff;border:none;border-radius:0.7rem;font-size:1.08rem;font-weight:700;box-shadow:0 1px 4px rgba(44,62,80,0.08);letter-spacing:0.3px;transition:background 0.2s,box-shadow 0.2s;cursor:pointer;">Deposit with Paystack</button>
        </form>
        <div class="make-payment-note" style="margin-top:0.7rem;background:#eafaf1;padding:0.7rem 1rem;border-radius:0.7rem;color:#26734d;font-size:0.98rem;text-align:center;">
            <strong>Note:</strong> All deposits are securely processed via Paystack. For help, contact support.
        </div>
    </div>
    <script>
    // Modern Paystack-only payment form
    document.addEventListener('DOMContentLoaded', function() {
      const paymentForm = document.getElementById('make-payment-form');
      if (paymentForm) {
        let submitting = false;
        const submitBtn = paymentForm.querySelector('button[type="submit"]');
        paymentForm.addEventListener('submit', function(e) {
          e.preventDefault();
          if (submitting) return;
          submitting = true;
          if (submitBtn) submitBtn.disabled = true;
          const amount = document.getElementById('payment_amount').value;
          const email = document.getElementById('email').value;
          const msgDiv = document.getElementById('make-payment-message');
          msgDiv.style.display = 'none';
          msgDiv.textContent = '';
          if (!amount || isNaN(amount) || amount < 100) {
            msgDiv.textContent = 'Enter a valid amount (minimum ₦100).';
            msgDiv.style.display = 'block';
            submitting = false;
            if (submitBtn) submitBtn.disabled = false;
            return;
          }
          if (!email) {
            msgDiv.textContent = 'Please enter your email.';
            msgDiv.style.display = 'block';
            submitting = false;
            if (submitBtn) submitBtn.disabled = false;
            return;
          }
          // Paystack Inline Modal
          var handler = PaystackPop.setup({
            key: 'pk_test_229b93f1bffbb831ae4e13f42212d6956faba8a2', // Replace with your actual Paystack public key
            email: email,
            amount: Math.round(parseFloat(amount) * 100),
            currency: 'NGN',
            channels: ['card', 'bank', 'ussd'],
            callback: function(response) {
              // Send reference and form data to backend for verification and record
              const formData = new FormData();
              formData.append('payment_amount', amount);
              formData.append('email', email);
              formData.append('paystack_reference', response.reference);
              formData.append('ajax_make_payment', '1');
              fetch('dashboardserver.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
              })
              .then(async res => {
                const text = await res.text();
                let data = null;
                try {
                  data = JSON.parse(text);
                } catch (err) {
                  msgDiv.textContent = 'Network error. Please try again later.';
                  msgDiv.style.display = 'block';
                  submitting = false;
                  if (submitBtn) submitBtn.disabled = false;
                  return;
                }
                if (data && data.success) {
                  showNotification(data.message || 'Deposit confirmed successful!', 'success');
                  paymentForm.reset();
                  if (typeof refreshPortfolioSummary === 'function') refreshPortfolioSummary();
                } else {
                  msgDiv.textContent = (data && data.message) ? data.message : 'Payment failed.';
                  msgDiv.style.display = 'block';
                }
                submitting = false;
                if (submitBtn) submitBtn.disabled = false;
              })
              .catch(() => {
                msgDiv.textContent = 'Network error. Please try again.';
                msgDiv.style.display = 'block';
                submitting = false;
                if (submitBtn) submitBtn.disabled = false;
              });
            },
            onClose: function() {
              submitting = false;
              if (submitBtn) submitBtn.disabled = false;
              msgDiv.textContent = 'Payment window closed.';
              msgDiv.style.display = 'block';
            }
          });
          handler.openIframe();
        });
      }
    });
    </script>
    <!-- End of Make Payment Section -->
    <!-- All dashboard JS moved to dashboard.js -->
    </section>
        <section class="dashboard-section" id="investments">
            <h2>Your Investments</h2>
            <div id="invest-error-log" style="color:#b71c1c;font-weight:600;margin-bottom:0.5rem;"></div>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th style="border-right:1.5px solid #e0e6ef;">Plan</th>
                        <th style="border-right:1.5px solid #e0e6ef;">Amount</th>
                        <th style="border-right:1.5px solid #e0e6ef;">Interest Rate (%)</th>
                        <th style="border-right:1.5px solid #e0e6ef;">Total Repayment (₦)</th>
                        <th style="border-right:1.5px solid #e0e6ef;">Status</th>
                        <th style="border-right:1.5px solid #e0e6ef;">Start Date</th>
                        <th>Next Payout</th>
                    </tr>
                </thead>
                <tbody id="investments-body">
                <!-- Investments data will be loaded here -->
                </tbody>
            </table>
            <script>
// AJAX: Load investments table
// Make getVisibleCashBalance globally available for invest form validation
function getVisibleCashBalance() {
  // Use portfolio cash balance only
  const portfolioBalElem = document.querySelector('[data-portfolio-cash-balance]');
  if (portfolioBalElem) {
    // Remove all non-numeric except dot and minus, then parse
    const raw = portfolioBalElem.textContent.replace(/[^\d.\-]/g, '');
    const val = parseFloat(raw);
    if (!isNaN(val)) return val;
  }
  // Fallback to window.USER_BALANCE if element missing or parse fails
  if (typeof window.USER_BALANCE !== 'undefined' && !isNaN(parseFloat(window.USER_BALANCE))) {
    return parseFloat(window.USER_BALANCE);
  }
  return 0;
}

function loadInvestments() {
    fetch('dashboardserver.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_get_investments=1',
        credentials: 'same-origin'
    })
    .then(async res => {
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            const tbody = document.getElementById('investments-body');
            const errorDiv = document.getElementById('invest-error-log');
            console.log('Investments AJAX data:', data);
            if (!data || !('investments' in data)) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">Error: No investments data returned from server.</td></tr>';
                if (errorDiv) errorDiv.textContent = 'Error: No investments data returned from server.';
                console.error('No investments property in response:', data);
                return;
            }
            console.log('Investments AJAX data.investments:', data.investments);
            if (tbody && Array.isArray(data.investments)) {
                if (data.investments.length > 0) {
                    tbody.innerHTML = data.investments.map(inv => `
                        <tr style="background:#fff;">
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.plan}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">₦${Number(inv.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.interest_rate}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">₦${Number(inv.total_repayment).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.status}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.created_at}</td>
                            <td>${inv.next_payout}</td>
                        </tr>
                    `).join('');
                    if (errorDiv) errorDiv.textContent = '';
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">No investments found.</td></tr>';
                    if (errorDiv) errorDiv.textContent = 'No investments found.';
                    console.warn('Investments array is empty:', data.investments);
                }
            } else if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">Error: Investments data is not an array.</td></tr>';
                if (errorDiv) errorDiv.textContent = 'Error: Investments data is not an array.';
                console.error('Investments data is not an array:', data.investments);
            }
            // Update USER_BALANCE if provided
            if (typeof data.user_balance !== 'undefined') {
                window.USER_BALANCE = data.user_balance;
                const balElem = document.getElementById('cash-balance-amount');
                if (balElem) {
                    balElem.textContent = '₦' + Number(data.user_balance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                }
            }
        } catch (err) {
            const tbody = document.getElementById('investments-body');
            const errorDiv = document.getElementById('invest-error-log');
            if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Failed to load investments: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
            if (errorDiv) errorDiv.textContent = 'Failed to load investments: ' + err;
            console.error('Investments AJAX error:', text);
        }
    })
    .catch((err) => {
        const tbody = document.getElementById('investments-body');
        const errorDiv = document.getElementById('invest-error-log');
        if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
        if (errorDiv) errorDiv.textContent = 'Network error: ' + err;
        console.error('Investments AJAX network error:', err);
    });
}
// Auto-refresh portfolio summary every 30 seconds
document.addEventListener('DOMContentLoaded', function() {
    refreshPortfolioSummary(); // Initial load
    setInterval(refreshPortfolioSummary, 30000); // 30 seconds
});

// Ensure investments table loads after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadInvestments);
} else {
    loadInvestments();
}
    fetch('dashboardserver.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_get_investments=1',
        credentials: 'same-origin'
    })
    .then(async res => {
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            const tbody = document.getElementById('investments-body');
            const errorDiv = document.getElementById('invest-error-log');
            console.log('Investments AJAX data:', data);
            if (!data || !('investments' in data)) {
                if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">Error: No investments data returned from server.</td></tr>';
                if (errorDiv) errorDiv.textContent = 'Error: No investments data returned from server.';
                console.error('No investments property in response:', data);
                return;
            }
            console.log('Investments AJAX data.investments:', data.investments);
            if (tbody && Array.isArray(data.investments)) {
                if (data.investments.length > 0) {
                    tbody.innerHTML = data.investments.map(inv => `
                        <tr style="background:#fff;">
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.plan}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">₦${Number(inv.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.interest_rate}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">₦${Number(inv.total_repayment).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.status}</td>
                            <td style="border-right:1.5px solid #e0e6ef;">${inv.created_at}</td>
                            <td>${inv.next_payout}</td>
                        </tr>
                    `).join('');
                    if (errorDiv) errorDiv.textContent = '';
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">No investments found.</td></tr>';
                    if (errorDiv) errorDiv.textContent = 'No investments found.';
                    console.warn('Investments array is empty:', data.investments);
                }
            } else if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b71c1c;">Error: Investments data is not an array.</td></tr>';
                if (errorDiv) errorDiv.textContent = 'Error: Investments data is not an array.';
                console.error('Investments data is not an array:', data.investments);
            }
            // Update USER_BALANCE if provided
            if (typeof data.user_balance !== 'undefined') {
                window.USER_BALANCE = data.user_balance;
                const balElem = document.getElementById('cash-balance-amount');
                if (balElem) {
                    balElem.textContent = '₦' + Number(data.user_balance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                }
            }
        } catch (err) {
            const tbody = document.getElementById('investments-body');
            const errorDiv = document.getElementById('invest-error-log');
            if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Failed to load investments: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
            if (errorDiv) errorDiv.textContent = 'Failed to load investments: ' + err;
            console.error('Investments AJAX error:', text);
        }
    })
    .catch((err) => {
        const tbody = document.getElementById('investments-body');
        const errorDiv = document.getElementById('invest-error-log');
        if (tbody) tbody.innerHTML = `<tr><td colspan='7' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
        if (errorDiv) errorDiv.textContent = 'Network error: ' + err;
        console.error('Investments AJAX network error:', err);
    });
    // End of investments table AJAX script
// Removed extra closing brace to fix syntax error
    </script>
    <!-- End of Investments Section -->
        </section>
            
        <section class="dashboard-section" id="profile">
            <h2>Profile</h2>
            <div class="dashboard-profile">
                <div class="profile-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($username); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
                </div>
                <div class="profile-actions">
                    <button id="edit-profile-btn" type="button">Edit Profile</button>
                    <button id="change-password-btn" type="button">Change Password</button>
                </div>
                <!-- Edit Profile Form (hidden by default) -->
                <form id="edit-profile-form" method="post" style="display:none;margin-top:1.2rem;background:#f8fafd;padding:1.2rem 1.5rem;border-radius:1rem;box-shadow:0 2px 8px rgba(44,62,80,0.07);">
    <h3 style="margin-bottom:1.2rem;font-size:1.3rem;color:#232946;">Edit Profile</h3>
        <label for="old_username" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Old Username:</label>
        <input type="text" name="old_username" id="old_username" value="<?php echo htmlspecialchars($username); ?>" readonly style="width:100%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#f0f0f0;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
        <label for="edit_username" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">New Username:</label>
        <input type="text" name="edit_username" id="edit_username" value="<?php echo htmlspecialchars($username); ?>" required style="width:100%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
        <label for="edit_email" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Email:</label>
        <input type="email" name="edit_email" id="edit_email" value="<?php echo htmlspecialchars($email); ?>" required style="width:100%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
        <label for="edit_phone" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Phone:</label>
        <input type="text" name="edit_phone" id="edit_phone" value="<?php echo htmlspecialchars($phone); ?>" required style="width:100%;padding:1rem 1.2rem;margin-bottom:1.2rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
        <div id="edit-profile-message" style="margin-bottom:0.7rem;font-weight:600;"></div>
        <button type="submit" style="width:100%;margin-top:0.5rem;padding:1rem 0;background:linear-gradient(90deg,#1a237e,#26734d);color:#fff;border:none;border-radius:1rem;font-size:1.15rem;font-weight:700;box-shadow:0 2px 8px rgba(44,62,80,0.10);letter-spacing:0.5px;transition:background 0.2s,box-shadow 0.2s;cursor:pointer;">Save Changes</button>
    </form>
                
            <!-- End of Profile Section -->
             </section>
            <section class="dashboard-section" id="invest-action">
            <div class="invest-action-card" style="max-width:420px;margin:2rem auto 0 auto;background:#fff;border-radius:1.2rem;box-shadow:0 4px 18px rgba(44,62,80,0.10);padding:1.7rem 1.5rem 1.5rem 1.5rem;position:relative;padding-left:3.5rem;">
                <div class="invest-action-header" style="position:absolute;top:1.5rem;left:1.5rem;display:flex;align-items:center;">
                    <!-- Investment Icon -->
                   
                    
                </div>
                <h1 class="invest-card-title" style="margin-bottom:1.1rem;font-size:1.18rem;color:#232946;font-weight:800;text-align:center;">Investment Application</h1>
                <!-- Cash Balance Display removed as per request -->
                <form id="invest-form" class="invest-form-modern" autocomplete="off" style="display:flex;flex-direction:column;gap:0.7rem;">
                    <label for="invest_plan" style="font-weight:600;color:#232946;margin-bottom:0.4rem;display:block;text-align:left;">Choose Plan:</label>
                    <select name="invest_plan" id="invest_plan" required style="width:60%;display:block;margin:0 auto 1rem auto;padding:0.7rem 1rem;border-radius:0.8rem;border:1.3px solid #d1d9e6;font-size:1rem;background:#f8fafd;box-shadow:0 1px 4px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                        <option value="">Select Plan</option>
                        <option value="StarterBoost" data-min="1000" data-max="4999">StarterBoost (₦1,000 - ₦4,999)</option>
                        <option value="ProGrow" data-min="5000" data-max="19999">ProGrow (₦5,000 - ₦19,999)</option>
                        <option value="EliteMax" data-min="20000" data-max="1000000">EliteMax (₦20,000+)</option>
                    </select>
                    <label for="invest_amount" style="font-weight:600;color:#232946;margin-bottom:0.4rem;display:block;text-align:left;">Amount (₦):</label>
                    <input type="number" name="invest_amount" id="invest_amount" step="0.01" min="0" required placeholder="Enter amount..." style="width:60%;display:block;margin:0 auto 1rem auto;padding:0.7rem 1rem;border-radius:0.8rem;border:1.3px solid #d1d9e6;font-size:1rem;background:#fff;box-shadow:0 1px 4px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                    <label for="invest_note" style="font-weight:600;color:#232946;margin-bottom:0.4rem;display:block;text-align:left;">Investment Note (optional):</label>
                    <textarea name="invest_note" id="invest_note" rows="2" placeholder="E.g. Saving for a goal..." style="width:60%;display:block;margin:0 auto 1.2rem auto;padding:0.7rem 1rem;border-radius:0.8rem;border:1.3px solid #d1d9e6;font-size:1rem;background:#fff;box-shadow:0 1px 4px rgba(44,62,80,0.06);resize:vertical;min-height:44px;transition:border 0.2s;outline:none;"></textarea>
                    <div class="invest-summary" id="invest-summary" style="display:none;;padding:0.7rem 1rem;border-radius:0.7rem;color:#26734d;font-size:0.98rem;text-align:center;margin-bottom:0.7rem;"></div>
                    <div id="invest-error-message" style="display:none;color:#b71c1c;font-weight:600;margin-bottom:0.5rem;text-align:center;"></div>
                    <button type="submit" style="width:60%;margin:0.7rem auto 0 auto;padding:0.8rem 0;background:linear-gradient(90deg,#1a237e,#26734d);color:#fff;border:none;border-radius:0.8rem;font-size:1.08rem;font-weight:700;box-shadow:0 1px 6px rgba(44,62,80,0.10);letter-spacing:0.4px;transition:background 0.2s,box-shadow 0.2s;cursor:pointer;">Invest Now</button>
                </form>
            </div>
<script>
           document.addEventListener('DOMContentLoaded', function() {
  const investForm = document.getElementById('invest-form');
  if (investForm) {
    investForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const planSel = document.getElementById('invest_plan');
      const amtInput = document.getElementById('invest_amount');
      const noteInput = document.getElementById('invest_note');
      const errorDiv = document.getElementById('invest-error-message');
      const plan = planSel.value;
      const amount = parseFloat(amtInput.value);
      let min = 0, max = 0, rate = 0;
      if (plan === 'StarterBoost') { min = 1000; max = 4999; rate = 5; }
      else if (plan === 'ProGrow') { min = 5000; max = 19999; rate = 7; }
      else if (plan === 'EliteMax') { min = 20000; max = 1000000; rate = 10; }
      const visibleBalance = getVisibleCashBalance();
      let parsedBalance = parseFloat(Number(visibleBalance).toFixed(2));
      let parsedAmount = parseFloat(Number(amount).toFixed(2));
      if (!plan) {
        errorDiv.textContent = 'Please select an investment plan.';
        errorDiv.style.display = 'block';
        return;
      }
      if (isNaN(parsedAmount) || parsedAmount < min || parsedAmount > max) {
        errorDiv.textContent = `Enter a valid amount for ${plan} (₦${min.toLocaleString()} - ₦${max.toLocaleString()}).`;
        errorDiv.style.display = 'block';
        return;
      }
      // Debug output for troubleshooting
      console.log('Invest Submit Validation:', {parsedAmount, parsedBalance, rawAmount: amtInput.value, rawBalance: visibleBalance});
      // Allow investing if cash balance is equal to or greater than amount (handle floating point rounding)
      if (parsedAmount - parsedBalance > 0.009) {
        errorDiv.textContent = `You cannot invest more than your available cash balance (₦${parsedBalance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}).`;
        errorDiv.style.display = 'block';
        return;
      }
      errorDiv.style.display = 'none';
      const formData = new FormData();
      formData.append('invest_plan', plan);
      formData.append('invest_amount', parsedAmount);
      formData.append('invest_note', noteInput.value);
      formData.append('ajax_invest', '1');
      fetch('dashboardserver.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showNotification('Investment successful!', 'success');
          investForm.reset();
          updateInvestSummary();
          refreshPortfolioSummary();
        } else {
          errorDiv.textContent = data.message || 'Investment failed.';
          errorDiv.style.display = 'block';
        }
      })
       
      
    });
  }
});
</script>
        <!-- End of Invest Action Section -->

        </section>
      
      

<script>

<!--Notifications for due loans and matured investments are created automatically by the backend (see dashboardserver.php). No admin action is needed for these notifications.-->

function loadNotifications() {
  fetch('dashboardserver.php?get_notifications=1')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      var list = data.notifications.map(n =>
        `<div style=\"padding:10px;border-bottom:1px solid #eee;${n.is_read ? 'color:#888;' : 'font-weight:bold;'}\">\n  <div>${n.message}</div>\n  <div style=\"font-size:11px;color:#999;\">${n.created_at}</div>\n</div>`
      ).join('');
      if (!list) {
        list = '<div style="padding:18px 16px;color:#232946;background:#f8fafd;border:2px solid #FFD600;border-radius:0.7rem;text-align:center;font-weight:600;">No notifications</div>';
      }
      document.getElementById('notif-list').innerHTML = list;
      // Show count of unread
      var unread = data.notifications.filter(n => !n.is_read).length;
      var count = document.getElementById('notif-count');
      count.textContent = unread;
      count.style.display = unread ? 'inline' : 'none';
    });
}
</script>
        <!-- Withdraw Funds Section -->
        <section class="dashboard-section" id="withdrawal">
            <div class="withdraw-section-card" style="margin:2rem auto 0 auto;max-width:420px;background:#fff;border-radius:1.2rem;box-shadow:0 4px 18px rgba(44,62,80,0.10);padding:1.7rem 1.5rem 1.5rem 1.5rem;position:relative;">
                <h2 class="withdraw-title">Withdraw Funds</h2>
                <form action="dashboardserver.php" method="POST" id="withdrawal-form" class="withdraw-form-modern" autocomplete="off" style="display:flex;flex-direction:column;gap:0.7rem;">
                    <label for="withdraw_amount" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;">Amount (&#8358;):</label>
                    <input type="number" name="withdraw_amount" id="withdraw_amount" step="0.01" min="1000" required placeholder="Minimum withdrawal: 1,000" style="width:100%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#f8fafd;box-shadow:0 1px 2px rgba(44,62,80,0.04);transition:border 0.2s;outline:none;">
                    <label for="account_number" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;">Account Number:</label>
                    <input type="text" name="account_number" id="account_number" pattern="\d{10}" maxlength="10" required placeholder="10-digit account number" style="width:100%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#f8fafd;box-shadow:0 1px 2px rgba(44,62,80,0.04);transition:border 0.2s;outline:none;">
                    <label for="bank_name" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;">Bank Name:</label>
                    <input type="text" name="bank_name" id="bank_name" required placeholder="e.g. Access Bank" style="width:100%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#f8fafd;box-shadow:0 1px 2px rgba(44,62,80,0.04);transition:border 0.2s;outline:none;">
                    <label for="withdraw_account_name" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;">Account Name:</label>
                    <input type="text" name="account_name" id="withdraw_account_name" required placeholder="Account holder's name" style="width:100%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#f8fafd;box-shadow:0 1px 2px rgba(44,62,80,0.04);transition:border 0.2s;outline:none;">
                    <label for="withdraw_note" style="font-weight:700;color:#232946;margin-bottom:0.2rem;display:block;">Withdrawal Note (optional):</label>
                    <textarea name="withdraw_note" id="withdraw_note" rows="2" placeholder="E.g. For rent, bills, etc..." style="width:100%;padding:0.55rem 0.9rem;border-radius:0.7rem;border:1.2px solid #d1d9e6;font-size:1rem;background:#f8fafd;box-shadow:0 1px 2px rgba(44,62,80,0.04);resize:vertical;min-height:38px;transition:border 0.2s;outline:none;"></textarea>
                    <div id="withdrawal-error-message" style="display:none;color:#b71c1c;font-weight:600;margin-bottom:0.5rem;"></div>
                    <button type="submit" name="withdraw_funds" style="width:100%;margin-top:0.2rem;padding:0.7rem 0;background:linear-gradient(90deg,#1a237e,#26734d);color:#fff;border:none;border-radius:0.7rem;font-size:1.05rem;font-weight:700;box-shadow:0 1px 4px rgba(44,62,80,0.08);letter-spacing:0.3px;transition:background 0.2s,box-shadow 0.2s;cursor:pointer;">Withdraw</button>
                </form>
                <div class="withdraw-note">
                    <strong>Note:</strong> Withdrawals are processed within 24 hours on business days. Ensure your bank details are correct. Minimum withdrawal is &#8358;1,000. Withdrawal requests may be subject to review for security.
                </div>
            </div>
            <script>
            // AJAX withdrawal submission and notification
            document.addEventListener('DOMContentLoaded', function() {
                const withdrawalForm = document.getElementById('withdrawal-form');
                const withdrawalErrorDiv = document.getElementById('withdrawal-error-message');
                if (withdrawalForm) {
                    withdrawalForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        withdrawalErrorDiv.style.display = 'none';
                        const amount = parseFloat(document.getElementById('withdraw_amount').value);
                        const accountNameInput = document.getElementById('withdraw_account_name');
                        const accountName = accountNameInput ? accountNameInput.value.trim() : '';
                        console.log('[Withdraw Debug] Account Name Value:', accountName, '| Raw:', accountNameInput ? accountNameInput.value : '(no input)');
                        // Always get the latest visible cash balance
                        const cashBalance = typeof getVisibleCashBalance === 'function' ? getVisibleCashBalance() : (window.USER_BALANCE !== undefined ? parseFloat(window.USER_BALANCE) : 0);
                        if (!accountName) {
                            console.trace('[Withdraw Debug] Account name is empty, showing error.');
                            withdrawalErrorDiv.textContent = "Please enter the account holder's name.";
                            withdrawalErrorDiv.style.display = 'block';
                            if (accountNameInput) accountNameInput.focus();
                            return;
                        }
                        if (isNaN(amount) || amount < 1000) {
                            withdrawalErrorDiv.textContent = 'Minimum withdrawal is ₦1,000.';
                            withdrawalErrorDiv.style.display = 'block';
                            return;
                        }
                        if (amount > cashBalance) {
                            withdrawalErrorDiv.textContent = `You cannot withdraw more than your available cash balance (₦${cashBalance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}).`;
                            withdrawalErrorDiv.style.display = 'block';
                            return;
                        }
                        const formData = new FormData(withdrawalForm);
                        formData.append('ajax_withdraw_funds', '1');
                        fetch('dashboardserver.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        })
                        .then(res => res.json())
                        .then(data => {
      if (data.success) {
    showNotification(data.message || 'Withdrawal request submitted successfully!', 'success');
    withdrawalForm.reset();
                                loadNotifications();
                                // Instantly update portfolio table
                                if (typeof refreshPortfolioSummary === 'function') refreshPortfolioSummary();
                                withdrawalErrorDiv.style.display = 'none';
                            } else {
                                withdrawalErrorDiv.textContent = data.message || 'Withdrawal failed.';
                                withdrawalErrorDiv.style.display = 'block';
                            }
                        })
                        .catch(err => {
                            withdrawalErrorDiv.textContent = 'Network error. Please try again later.';
                            withdrawalErrorDiv.style.display = 'block';
                            console.error('Withdrawal AJAX error:', err);
                        });
                    });
                }
            });
            </script>
        </section>
        <!-- End of Withdraw Funds Section -->
        <section class="dashboard-section" id="loans">

            <h2>Your Loans</h2>
            <table class="dashboard-table" style="width:100%;min-width:700px;margin:auto;box-shadow:0 2px 12px rgba(44,62,80,0.07);border-radius:1rem;overflow:hidden;">
              <thead style="background:#f8fafd;">
                <tr style="color:#232946;font-weight:600;">
                  <th style="border-right:1.5px solid #e0e6ef;">Type</th>
                  <th style="border-right:1.5px solid #e0e6ef;">Amount (₦)</th>
                  <th style="border-right:1.5px solid #e0e6ef;">Interest (₦)</th>
                  <th style="border-right:1.5px solid #e0e6ef;">Total Repayment (₦)</th>
                  <th style="border-right:1.5px solid #e0e6ef;">Duration (wks)</th>
                  <th style="border-right:1.5px solid #e0e6ef;">Status</th>
                  <th style="border-right:1.5px solid #e0e6ef;">Due Date</th>
                  <th>Applied</th>
                  <th>Repay Loan</th>
                </tr>
              </thead>
              <tbody id="loans-body">
                <!-- Loans data will be loaded here -->
              </tbody>
            </table>
            <script>
            // AJAX: Load loans table (now uses GET)
            function loadLoans() {
                fetch('dashboardserver.php?get_loans=1', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(async res => {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        const tbody = document.getElementById('loans-body');
                        if (tbody && data && Array.isArray(data.loans)) {
                            tbody.innerHTML = data.loans.map((loan, idx) => {
                                const amount = parseFloat(loan.amount) || 0;
                                const duration = loan.duration !== undefined ? loan.duration : '-';
                                const interest = loan.interest !== undefined ? parseFloat(loan.interest) : (amount * 0.05 * (parseInt(duration) || 0));
                                const total = loan.total_repayment !== undefined ? parseFloat(loan.total_repayment) : (amount + interest);
                                const statusColor = loan.status === 'approved' ? '#26734d' : (loan.status === 'pending' ? '#bfa600' : (['rejected','declined'].includes(loan.status) ? '#b71c1c' : '#232946'));
                                return `<tr style="background:#fff;">
                                    <td style="border-right:1.5px solid #e0e6ef;">${loan.type || '-'}</td>
                                    <td style="border-right:1.5px solid #e0e6ef;">₦${amount ? amount.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '-'}</td>
                                    <td style="border-right:1.5px solid #e0e6ef;">₦${interest ? interest.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '-'}</td>
                                    <td style="border-right:1.5px solid #e0e6ef;">₦${total ? total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '-'}</td>
                                    <td style="border-right:1.5px solid #e0e6ef;">${duration || '-'}</td>
                                    <td style="border-right:1.5px solid #e0e6ef;color:${statusColor};font-weight:600;">${loan.status ? loan.status.charAt(0).toUpperCase() + loan.status.slice(1) : '-'}</td>
                                    <td style="border-right:1.5px solid #e0e6ef;">${loan.due_date || '-'}</td>
                                    <td>${loan.created_at || '-'}</td>
                                    <td>${loan.status !== 'repaid' && loan.status !== 'pending' && loan.status !== 'rejected' && loan.status !== 'declined' ? `<button class='repay-loan-btn' data-loan-id='${loan.id || idx}' data-total='${total}' style='padding:0.4rem 0.8rem;background:#26734d;color:#fff;border:none;border-radius:0.5rem;font-size:0.98rem;cursor:pointer;'>Repay</button>` : '-'}</td>
                                </tr>`;
                            }).join('');
                            setTimeout(() => { if (typeof bindRepayButtons === 'function') bindRepayButtons(); }, 0);
                } else if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#b71c1c;">No loans found.</td></tr>';
                }
                    } catch (err) {
                        const tbody = document.getElementById('loans-body');
                        if (tbody) tbody.innerHTML = `<tr><td colspan='9' style='text-align:center;color:#b71c1c;'>Failed to load loans: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
                        console.error('Loans AJAX error:', text);
                    }
                })
                .catch((err) => {
                    const tbody = document.getElementById('loans-body');
                    if (tbody) tbody.innerHTML = `<tr><td colspan='9' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
                    console.error('Loans AJAX network error:', err);
                });
            }
            document.addEventListener('DOMContentLoaded', function() {
                loadLoans();
            });
            </script>
        <!-- End of Loans Section -->
        </section>
        <!-- Loan Requirements Section -->
        <section class="dashboard-section" id="loan-requirement">
            <div style="background:#f6f8fa;border-radius:1.2rem;padding:2.2rem 2rem 2rem 2rem;box-shadow:0 2px 8px rgba(44,62,80,0.07);margin:2rem auto 2rem auto;max-width:600px;border:1.5px solid #e0e6ef;">
                <h2 style="margin-bottom:1.2rem;font-size:1.5rem;color:#1a237e;font-weight:900;letter-spacing:0.5px;">Loan Requirements</h2>
                <ul style="font-size:1.08rem;color:#232946;line-height:1.7;margin-bottom:1.2rem;">
                    <li><strong>Minimum Age:</strong> 18 years</li>
                    <li><strong>Valid ID:</strong> BVN or NIN required</li>
                    <li><strong>Account:</strong> Must have an active account with Excel Investments</li>
                    <li><strong>Payment History:</strong> Good repayment record (if previous loans exist)</li>
                    <li><strong>Income Proof:</strong> May be required for larger loans</li>
                    <li><strong>Loan Amount:</strong> Minimum ₦1,000, maximum based on eligibility</li>
                    <li><strong>Duration:</strong> 1 to 52 weeks</li>
                </ul>
                <div style="background:#eafaf1;padding:1rem 1.2rem;border-radius:0.7rem;color:#26734d;font-size:1.01rem;">
                    <strong>Required Documents:</strong><br>
                    <ul style="margin:0 0 0.5rem 1.2rem;padding:0;">
                        <li>Bank Verification Number (BVN) or National Identification Number (NIN)</li>
                        <li>Valid phone number and email address</li>
                        <li>Bank account details for disbursement</li>
                        <li>Proof of income (for business/large loans)</li>
                    </ul>
                    <span style="color:#b71c1c;">All information provided must be accurate. False information may result in loan rejection.</span>
                </div>
            </div>
        <!-- End of Loan Requirements Section -->
        </section>
        <section class="dashboard-section" id="loan-form" >
            <?php
            // Output user's balance for JS
            // REMOVED window.USER_BALANCE initialization here; now set only by portfolio summary AJAX
            ?>
            <!-- End of Loan Form Section -->
            <div class="loan-application-card" style="margin:2rem auto 0 auto;max-width:480px;background:#fff;border-radius:1.3rem;box-shadow:0 6px 24px rgba(44,62,80,0.13);padding:2.2rem 2rem 2rem 2rem;position:relative;">
                <div class="loan-application-header" style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.2rem;">
                    <span class="loan-icon" style="font-size:3.2rem;display:inline-block;color:#fff;padding:1.1rem 1.3rem;box-shadow:0 2px 12px rgba(44,62,80,0.10);">
                        💰
                    </span>
                    <h1 class="loan-application-title" style="font-size:1.6rem;font-weight:800;color:#232946;">Apply for a Loan</h1>
                </div>
                <h2 class="loan-card-title" style="margin-bottom:1.1rem;font-size:1.18rem;color:#26734d;font-weight:800;text-align:center;">Loan Application Form</h2>
                <form id="loan-application-form" autocomplete="off" style="display:flex;flex-direction:column;gap:0.7rem;">
                    <label for="loan_type" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Loan Type:</label>
                    <select name="loan_type" id="loan_type" required style="width:88%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                        <option value="">Select Loan Type</option>
                        <option value="Personal Loan">Personal Loan</option>
                        <option value="Business Loan">Business Loan</option>
                        <option value="Student Loans">Student Loans</option>
                    </select>
                    <label for="loan_amount" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Amount (&#8358;):</label>
                    <input type="number" name="loan_amount" id="loan_amount" step="0.01" min="1000" required style="width:80%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                    <label for="loan_duration" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Duration (weeks):</label>
                    <input type="number" name="loan_duration" id="loan_duration" min="1" max="52" required style="width:80%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                    <label for="loan_purpose" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">Purpose:</label>
                    <input type="text" name="loan_purpose" id="loan_purpose" required style="width:80%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                    <label for="id_type" style="font-weight:600;color:#232946;margin-bottom:0.5rem;display:block;">ID Verification:</label>
                    <select name="id_type" id="id_type" required style="width:88%;padding:1rem 1.2rem;margin-bottom:1rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                        <option value="">Select ID Type</option>
                        <option value="BVN">BVN</option>
                        <option value="NIN">NIN</option>
                    </select>
                    <input type="text" name="id_value" id="id_value" maxlength="20" required placeholder="Enter your BVN or NIN" style="width:80%;padding:1rem 1.2rem;margin-bottom:1.2rem;border-radius:1rem;border:1.5px solid #d1d9e6;font-size:1.1rem;background:#fff;box-shadow:0 2px 8px rgba(44,62,80,0.06);transition:border 0.2s;outline:none;">
                    <div id="loan-interest-summary" style="margin:0.5rem 0 0.5rem 0;color:#1a4d2e;font-size:1.05rem;display:none;"></div>
                    <div id="loan-application-summary" style="display:none;"></div>
                    <button type="submit" style="width:50%;margin-top:0.5rem;padding:1rem 0;background:linear-gradient(90deg,#1a237e,#26734d);color:#fff;border:none;border-radius:1rem;font-size:1.15rem;font-weight:700;box-shadow:0 2px 8px rgba(44,62,80,0.10);letter-spacing:0.5px;transition:background 0.2s,box-shadow 0.2s;cursor:pointer;">Apply</button>
                </form>
                <div style="margin-top:1rem;font-size:0.98rem;color:#555;">
                    <strong>Note:</strong> Interest is 5% per week. Total repayment = Amount + (5% x weeks x Amount)
                </div>
            </div>
        </section>
            <script>
            // Real-time loan interest/total calculation and AJAX loan application
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('loan-application-form');
                const summaryDiv = document.getElementById('loan-application-summary');
                const interestSummary = document.getElementById('loan-interest-summary');
                const amountInput = document.getElementById('loan_amount');
                const durationInput = document.getElementById('loan_duration');
                // Show interest and total repayment
                function updateInterestSummary() {
                    const amount = parseFloat(amountInput.value);
                    const duration = parseInt(durationInput.value);
                    if (!isNaN(amount) && !isNaN(duration) && amount > 0 && duration > 0) {
                        const interest = amount * 0.05 * duration;
                        const total = amount + interest;
                        interestSummary.innerHTML = `Interest: <strong>₦${interest.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</strong> &nbsp; | &nbsp; Total Repayment: <strong>₦${total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>`;
                        interestSummary.style.display = 'block';
                    } else {
                        interestSummary.style.display = 'none';
                    }
                }
                amountInput.addEventListener('input', updateInterestSummary);
                durationInput.addEventListener('input', updateInterestSummary);

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const loanType = document.getElementById('loan_type').value;
                    const amount = parseFloat(amountInput.value);
                    const duration = parseInt(durationInput.value);
                    const purpose = document.getElementById('loan_purpose').value.trim();
                    const idType = document.getElementById('id_type').value;
                    const idValue = document.getElementById('id_value').value.trim();
                    if (!loanType) {
                        summaryDiv.innerHTML = '<span style="color:#b71c1c;">Please select a loan type.</span>';
                        summaryDiv.style.display = 'block';
                        return;
                    }
                    if (isNaN(amount) || amount < 1000) {
                        summaryDiv.innerHTML = '<span style="color:#b71c1c;">Minimum loan amount is ₦1,000.</span>';
                        summaryDiv.style.display = 'block';
                        return;
                    }
                    if (isNaN(duration) || duration < 1 || duration > 52) {
                        summaryDiv.innerHTML = '<span style="color:#b71c1c;">Duration must be between 1 and 52 weeks.</span>';
                        summaryDiv.style.display = 'block';
                        return;
                    }
                    if (!purpose) {
                        summaryDiv.innerHTML = '<span style="color:#b71c1c;">Please enter the loan purpose.</span>';
                        summaryDiv.style.display = 'block';
                        return;
                    }
                    if (!idType) {
                        summaryDiv.innerHTML = '<span style="color:#b71c1c;">Please select an ID type.</span>';
                        summaryDiv.style.display = 'block';
                        return;
                    }
                    if (!idValue) {
                        summaryDiv.innerHTML = '<span style="color:#b71c1c;">Please enter your BVN or NIN.</span>';
                        summaryDiv.style.display = 'block';
                        return;
                    }
                    summaryDiv.innerHTML = 'Submitting loan application...';
                    summaryDiv.style.display = 'block';
                    // AJAX submit
                    const formData = new FormData();
                    formData.append('loan_type', loanType);
                    formData.append('loan_amount', amount);
                    formData.append('loan_duration', duration);
                    formData.append('loan_purpose', purpose);
                    formData.append('id_type', idType);
                    formData.append('id_value', idValue);
                    formData.append('ajax_loan', '1');
                    fetch('dashboardserver.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message || 'Loan application submitted successfully!', 'success');
                            summaryDiv.innerHTML = '';
                            form.reset();
                            interestSummary.style.display = 'none';
                        } else {
                            showNotification(data.message || 'Loan application failed.', 'error');
                            summaryDiv.innerHTML = `<span style='color:#b71c1c;'>${data.message || 'Loan application failed.'}</span>`;
                        }
                        summaryDiv.style.display = data.success ? 'none' : 'block';
                    })
                    .catch(() => {
                        summaryDiv.innerHTML = `<span style='color:#b71c1c;'>An error occurred. Please try again.</span>`;
                        summaryDiv.style.display = 'block';
                    });
                });
            });
            </script>
          
        </div>
    </main>
    </div> <!-- close dashboard-container -->
    <footer class="dashboard-footer">
        <p>&copy; 2025 Excel Investments. All rights reserved.</p>
    </footer>
    <script src="dashboard.js"></script>
</body>
<script>
// AJAX payment submission and update payments table
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('make-payment-form');
    const paymentMsg = document.getElementById('make-payment-message');
    const paymentMethodSelect = document.getElementById('payment_method');
    const paymentMethodInfo = document.getElementById('payment-method-info');
    if (paymentMethodSelect && paymentMethodInfo) {
        paymentMethodSelect.addEventListener('change', function() {
            let info = '';
            switch (this.value) {
                case 'Bank Transfer':
                    info = '<b>Bank Transfer Info:</b><br>Bank: Zenith Bank<br>Account Name: Excel Investments<br>Account Number: 1234567890';
                    break;
                case 'Card Payment':
                    info = '<b>Card Payment Info:</b><br>Use your debit/credit card. Payment is processed securely.';
                    break;
                case 'USSD':
                    info = '<b>USSD Info:</b><br>Dial *966*123*Amount# to pay.';
                    break;
                default:
                    info = '';
            }
            paymentMethodInfo.innerHTML = info;
            paymentMethodInfo.style.display = info ? 'block' : 'none';
        });
    }
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('payment_amount').value;
            const payeeEmail = document.getElementById('email').value.trim();
            const payeeName = document.getElementById('account_name').value.trim();
            const method = document.getElementById('payment_method').value;
            const note = document.getElementById('payment_note').value.trim();
            let error = '';
            if (!amount || isNaN(amount) || amount <= 0) error = 'Please enter a valid amount.';
            else if (!payeeEmail || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(payeeEmail)) error = 'Please enter a valid payee email.';
            else if (!payeeName) error = 'Please enter the payee account name.';
            else if (!method) error = 'Please select a payment method.';
            if (paymentMsg) {
                if (error) {
                    paymentMsg.textContent = error;
                    paymentMsg.style.display = 'block';
                } else {
                    paymentMsg.textContent = '';
                    paymentMsg.style.display = 'none';
                }
            }
            if (error) return;
            const formData = new FormData();
            formData.append('payment_amount', amount);
            formData.append('email', payeeEmail);
            formData.append('account_name', payeeName);
            formData.append('payment_method', method);
            formData.append('payment_note', note);
            formData.append('ajax_make_payment', '1');
            fetch('dashboardserver.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(async res => {
                const text = await res.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        paymentForm.reset();
                        if (paymentMsg) {
                            paymentMsg.textContent = data.message || 'Payment successful!';
                            paymentMsg.style.color = '#26734d';
                            paymentMsg.style.display = 'block';
                        }
                        loadPayments();
                        setTimeout(() => {
                            if (paymentMsg) paymentMsg.style.display = 'none';
                        }, 1200);
                    } else {
                        if (paymentMsg) {
                            paymentMsg.textContent = data.message || 'Payment failed.';
                            paymentMsg.style.color = '#b71c1c';
                            paymentMsg.style.display = 'block';
                        }
                    }
                } catch (err) {
                    if (paymentMsg) {
                        paymentMsg.textContent = 'Server error: ' + text;
                        paymentMsg.style.color = '#b71c1c';
                        paymentMsg.style.display = 'block';
                    }
                }
            })
            .catch((err) => {
                if (paymentMsg) {
                    paymentMsg.textContent = 'Network error: ' + err;
                    paymentMsg.style.color = '#b71c1c';
                    paymentMsg.style.display = 'block';
                }
            });
        });
    }
});
</script>
<script>
// Periodically refresh portfolio summary every 30 seconds
function refreshPortfolioSummary() {
  fetch('dashboardserver.php?get_breakdown=1')
      .then(response => response.json())
      .then(data => {
          function safeAmount(val) {
              var num = Number(val);
              return isNaN(num) ? 0 : num;
          }
          document.querySelectorAll('[data-portfolio-active-investments]').forEach(cell => {
              cell.textContent = '₦' + safeAmount(data.activeInvestments).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          });
          document.querySelectorAll('[data-portfolio-outstanding-loans]').forEach(cell => {
              cell.textContent = '₦' + safeAmount(data.outstandingLoans).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          });
          document.querySelectorAll('[data-portfolio-total-payments]').forEach(cell => {
              cell.textContent = '₦' + safeAmount(data.totalPayments).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          });
          document.querySelectorAll('[data-portfolio-total-withdrawals]').forEach(cell => {
              cell.textContent = '₦' + safeAmount(data.totalWithdrawals).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          });
          document.querySelectorAll('[data-portfolio-cash-balance]').forEach(cell => {
              cell.textContent = '₦' + safeAmount(data.cashBalance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          });
          document.querySelectorAll('[data-portfolio-value]').forEach(cell => {
              cell.textContent = '₦' + safeAmount(data.portfolioValue).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
          });
      })
      .catch(() => {
          document.querySelectorAll('[data-portfolio-active-investments],[data-portfolio-outstanding-loans],[data-portfolio-total-payments],[data-portfolio-total-withdrawals],[data-portfolio-cash-balance],[data-portfolio-value]').forEach(cell => {
              cell.textContent = '₦0.00';
          });
      });
}
setInterval(refreshPortfolioSummary, 30000); // 30 seconds
// Initial load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', refreshPortfolioSummary);
} else {
  refreshPortfolioSummary();
}
</script>
<!-- Repayment History Section >
<section class="dashboard-section" id="repayment-history" style="margin-bottom:2rem;">
    <h2>Your Repayment History</h2>
    <table class="dashboard-table">
        <thead>
            <tr>
                <th>Loan ID</th>
                <th>Amount (₦)</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="repayment-history-body">
            <Repayments data will be loaded here>
        </tbody>
    </table-->
    <script>
    // AJAX: Load repayment history table
    function loadRepayments() {
        fetch('dashboardserver.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax_get_repayments=1',
            credentials: 'same-origin'
        })
        .then(async res => {
            const text = await res.text();
            try {
                const data = JSON.parse(text);
                const tbody = document.getElementById('repayment-history-body');
                if (tbody && data && Array.isArray(data.repayments) && data.repayments.length > 0) {
                    tbody.innerHTML = data.repayments.map(r => `
                        <tr style=\"background:#fff;\">
                            <td>${r.loan_id}</td>
                            <td>₦${Number(r.amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                            <td>${r.repaid_at}</td>
                        </tr>
                    `).join('');
                } else if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#b71c1c;">No repayments found.</td></tr>';
                }
            } catch (err) {
                const tbody = document.getElementById('repayment-history-body');
                if (tbody) tbody.innerHTML = `<tr><td colspan='3' style='text-align:center;color:#b71c1c;'>Failed to load repayments: <span style='color:#bfa600;'>${text.replace(/</g, '&lt;')}</span></td></tr>`;
                console.error('Repayments AJAX error:', text);
            }
        })
        .catch((err) => {
            const tbody = document.getElementById('repayment-history-body');
            if (tbody) tbody.innerHTML = `<tr><td colspan='3' style='text-align:center;color:#b71c1c;'>Network error: ${err}</td></tr>`;
            console.error('Repayments AJAX network error:', err);
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        loadRepayments();
    });
    </script>
<!-- End of Repayment History Section -->
</section>
