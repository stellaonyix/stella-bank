<?php
session_start();
include __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$id   = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, name, email, account_number, balance, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Fetch recent transactions
$txStmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$txStmt->bind_param("i", $id);
$txStmt->execute();
$txResult     = $txStmt->get_result();
$transactions = $txResult->fetch_all(MYSQLI_ASSOC);
$txStmt->close();

$hour = (int) date('G');
if ($hour < 12)     $greeting = 'Good Morning';
elseif ($hour < 18) $greeting = 'Good Afternoon';
else                $greeting = 'Good Evening';

$firstName = explode(' ', $user['name'])[0];
$initial   = strtoupper($user['name'][0]);
$memberSince = date('F Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stella Bank - Dashboard</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* ── Profile Dropdown ── */
    .profile { position: relative; }

    .avatar {
      cursor: pointer;
      transition: box-shadow .2s;
    }
    .avatar:hover {
      box-shadow: 0 0 0 3px rgba(39,174,96,0.5);
    }

    .profile-dropdown {
      display: none;
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 280px;
      background: #0f1c33;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 0;
      z-index: 999;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      overflow: hidden;
      animation: dropIn .2s ease;
    }
    .profile-dropdown.open { display: block; }

    @keyframes dropIn {
      from { opacity:0; transform: translateY(-8px); }
      to   { opacity:1; transform: translateY(0); }
    }

    .pd-header {
      background: linear-gradient(135deg, #0a2540, #0f1c33);
      padding: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .pd-avatar {
      width: 52px; height: 52px;
      background: linear-gradient(135deg, #27ae60, #1a7a43);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 700;
      margin-bottom: 10px;
    }
    .pd-name  { font-weight: 700; font-size: 16px; }
    .pd-email { font-size: 12px; opacity: .5; margin-top: 2px; }
    .pd-since { font-size: 11px; opacity: .4; margin-top: 4px; }

    .pd-info {
      padding: 14px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .pd-info-row {
      display: flex; justify-content: space-between;
      font-size: 13px; margin-bottom: 8px;
    }
    .pd-info-row:last-child { margin-bottom: 0; }
    .pd-info-label { opacity: .5; }
    .pd-info-value { font-weight: 600; color: #27ae60; }

    .pd-menu { padding: 8px; }
    .pd-item {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 12px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
      transition: background .15s;
    }
    .pd-item:hover { background: rgba(255,255,255,0.06); }
    .pd-item.danger { color: #e74c3c; }
    .pd-item.danger:hover { background: rgba(231,76,60,0.1); }
    .pd-icon { font-size: 16px; width: 24px; text-align: center; }

    /* ── Transaction list ── */
    .tx-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .tx-item:last-child { border-bottom: none; }
    .tx-left { display: flex; align-items: center; gap: 12px; }
    .tx-icon {
      width: 38px; height: 38px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px;
      flex-shrink: 0;
    }
    .tx-icon.credit { background: rgba(39,174,96,0.15); }
    .tx-icon.debit  { background: rgba(231,76,60,0.12); }
    .tx-desc  { font-size: 13px; font-weight: 500; }
    .tx-date  { font-size: 11px; opacity: .4; margin-top: 2px; }
    .tx-amount { font-weight: 700; font-size: 14px; }
    .tx-amount.credit { color: #2ecc71; }
    .tx-amount.debit  { color: #e74c3c; }

    /* ── Modal base ── */
    .modal-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.65);
      z-index: 999;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .modal-overlay.open { display: flex; }

    .modal-box {
      background: #0f1c33;
      border: 1px solid rgba(255,255,255,0.08);
      padding: 30px;
      border-radius: 20px;
      width: 100%;
      max-width: 400px;
      animation: modalIn .25s ease;
    }
    @keyframes modalIn {
      from { opacity:0; transform: scale(.95); }
      to   { opacity:1; transform: scale(1); }
    }

    .modal-title {
      font-size: 18px; font-weight: 700;
      margin-bottom: 20px;
    }
    .modal-label {
      font-size: 12px; opacity: .6;
      margin-bottom: 6px; margin-top: 14px;
      text-transform: uppercase; letter-spacing: .4px;
    }
    .modal-input {
      width: 100%;
      background: rgba(255,255,255,0.06);
      color: white;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 14px;
      outline: none;
      transition: border-color .2s;
    }
    .modal-input:focus { border-color: rgba(39,174,96,0.5); }
    .modal-input option { background: #0f1c33; }

    .modal-error {
      display: none;
      color: #e74c3c;
      font-size: 12px;
      margin-top: 10px;
      background: rgba(231,76,60,0.1);
      border: 1px solid rgba(231,76,60,0.3);
      padding: 8px 12px;
      border-radius: 8px;
    }
    .modal-error.show { display: block; }

    .modal-success {
      display: none;
      color: #2ecc71;
      font-size: 12px;
      margin-top: 10px;
      background: rgba(39,174,96,0.1);
      border: 1px solid rgba(39,174,96,0.3);
      padding: 8px 12px;
      border-radius: 8px;
    }
    .modal-success.show { display: block; }

    .modal-btns {
      display: flex; gap: 10px;
      margin-top: 22px;
    }
    .modal-btns button {
      flex: 1; padding: 12px;
      border-radius: 10px;
      border: none; cursor: pointer;
      font-size: 14px; font-weight: 600;
    }
    .btn-cancel { background: rgba(255,255,255,0.07); color: white; }
    .btn-submit { background: #27ae60; color: white; }
    .btn-submit:disabled { opacity: .5; cursor: not-allowed; }

    /* ── "Coming Soon" badge ── */
    .coming-soon-note {
      font-size: 12px; opacity: .5;
      text-align: center; margin-top: 16px;
    }

    /* ── Settings modal ── */
    .settings-section { margin-bottom: 20px; }
    .settings-title {
      font-size: 11px; opacity: .4;
      text-transform: uppercase; letter-spacing: .5px;
      margin-bottom: 10px;
    }
    .settings-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      font-size: 14px;
    }
    .settings-row:last-child { border-bottom: none; }
    .toggle {
      width: 40px; height: 22px;
      background: rgba(255,255,255,0.1);
      border-radius: 11px;
      position: relative; cursor: pointer;
      transition: background .2s;
    }
    .toggle.on { background: #27ae60; }
    .toggle::after {
      content: '';
      position: absolute;
      width: 16px; height: 16px;
      background: white;
      border-radius: 50%;
      top: 3px; left: 3px;
      transition: left .2s;
    }
    .toggle.on::after { left: 21px; }
  </style>
</head>

<body>

<div class="page active" id="dashboardPage">

  <header>
    <div class="logo">⭐ Stella Bank</div>
    <a href="admin/dashboard.php"><button id="adminbtn">Admin</button></a>
    
    <div class="profile">
      <div class="avatar" id="avatarBtn" onclick="toggleProfileDropdown()"><?php echo $initial; ?></div>
      <span><?php echo htmlspecialchars($user['name']); ?></span>

      <!-- Profile Dropdown -->
      <div class="profile-dropdown" id="profileDropdown">
        <div class="pd-header">
          <div class="pd-avatar"><?php echo $initial; ?></div>
          <div class="pd-name"><?php echo htmlspecialchars($user['name']); ?></div>
          <div class="pd-email"><?php echo htmlspecialchars($user['email']); ?></div>
          <div class="pd-since">Member since <?php echo $memberSince; ?></div>
        </div>

        <div class="pd-info">
          <div class="pd-info-row">
            <span class="pd-info-label">Account Balance</span>
            <span class="pd-info-value" id="pdBalance">₦<?php echo number_format($user['balance'], 2); ?></span>
            <p style="font-size:12px; opacity:.5; margin-top:4px; cursor:pointer;" onclick="speak('Your available balance is ' + document.getElementById('balance').textContent)">
  🔊 Tap to hear balance
</p>
          </div>
          <div class="pd-info-row">
            <span class="pd-info-label">Account ID</span>
            <span class="pd-info-value">#<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></span>
          </div>
        </div>

        <div class="pd-menu">
          <div class="pd-item" onclick="openModal('settingsModal'); closeProfileDropdown()">
            <span class="pd-icon">⚙️</span> Settings
          </div>
          <div class="pd-item" onclick="openModal('profileModal'); closeProfileDropdown()">
            <span class="pd-icon">👤</span> Edit Profile
          </div>
          <div class="pd-item" onclick="openModal('securityModal'); closeProfileDropdown()">
            <span class="pd-icon">🔒</span> Security
          </div>
          <div class="pd-item danger" onclick="window.location='logout.php'">
            <span class="pd-icon">🚪</span> Logout
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="balance-card">
    <p><?php echo $greeting; ?>, <strong><?php echo htmlspecialchars($firstName); ?></strong> 👋</p>
    <p style="margin-top:8px; opacity:.7; font-size:13px;">Available Balance</p>
    <h1 id="balance">₦<?php echo number_format($user['balance'], 2); ?></h1>
    <p style="font-size:12px; opacity:.45; margin-top:6px; font-family:monospace; letter-spacing:2px;">
      Acct No: <strong><?php echo htmlspecialchars($user['account_number']); ?></strong>
    </p>
    <div class="balance-actions">
      <button class="fund" onclick="payWithPaystack()">+ Fund Account</button>
      <button class="send" onclick="openModal('transferModal')">↑ Send Money</button>
    </div>
  </div>

  <div class="dashboard-grid">
    <div class="card">
      <h3 style="margin-bottom:15px;">Quick Actions</h3>
      <div class="quick-grid">
        <button style="color:white;" class="quick" onclick="openModal('transferModal')">💸<span>Transfer</span></button>
        <button style="color:white;" class="quick" onclick="openModal('airtimeModal')">📱<span>Airtime</span></button>
        <button style="color:white;" class="quick" onclick="openModal('dataModal')">🌐<span>Data</span></button>
        <button style="color:white;" class="quick" onclick="openModal('electricityModal')">⚡<span>Electricity</span></button>
        <button style="color:white;" class="quick" onclick="openModal('cableModal')">📺<span>Cable TV</span></button>
        <button style="color:white;" class="quick" onclick="openModal('rentModal')">🏠<span>Rent</span></button>
      </div>
    </div>

    <div class="card">
      <h3 style="margin-bottom:15px;">Recent Transactions</h3>
      <div id="transactions">
        <?php if (empty($transactions)): ?>
          <p style="opacity:.5; font-size:13px; text-align:center; padding:20px 0;">No transactions yet.</p>
        <?php else: ?>
          <?php foreach ($transactions as $tx):
            $isCredit = $tx['type'] === 'credit';
            $icon     = $isCredit ? '⬇️' : '⬆️';
            $date     = date('d M Y, h:i A', strtotime($tx['created_at']));
          ?>
          <div class="tx-item">
            <div class="tx-left">
              <div class="tx-icon <?php echo $tx['type']; ?>"><?php echo $icon; ?></div>
              <div>
                <div class="tx-desc"><?php echo htmlspecialchars($tx['description'] ?: ucfirst($tx['type'])); ?></div>
                <div class="tx-date"><?php echo $date; ?></div>
              </div>
            </div>
            <div class="tx-amount <?php echo $tx['type']; ?>">
              <?php echo $isCredit ? '+' : '-'; ?>₦<?php echo number_format($tx['amount'], 2); ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="userEmailMeta" value="<?php echo htmlspecialchars($user['email']); ?>">

<!-- ═══════════════════════════════════════════════
     TRANSFER MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="transferModal">
  <div class="modal-box">
    <div class="modal-title">↑ Send Money</div>

    <div class="modal-label">From Account (Yours)</div>
    <input class="modal-input" type="text" id="senderAccountDisplay"
           value="<?php echo htmlspecialchars($user['account_number']); ?>"
           disabled
           style="opacity:.5; cursor:not-allowed; font-family:monospace; letter-spacing:2px;">

    <div class="modal-label">Recipient Account Number</div>
    <input class="modal-input" type="text" id="recipientAccount"
           placeholder="Enter 10-digit account number"
           maxlength="10"
           oninput="this.value=this.value.replace(/\D/g,'')">

    <div class="modal-label">Amount (₦)</div>
    <input class="modal-input" type="number" id="transferAmount" placeholder="Enter amount" min="1">

    <div class="modal-error"   id="transferError"></div>
    <div class="modal-success" id="transferSuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('transferModal')">Cancel</button>
      <button class="btn-submit" id="transferBtn" onclick="submitTransfer()">Send Money</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     AIRTIME MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="airtimeModal">
  <div class="modal-box">
    <div class="modal-title">📱 Buy Airtime</div>
    <div class="modal-label">Network</div>
    <select class="modal-input" id="airtimeNetwork">
      <option value="">Select Network</option>
      <option>MTN</option>
      <option>Airtel</option>
      <option>Glo</option>
      <option>9mobile</option>
    </select>
    <div class="modal-label">Phone Number</div>
    <input class="modal-input" type="tel" id="airtimePhone" placeholder="08XXXXXXXXX" maxlength="11">
    <div class="modal-label">Amount (₦)</div>
    <select class="modal-input" id="airtimeAmount">
      <option value="">Select Amount</option>
      <option value="50">₦50</option>
      <option value="100">₦100</option>
      <option value="200">₦200</option>
      <option value="500">₦500</option>
      <option value="1000">₦1,000</option>
      <option value="2000">₦2,000</option>
      <option value="5000">₦5,000</option>
    </select>
    <div class="modal-error"   id="airtimeError"></div>
    <div class="modal-success" id="airtimeSuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('airtimeModal')">Cancel</button>
      <button class="btn-submit" onclick="submitBill('airtime')">Buy Airtime</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     DATA MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="dataModal">
  <div class="modal-box">
    <div class="modal-title">🌐 Buy Data</div>
    <div class="modal-label">Network</div>
    <select class="modal-input" id="dataNetwork">
      <option value="">Select Network</option>
      <option>MTN</option>
      <option>Airtel</option>
      <option>Glo</option>
      <option>9mobile</option>
    </select>
    <div class="modal-label">Phone Number</div>
    <input class="modal-input" type="tel" id="dataPhone" placeholder="08XXXXXXXXX" maxlength="11">
    <div class="modal-label">Data Bundle</div>
    <select class="modal-input" id="dataBundle">
      <option value="">Select Bundle</option>
      <option value="100">100MB — ₦100</option>
      <option value="200">500MB — ₦200</option>
      <option value="500">1GB — ₦500</option>
      <option value="1000">2GB — ₦1,000</option>
      <option value="2000">5GB — ₦2,000</option>
      <option value="3500">10GB — ₦3,500</option>
      <option value="6000">20GB — ₦6,000</option>
    </select>
    <div class="modal-error"   id="dataError"></div>
    <div class="modal-success" id="dataSuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('dataModal')">Cancel</button>
      <button class="btn-submit" onclick="submitBill('data')">Buy Data</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     ELECTRICITY MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="electricityModal">
  <div class="modal-box">
    <div class="modal-title">⚡ Pay Electricity</div>
    <div class="modal-label">Disco (Provider)</div>
    <select class="modal-input" id="electricityDisco">
      <option value="">Select Provider</option>
      <option>EKEDC (Eko)</option>
      <option>IKEDC (Ikeja)</option>
      <option>AEDC (Abuja)</option>
      <option>PHEDC (Port Harcourt)</option>
      <option>KEDCO (Kano)</option>
      <option>IBEDC (Ibadan)</option>
      <option>EEDC (Enugu)</option>
    </select>
    <div class="modal-label">Meter Number</div>
    <input class="modal-input" type="text" id="electricityMeter" placeholder="Enter meter number">
    <div class="modal-label">Meter Type</div>
    <select class="modal-input" id="electricityType">
      <option value="prepaid">Prepaid</option>
      <option value="postpaid">Postpaid</option>
    </select>
    <div class="modal-label">Amount (₦)</div>
    <input class="modal-input" type="number" id="electricityAmount" placeholder="Min ₦500" min="500">
    <div class="modal-error"   id="electricityError"></div>
    <div class="modal-success" id="electricitySuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('electricityModal')">Cancel</button>
      <button class="btn-submit" onclick="submitBill('electricity')">Pay Now</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     CABLE TV MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="cableModal">
  <div class="modal-box">
    <div class="modal-title">📺 Cable TV</div>
    <div class="modal-label">Provider</div>
    <select class="modal-input" id="cableProvider" onchange="updateCablePlans()">
      <option value="">Select Provider</option>
      <option value="dstv">DStv</option>
      <option value="gotv">GOtv</option>
      <option value="startimes">Startimes</option>
    </select>
    <div class="modal-label">Smart Card / IUC Number</div>
    <input class="modal-input" type="text" id="cableCard" placeholder="Enter card number">
    <div class="modal-label">Subscription Plan</div>
    <select class="modal-input" id="cablePlan">
      <option value="">Select Provider First</option>
    </select>
    <div class="modal-error"   id="cableError"></div>
    <div class="modal-success" id="cableSuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('cableModal')">Cancel</button>
      <button class="btn-submit" onclick="submitBill('cable')">Subscribe</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     RENT MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="rentModal">
  <div class="modal-box">
    <div class="modal-title">🏠 Pay Rent</div>
    <div class="modal-label">Landlord / Agent Email</div>
    <input class="modal-input" type="email" id="rentEmail" placeholder="landlord@email.com">
    <div class="modal-label">Property Address</div>
    <input class="modal-input" type="text" id="rentAddress" placeholder="Enter property address">
    <div class="modal-label">Amount (₦)</div>
    <input class="modal-input" type="number" id="rentAmount" placeholder="Enter rent amount" min="1000">
    <div class="modal-label">Note (optional)</div>
    <input class="modal-input" type="text" id="rentNote" placeholder="e.g. January 2025 rent">
    <div class="modal-error"   id="rentError"></div>
    <div class="modal-success" id="rentSuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('rentModal')">Cancel</button>
      <button class="btn-submit" onclick="submitBill('rent')">Pay Rent</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     PROFILE / EDIT MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="profileModal">
  <div class="modal-box">
    <div class="modal-title">👤 Edit Profile</div>
    <div class="modal-label">Full Name</div>
    <input class="modal-input" type="text" id="editName" value="<?php echo htmlspecialchars($user['name']); ?>">
    <div class="modal-label">Email Address</div>
    <input class="modal-input" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="opacity:.4; cursor:not-allowed;">
    <p style="font-size:11px; opacity:.4; margin-top:6px;">Email cannot be changed for security reasons.</p>
    <div class="modal-label">Phone Number</div>
    <input class="modal-input" type="tel" id="editPhone" placeholder="08XXXXXXXXX">
    <div class="modal-error"   id="profileError"></div>
    <div class="modal-success" id="profileSuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('profileModal')">Cancel</button>
      <button class="btn-submit" onclick="saveProfile()">Save Changes</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     SECURITY MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="securityModal">
  <div class="modal-box">
    <div class="modal-title">🔒 Security</div>
    <div class="modal-label">Current Password</div>
    <input class="modal-input" type="password" id="currentPwd" placeholder="Enter current password">
    <div class="modal-label">New Password</div>
    <input class="modal-input" type="password" id="newPwd" placeholder="Min 6 characters">
    <div class="modal-label">Confirm New Password</div>
    <input class="modal-input" type="password" id="confirmPwd" placeholder="Repeat new password">
    <div class="modal-error"   id="securityError"></div>
    <div class="modal-success" id="securitySuccess"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('securityModal')">Cancel</button>
      <button class="btn-submit" onclick="changePassword()">Update Password</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     SETTINGS MODAL
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="settingsModal">
  <div class="modal-box">
    <div class="modal-title">⚙️ Settings</div>

    <div class="settings-section">
      <div class="settings-title">Notifications</div>
      <div class="settings-row">
        <span>Transaction Alerts</span>
        <div class="toggle on" onclick="this.classList.toggle('on')"></div>
      </div>
      <div class="settings-row">
        <span>Login Alerts</span>
        <div class="toggle on" onclick="this.classList.toggle('on')"></div>
      </div>
      <div class="settings-row">
        <span>Promotional Emails</span>
        <div class="toggle" onclick="this.classList.toggle('on')"></div>
      </div>
    </div>

    <div class="settings-section">
      <div class="settings-title">Privacy</div>
      <div class="settings-row">
        <span>Hide Balance by Default</span>
        <div class="toggle" onclick="this.classList.toggle('on')"></div>
      </div>
      <div class="settings-row">
        <span>Two-Factor Authentication</span>
        <div class="toggle" onclick="this.classList.toggle('on')"></div>
      </div>
    </div>

    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('settingsModal')">Close</button>
      <button class="btn-submit" onclick="closeModal('settingsModal')">Save Settings</button>
    </div>
  </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script src="assets/js/dashboard.js"></script>
<script src="assets/js/paystack.js"></script>

<script>
// ── Modal helpers ────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  const el = document.getElementById(id);
  el.classList.remove('open');
  // Clear errors/success
  el.querySelectorAll('.modal-error, .modal-success').forEach(e => {
    e.classList.remove('show'); e.textContent = '';
  });
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

// ── Profile dropdown ─────────────────────────────
function toggleProfileDropdown() {
  document.getElementById('profileDropdown').classList.toggle('open');
}
function closeProfileDropdown() {
  document.getElementById('profileDropdown').classList.remove('open');
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.profile')) closeProfileDropdown();
});

// ── Transfer ─────────────────────────────────────
function submitTransfer() {
  const toAccount = document.getElementById('recipientAccount').value.trim();
  const amount    = parseFloat(document.getElementById('transferAmount').value);
  const errEl     = document.getElementById('transferError');
  const sucEl     = document.getElementById('transferSuccess');
  const btn       = document.getElementById('transferBtn');

  errEl.classList.remove('show'); sucEl.classList.remove('show');

  if (!toAccount || toAccount.length !== 10) {
    errEl.textContent = 'Please enter a valid 10-digit account number.';
    errEl.classList.add('show'); return;
  }
  if (!amount || amount <= 0) {
    errEl.textContent = 'Please enter a valid amount.';
    errEl.classList.add('show'); return;
  }

  btn.disabled = true; btn.textContent = 'Sending...';

  fetch('transfer.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ to_account: toAccount, amount })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      sucEl.textContent =
        `✅ ₦${amount.toLocaleString()} sent to ${data.recipient_name}!\n` +
        `From: ${data.from_account}  →  To: ${data.to_account}\nRef: ${data.reference}`;
      sucEl.style.whiteSpace = 'pre-line';
      sucEl.classList.add('show');
      updateBalance(data.new_balance);
      addTransactionToList('debit', amount,
        `Transfer to ${data.recipient_name} — Acct: ${data.to_account}`);
      document.getElementById('recipientAccount').value = '';
      document.getElementById('transferAmount').value   = '';
      setTimeout(() => closeModal('transferModal'), 3000);
    } else {
      errEl.textContent = data.error || 'Transfer failed.';
      errEl.classList.add('show');
    }
  })
  .catch(() => {
    errEl.textContent = 'Network error. Please try again.';
    errEl.classList.add('show');
  })
  .finally(() => {
    btn.disabled = false; btn.textContent = 'Send Money';
  });
}

// ── Bill payments (Airtime, Data, Electricity, Cable, Rent) ──
function submitBill(type) {
  const configs = {
    airtime: {
      fields: ['airtimeNetwork','airtimePhone','airtimeAmount'],
      labels: ['Network','Phone','Amount'],
      getAmount: () => parseFloat(document.getElementById('airtimeAmount').value),
      getDesc: () => `${document.getElementById('airtimeNetwork').value} Airtime — ${document.getElementById('airtimePhone').value}`,
      modal: 'airtimeModal'
    },
    data: {
      fields: ['dataNetwork','dataPhone','dataBundle'],
      labels: ['Network','Phone','Bundle'],
      getAmount: () => parseFloat(document.getElementById('dataBundle').value),
      getDesc: () => `${document.getElementById('dataNetwork').value} Data — ${document.getElementById('dataPhone').value}`,
      modal: 'dataModal'
    },
    electricity: {
      fields: ['electricityDisco','electricityMeter','electricityAmount'],
      labels: ['Provider','Meter','Amount'],
      getAmount: () => parseFloat(document.getElementById('electricityAmount').value),
      getDesc: () => `${document.getElementById('electricityDisco').value} — Meter: ${document.getElementById('electricityMeter').value}`,
      modal: 'electricityModal'
    },
    cable: {
      fields: ['cableProvider','cableCard','cablePlan'],
      labels: ['Provider','Card','Plan'],
      getAmount: () => parseFloat(document.getElementById('cablePlan').value),
      getDesc: () => `${document.getElementById('cableProvider').value.toUpperCase()} — Card: ${document.getElementById('cableCard').value}`,
      modal: 'cableModal'
    },
    rent: {
      fields: ['rentEmail','rentAddress','rentAmount'],
      labels: ['Email','Address','Amount'],
      getAmount: () => parseFloat(document.getElementById('rentAmount').value),
      getDesc: () => `Rent — ${document.getElementById('rentAddress').value}`,
      modal: 'rentModal'
    }
  };

  const cfg    = configs[type];
  const errEl  = document.getElementById(type + 'Error');
  const sucEl  = document.getElementById(type + 'Success');
  errEl.classList.remove('show'); sucEl.classList.remove('show');

  // Validate all fields filled
  for (let f of cfg.fields) {
    if (!document.getElementById(f).value) {
      errEl.textContent = 'Please fill all fields.';
      errEl.classList.add('show'); return;
    }
  }

  const amount = cfg.getAmount();
  if (!amount || amount <= 0) {
    errEl.textContent = 'Invalid amount.';
    errEl.classList.add('show'); return;
  }

  // Deduct balance
  fetch('deduct_balance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ amount, description: cfg.getDesc(), reference: 'BILL-' + Date.now() })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      sucEl.textContent = `✅ Payment of ₦${amount.toLocaleString()} successful!`;
      sucEl.classList.add('show');
      updateBalance(data.new_balance);
      addTransactionToList('debit', amount, cfg.getDesc());
      setTimeout(() => closeModal(cfg.modal), 2000);
    } else {
      errEl.textContent = data.error || 'Payment failed.';
      errEl.classList.add('show');
    }
  })
  .catch(() => {
    errEl.textContent = 'Network error. Please try again.';
    errEl.classList.add('show');
  });
}

// ── Cable TV plan options ────────────────────────
function updateCablePlans() {
  const provider = document.getElementById('cableProvider').value;
  const planEl   = document.getElementById('cablePlan');
  const plans = {
    dstv: [
      ['','Select Plan'],['2900','Padi — ₦2,900'],['5800','Yanga — ₦5,800'],
      ['9300','Confam — ₦9,300'],['14750','Compact — ₦14,750'],
      ['24900','Compact+ — ₦24,900'],['37500','Premium — ₦37,500']
    ],
    gotv: [
      ['','Select Plan'],['1575','Smallie — ₦1,575'],['2700','Jinja — ₦2,700'],
      ['4850','Jolli — ₦4,850'],['6400','Max — ₦6,400'],['15200','Supa — ₦15,200']
    ],
    startimes: [
      ['','Select Plan'],['1300','Nova — ₦1,300'],['2200','Basic — ₦2,200'],
      ['2800','Smart — ₦2,800'],['4200','Classic — ₦4,200'],['6200','Super — ₦6,200']
    ]
  };
  planEl.innerHTML = '';
  (plans[provider] || [['','Select Provider First']]).forEach(([val, label]) => {
    const opt = document.createElement('option');
    opt.value = val; opt.textContent = label;
    planEl.appendChild(opt);
  });
}

// ── Profile save (UI demo) ───────────────────────
function saveProfile() {
  const sucEl = document.getElementById('profileSuccess');
  sucEl.textContent = '✅ Profile updated successfully!';
  sucEl.classList.add('show');
  setTimeout(() => closeModal('profileModal'), 1800);
}

// ── Change password ──────────────────────────────
function changePassword() {
  const cur  = document.getElementById('currentPwd').value;
  const nw   = document.getElementById('newPwd').value;
  const con  = document.getElementById('confirmPwd').value;
  const errEl = document.getElementById('securityError');
  const sucEl = document.getElementById('securitySuccess');
  errEl.classList.remove('show'); sucEl.classList.remove('show');

  if (!cur || !nw || !con) {
    errEl.textContent = 'Please fill all fields.'; errEl.classList.add('show'); return;
  }
  if (nw.length < 6) {
    errEl.textContent = 'New password must be at least 6 characters.'; errEl.classList.add('show'); return;
  }
  if (nw !== con) {
    errEl.textContent = 'Passwords do not match.'; errEl.classList.add('show'); return;
  }
  sucEl.textContent = '✅ Password updated successfully!';
  sucEl.classList.add('show');
  setTimeout(() => closeModal('securityModal'), 1800);
}

// ── Balance updater ──────────────────────────────
function updateBalance(newBalance) {
  const fmt = '₦' + parseFloat(newBalance).toLocaleString('en-NG', { minimumFractionDigits: 2 });
  document.getElementById('balance').textContent = fmt;
  const pdBal = document.getElementById('pdBalance');
  if (pdBal) pdBal.textContent = fmt;
}

// ── Add transaction to list dynamically ─────────
function addTransactionToList(type, amount, description) {
  const container = document.getElementById('transactions');
  const noTx = container.querySelector('p');
  if (noTx) noTx.remove();

  const icon = type === 'credit' ? '⬇️' : '⬆️';
  const sign = type === 'credit' ? '+' : '-';
  const now  = new Date().toLocaleDateString('en-GB', {
    day:'2-digit', month:'short', year:'numeric',
    hour:'2-digit', minute:'2-digit'
  });

  const div = document.createElement('div');
  div.className = 'tx-item';
  div.innerHTML = `
    <div class="tx-left">
      <div class="tx-icon ${type}">${icon}</div>
      <div>
        <div class="tx-desc">${description}</div>
        <div class="tx-date">${now}</div>
      </div>
    </div>
    <div class="tx-amount ${type}">${sign}₦${parseFloat(amount).toLocaleString('en-NG', {minimumFractionDigits:2})}</div>
  `;
  container.insertBefore(div, container.firstChild);
}

// ── Legacy support for paystack.js ──────────────
function openTransferModal()  { openModal('transferModal'); }
function closeTransferModal() { closeModal('transferModal'); }
</script>


<script>
// ── TTS Engine ───────────────────────────────────
function speak(text, rate = 1, pitch = 1) {
  if (!window.speechSynthesis) return;
  window.speechSynthesis.cancel(); // stop any current speech
  const utter = new SpeechSynthesisUtterance(text);
  utter.rate  = rate;
  utter.pitch = pitch;
  utter.volume = 1;
  window.speechSynthesis.speak(utter);
}

// ── 1. Speak on Login (when dashboard loads) ────
window.addEventListener('load', () => {
  const greeting  = <?php echo json_encode($greeting); ?>;
  const firstName = <?php echo json_encode($firstName); ?>;
  const balance   = <?php echo json_encode(number_format($user['balance'], 2)); ?>;

  setTimeout(() => {
    speak(`${greeting}, ${firstName}! Welcome back to Stella Bank. Your current balance is ₦${balance}.`);
  }, 800); // slight delay so page is fully ready
});

// ── 2. Speak balance when balance card is clicked ──
document.getElementById('balance').style.cursor = 'pointer';
document.getElementById('balance').title = 'Click to hear your balance';
document.getElementById('balance').addEventListener('click', () => {
  const bal = document.getElementById('balance').textContent;
  speak(`Your available balance is ${bal}`);
});

// ── 3. Speak after transfer or bill payment ──────
// Wrap the original updateBalance to also speak
const _originalUpdateBalance = updateBalance;
window.updateBalance = function(newBalance) {
  _originalUpdateBalance(newBalance);
  const fmt = parseFloat(newBalance).toLocaleString('en-NG', { minimumFractionDigits: 2 });
  speak(`Transaction successful. Your new balance is ₦${fmt}`);
};
</script>

</body>
</html>