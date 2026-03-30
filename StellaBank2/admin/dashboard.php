<?php
require_once __DIR__ . '/auth_guard.php';
include    __DIR__ . '/../config/db.php';

// ── Summary stats ─────────────────────────────────────────────
$totalUsers = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$totalTx    = $conn->query("SELECT COUNT(*) c FROM transactions")->fetch_assoc()['c'];
$totalDebit = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='debit'")->fetch_assoc()['s'];
$totalCredit= $conn->query("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE type='credit'")->fetch_assoc()['s'];

// ── Users (paginated, 20/page, optional search) ───────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['search'] ?? '');
$tab     = $_GET['tab'] ?? 'overview';

if ($search) {
    $like  = "%{$search}%";
    $uStmt = $conn->prepare(
        "SELECT id,name,email,account_number,balance,created_at FROM users
         WHERE name LIKE ? OR email LIKE ? OR account_number LIKE ?
         ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $uStmt->bind_param("sssii", $like, $like, $like, $perPage, $offset);
} else {
    $uStmt = $conn->prepare(
        "SELECT id,name,email,account_number,balance,created_at FROM users
         ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $uStmt->bind_param("ii", $perPage, $offset);
}
$uStmt->execute();
$userRows = $uStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$uStmt->close();

// ── All transactions (latest 200) ─────────────────────────────
$txRows = $conn->query(
    "SELECT t.*, u.name user_name, u.account_number user_acct
     FROM transactions t JOIN users u ON u.id=t.user_id
     ORDER BY t.created_at DESC LIMIT 200"
)->fetch_all(MYSQLI_ASSOC);

// ── Admins list (superadmin only) ─────────────────────────────
$adminRows = [];
if ($adminRole === 'superadmin') {
    $adminRows = $conn->query("SELECT id,name,email,role,created_at FROM admins ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stella Bank — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root { --bg:#050d1a; --sidebar:#07101f; --card:rgba(8,20,40,0.9);
            --border:rgba(255,255,255,0.07); --red:#e74c3c; --green:#27ae60;
            --text:#e8f0fe; --muted:rgba(232,240,254,0.45); }
    html, body { height:100%; background:var(--bg); color:var(--text);
                 font-family:'DM Sans',sans-serif; font-size:14px; }
    a { color:inherit; text-decoration:none; }

    /* ── Layout ── */
    .layout { display:flex; min-height:100vh; }

    /* ── Sidebar ── */
    .sidebar { width:230px; flex-shrink:0; background:var(--sidebar);
               border-right:1px solid var(--border);
               display:flex; flex-direction:column; padding:22px 14px; position:sticky; top:0; height:100vh; }
    .sb-logo { display:flex; align-items:center; gap:10px; padding:0 8px; margin-bottom:30px; }
    .sb-logo-icon { width:34px; height:34px; background:linear-gradient(135deg,#e74c3c,#c0392b);
                    border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; }
    .sb-logo-text { font-family:'Syne',sans-serif; font-weight:800; font-size:16px; }
    .sb-logo-text span { color:#e74c3c; }
    .sb-section { font-size:10px; color:var(--muted); letter-spacing:.8px; text-transform:uppercase;
                  padding:0 8px; margin:18px 0 6px; }
    .sb-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px;
               cursor:pointer; font-size:13px; font-weight:500; transition:background .15s; color:var(--muted); }
    .sb-item:hover { background:rgba(255,255,255,0.05); color:var(--text); }
    .sb-item.active { background:rgba(231,76,60,0.12); color:#e74c3c; }
    .sb-icon { font-size:16px; width:22px; text-align:center; }
    .sb-bottom { margin-top:auto; }
    .sb-admin { display:flex; align-items:center; gap:10px; padding:12px;
                background:rgba(255,255,255,0.04); border-radius:12px; margin-bottom:10px; }
    .sb-av { width:34px; height:34px; background:linear-gradient(135deg,#e74c3c,#c0392b);
             border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; }
    .sb-admin-name { font-weight:600; font-size:13px; }
    .sb-admin-role { font-size:11px; color:var(--muted); }
    .sb-logout { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px;
                 color:#e74c3c; font-size:13px; font-weight:500; cursor:pointer; transition:background .15s; }
    .sb-logout:hover { background:rgba(231,76,60,0.1); }

    /* ── Main ── */
    .main { flex:1; overflow-y:auto; padding:28px 30px; }
    .page-title { font-family:'Syne',sans-serif; font-weight:700; font-size:22px; margin-bottom:4px; }
    .page-sub { color:var(--muted); font-size:13px; margin-bottom:28px; }

    /* ── Stat cards ── */
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
    .stat { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:20px 22px; }
    .stat-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
    .stat-value { font-family:'Syne',sans-serif; font-weight:700; font-size:24px; }
    .stat-value.green { color:var(--green); }
    .stat-value.red   { color:var(--red); }

    /* ── Section card ── */
    .section { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:22px; margin-bottom:24px; }
    .sec-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:18px; }
    .sec-title { font-family:'Syne',sans-serif; font-weight:700; font-size:16px; }

    /* ── Search form ── */
    .search-form { display:flex; gap:8px; }
    .search-input { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
                    border-radius:8px; padding:8px 14px; color:var(--text); font-size:13px; outline:none;
                    width:220px; transition:border-color .2s; }
    .search-input:focus { border-color:rgba(231,76,60,0.5); }
    .search-input::placeholder { color:var(--muted); }
    .btn-search { background:var(--red); color:#fff; border:none; border-radius:8px;
                  padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }

    /* ── Table ── */
    .tbl-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    th { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px;
         padding:8px 10px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
    td { padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.04);
         font-size:13px; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(255,255,255,0.02); }

    .badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; }
    .badge-credit { background:rgba(39,174,96,0.15); color:#2ecc71; }
    .badge-debit  { background:rgba(231,76,60,0.12); color:#e74c3c; }
    .badge-admin  { background:rgba(231,76,60,0.12); color:#e74c3c; }
    .badge-super  { background:rgba(231,76,60,0.25); color:#ff6b5b; }

    .acct { font-family:monospace; font-size:12px; color:var(--muted); }
    .text-green { color:var(--green); font-weight:600; }
    .text-red   { color:var(--red);   font-weight:600; }
    .text-muted { color:var(--muted); }

    /* ── Action buttons in table ── */
    .tbl-btn { border:none; padding:5px 12px; border-radius:6px; cursor:pointer;
               font-size:12px; font-weight:600; transition:opacity .15s; }
    .tbl-btn:hover { opacity:.8; }
    .tbl-btn-green { background:rgba(39,174,96,0.15); color:#2ecc71; }
    .tbl-btn-red   { background:rgba(231,76,60,0.12); color:#e74c3c; margin-left:4px; }

    /* ── Modal ── */
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7);
             z-index:999; align-items:center; justify-content:center; padding:20px; }
    .modal.open { display:flex; }
    .modal-box { background:#0c1829; border:1px solid rgba(255,255,255,0.08);
                 border-radius:20px; padding:28px; width:100%; max-width:460px;
                 animation:mIn .22s ease; }
    @keyframes mIn { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
    .modal-title { font-family:'Syne',sans-serif; font-weight:700; font-size:17px; margin-bottom:18px; }
    .ml { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px;
          margin-bottom:6px; margin-top:14px; }
    .mi { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
          border-radius:8px; padding:10px 12px; color:var(--text); font-size:14px; outline:none;
          transition:border-color .2s; }
    .mi:focus { border-color:rgba(231,76,60,0.5); }
    .mi:disabled { opacity:.4; cursor:not-allowed; }
    .mb { display:flex; gap:8px; margin-top:20px; }
    .mb button { flex:1; padding:11px; border-radius:8px; border:none; cursor:pointer;
                 font-size:13px; font-weight:600; transition:opacity .15s; }
    .mb button:hover { opacity:.85; }
    .btn-cancel { background:rgba(255,255,255,0.07); color:var(--text); }
    .btn-red    { background:var(--red); color:#fff; }
    .btn-green  { background:var(--green); color:#fff; }
    .msg { font-size:12px; border-radius:8px; padding:9px 12px; margin-top:10px; display:none; }
    .msg.show { display:block; }
    .msg.ok  { background:rgba(39,174,96,0.12); border:1px solid rgba(39,174,96,0.3); color:#a8f0c6; }
    .msg.err { background:rgba(231,76,60,0.12); border:1px solid rgba(231,76,60,0.3); color:#ffb3ad; }

    .empty-row td { text-align:center; padding:28px; color:var(--muted); }

    @media (max-width:760px) { .sidebar{display:none} .main{padding:16px} }
  </style>
</head>
<body>
<div class="layout">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon">🔐</div>
    <div class="sb-logo-text">Stella <span>Admin</span></div>
  </div>

  <div class="sb-section">Overview</div>
  <div class="sb-item <?php echo $tab==='overview'?'active':''; ?>" onclick="showTab('overview')">
    <span class="sb-icon">📊</span> Dashboard
  </div>

  <div class="sb-section">Management</div>
  <div class="sb-item <?php echo $tab==='users'?'active':''; ?>" onclick="showTab('users')">
    <span class="sb-icon">👥</span> Users
  </div>
  <div class="sb-item <?php echo $tab==='transactions'?'active':''; ?>" onclick="showTab('transactions')">
    <span class="sb-icon">💳</span> Transactions
  </div>
  <?php if ($adminRole === 'superadmin'): ?>
  <div class="sb-item <?php echo $tab==='admins'?'active':''; ?>" onclick="showTab('admins')">
    <span class="sb-icon">🛡️</span> Admins
  </div>
  <?php endif; ?>

  <div class="sb-bottom">
    <div class="sb-admin">
      <div class="sb-av"><?php echo strtoupper($adminName[0]); ?></div>
      <div>
        <div class="sb-admin-name"><?php echo htmlspecialchars($adminName); ?></div>
        <div class="sb-admin-role"><?php echo ucfirst($adminRole); ?></div>
      </div>
    </div>
    <a href="logout.php" class="sb-logout">
      <span class="sb-icon">🚪</span> Logout
    </a>
  </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main class="main">

  <!-- ══ OVERVIEW ══ -->
  <div id="tab-overview" class="tab-content" style="display:<?php echo $tab==='overview'?'block':'none'; ?>">
    <div class="page-title">Welcome back, <?php echo htmlspecialchars($adminName); ?> 👋</div>
    <div class="page-sub">Stella Bank admin panel — real-time overview.</div>
        <a href="index.php"><button id="adminbtn">Admin</button></a>

    <div class="stats">
      <div class="stat">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Total Transactions</div>
        <div class="stat-value"><?php echo number_format($totalTx); ?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Total Credits</div>
        <div class="stat-value green">₦<?php echo number_format($totalCredit, 2); ?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Total Debits</div>
        <div class="stat-value red">₦<?php echo number_format($totalDebit, 2); ?></div>
      </div>
    </div>

    <!-- Recent 30 transactions preview -->
    <div class="section">
      <div class="sec-header">
        <div class="sec-title">Recent Transactions</div>
        <button onclick="showTab('transactions')" style="background:rgba(231,76,60,0.15);color:#e74c3c;border:none;padding:7px 16px;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600">View All →</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>Date</th><th>User</th><th>Type</th><th>Amount</th><th>From Acct</th><th>To Acct</th><th>Description</th><th>Reference</th></tr>
          </thead>
          <tbody>
            <?php
            $recent = array_slice($txRows, 0, 15);
            foreach ($recent as $tx):
              $isCredit = $tx['type'] === 'credit';
            ?>
            <tr>
              <td class="text-muted"><?php echo date('d M Y H:i', strtotime($tx['created_at'])); ?></td>
              <td><?php echo htmlspecialchars($tx['user_name']); ?><br>
                  <span class="acct"><?php echo $tx['user_acct']; ?></span></td>
              <td><span class="badge badge-<?php echo $tx['type']; ?>"><?php echo strtoupper($tx['type']); ?></span></td>
              <td class="<?php echo $isCredit?'text-green':'text-red'; ?>">
                <?php echo ($isCredit?'+':'-').'₦'.number_format($tx['amount'],2); ?></td>
              <td class="acct"><?php echo htmlspecialchars($tx['from_account'] ?? '—'); ?></td>
              <td class="acct"><?php echo htmlspecialchars($tx['to_account']   ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($tx['description']); ?></td>
              <td class="acct"><?php echo htmlspecialchars($tx['reference']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?>
              <tr class="empty-row"><td colspan="8">No transactions yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══ USERS ══ -->
  <div id="tab-users" class="tab-content" style="display:<?php echo $tab==='users'?'block':'none'; ?>">
    <div class="page-title">Users</div>
    <div class="page-sub">All registered customer accounts.</div>

    <div class="section">
      <div class="sec-header">
        <div class="sec-title">Customer Accounts (<?php echo number_format($totalUsers); ?>)</div>
        <form class="search-form" method="GET" onsubmit="document.querySelector('[name=tab]').value='users'">
          <input type="hidden" name="tab" value="users">
          <input class="search-input" name="search" placeholder="Search name / email / account…"
                 value="<?php echo htmlspecialchars($search); ?>">
          <button type="submit" class="btn-search">Search</button>
        </form>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Account No.</th><th>Balance</th><th>Joined</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($userRows as $u): ?>
            <tr>
              <td class="text-muted"><?php echo str_pad($u['id'],4,'0',STR_PAD_LEFT); ?></td>
              <td><?php echo htmlspecialchars($u['name']); ?></td>
              <td class="text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
              <td class="acct"><?php echo $u['account_number']; ?></td>
              <td class="text-green">₦<?php echo number_format($u['balance'],2); ?></td>
              <td class="text-muted"><?php echo date('d M Y',strtotime($u['created_at'])); ?></td>
              <td>
                <button class="tbl-btn tbl-btn-green"
                  onclick="openAdjust(<?php echo $u['id']; ?>,'<?php echo addslashes($u['name']); ?>','credit')">
                  ＋ Credit
                </button>
                <?php if ($adminRole === 'superadmin'): ?>
                <button class="tbl-btn tbl-btn-red"
                  onclick="openAdjust(<?php echo $u['id']; ?>,'<?php echo addslashes($u['name']); ?>','debit')">
                  − Debit
                </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($userRows)): ?>
              <tr class="empty-row"><td colspan="7">No users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══ TRANSACTIONS ══ -->
  <div id="tab-transactions" class="tab-content" style="display:<?php echo $tab==='transactions'?'block':'none'; ?>">
    <div class="page-title">All Transactions</div>
    <div class="page-sub">Complete audit trail — every debit and credit including from/to account numbers.</div>

    <div class="section">
      <div class="sec-header">
        <div class="sec-title">Transaction Ledger (<?php echo number_format($totalTx); ?> total)</div>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Date</th><th>User</th><th>Type</th><th>Amount</th>
                <th>From Account</th><th>To Account</th><th>Description</th><th>Reference</th></tr>
          </thead>
          <tbody>
            <?php foreach ($txRows as $tx):
              $isCredit = $tx['type'] === 'credit';
            ?>
            <tr>
              <td class="text-muted"><?php echo str_pad($tx['id'],5,'0',STR_PAD_LEFT); ?></td>
              <td class="text-muted"><?php echo date('d M Y H:i', strtotime($tx['created_at'])); ?></td>
              <td>
                <?php echo htmlspecialchars($tx['user_name']); ?><br>
                <span class="acct"><?php echo $tx['user_acct']; ?></span>
              </td>
              <td><span class="badge badge-<?php echo $tx['type']; ?>"><?php echo strtoupper($tx['type']); ?></span></td>
              <td class="<?php echo $isCredit?'text-green':'text-red'; ?>">
                <?php echo ($isCredit?'+':'-').'₦'.number_format($tx['amount'],2); ?>
              </td>
              <td class="acct"><?php echo htmlspecialchars($tx['from_account'] ?? '—'); ?></td>
              <td class="acct"><?php echo htmlspecialchars($tx['to_account']   ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($tx['description']); ?></td>
              <td class="acct"><?php echo htmlspecialchars($tx['reference']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($txRows)): ?>
              <tr class="empty-row"><td colspan="9">No transactions yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══ ADMINS (superadmin only) ══ -->
  <?php if ($adminRole === 'superadmin'): ?>
  <div id="tab-admins" class="tab-content" style="display:<?php echo $tab==='admins'?'block':'none'; ?>">
    <div class="page-title">Admin Accounts</div>
    <div class="page-sub">Manage admin access. Only superadmins can see this section.</div>

    <div class="section">
      <div class="sec-header">
        <div class="sec-title">Admins (<?php echo count($adminRows); ?>)</div>
        <button onclick="document.getElementById('addAdminModal').classList.add('open')"
                style="background:var(--red);color:#fff;border:none;border-radius:8px;
                       padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer">
          + Add Admin
        </button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($adminRows as $a): ?>
            <tr>
              <td class="text-muted"><?php echo str_pad($a['id'],3,'0',STR_PAD_LEFT); ?></td>
              <td><?php echo htmlspecialchars($a['name']); ?></td>
              <td class="text-muted"><?php echo htmlspecialchars($a['email']); ?></td>
              <td>
                <span class="badge <?php echo $a['role']==='superadmin'?'badge-super':'badge-admin'; ?>">
                  <?php echo strtoupper($a['role']); ?>
                </span>
              </td>
              <td class="text-muted"><?php echo date('d M Y',strtotime($a['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</main>
</div>

<!-- ══ ADJUST BALANCE MODAL ══ -->
<div class="modal" id="adjustModal">
  <div class="modal-box">
    <div class="modal-title" id="adjustTitle">Adjust Balance</div>
    <input type="hidden" id="adjustUserId">
    <input type="hidden" id="adjustType">
    <div class="ml">User</div>
    <input class="mi" id="adjustUserName" disabled>
    <div class="ml">Amount (₦)</div>
    <input class="mi" type="number" id="adjustAmount" placeholder="Enter amount" min="1">
    <div class="ml">Description / Reason</div>
    <input class="mi" id="adjustDesc" placeholder="e.g. Manual top-up / Reversal">
    <div class="msg" id="adjustMsg"></div>
    <div class="mb">
      <button class="btn-cancel" onclick="closeModal('adjustModal')">Cancel</button>
      <button id="adjustSubmit" onclick="submitAdjust()">Confirm</button>
    </div>
  </div>
</div>

<!-- ══ ADD ADMIN MODAL (superadmin only) ══ -->
<?php if ($adminRole === 'superadmin'): ?>
<div class="modal" id="addAdminModal">
  <div class="modal-box">
    <div class="modal-title">🛡️ Create Admin Account</div>
    <div class="ml">Full Name</div>
    <input class="mi" id="newAdminName" placeholder="Admin Name">
    <div class="ml">Email</div>
    <input class="mi" type="email" id="newAdminEmail" placeholder="admin@stellabank.com">
    <div class="ml">Password (min 8 chars)</div>
    <input class="mi" type="password" id="newAdminPwd" placeholder="••••••••">
    <div class="ml">Role</div>
    <select class="mi" id="newAdminRole">
      <option value="admin">Admin</option>
      <option value="superadmin">Super Admin</option>
    </select>
    <div class="msg" id="addAdminMsg"></div>
    <div class="mb">
      <button class="btn-cancel" onclick="closeModal('addAdminModal')">Cancel</button>
      <button class="btn-red" onclick="submitAddAdmin()">Create Admin</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ── Tab switching ─────────────────────────────────────────
function showTab(name) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  const el = document.getElementById('tab-' + name);
  if (el) el.style.display = 'block';

  document.querySelectorAll('.sb-item').forEach(item => item.classList.remove('active'));
  document.querySelectorAll('.sb-item').forEach(item => {
    if (item.getAttribute('onclick')?.includes("'" + name + "'")) item.classList.add('active');
  });
}

// ── Modal helpers ─────────────────────────────────────────
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.modal').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Open adjust (credit/debit) modal ─────────────────────
function openAdjust(userId, userName, type) {
  document.getElementById('adjustUserId').value  = userId;
  document.getElementById('adjustUserName').value = userName;
  document.getElementById('adjustType').value    = type;
  document.getElementById('adjustAmount').value  = '';
  document.getElementById('adjustDesc').value    = '';
  document.getElementById('adjustMsg').className = 'msg';
  document.getElementById('adjustTitle').textContent =
    type === 'credit' ? '➕ Credit User Balance' : '➖ Debit User Balance';
  const btn = document.getElementById('adjustSubmit');
  btn.className = type === 'credit' ? 'btn-green' : 'btn-red';
  btn.textContent = type === 'credit' ? 'Credit' : 'Debit';
  document.getElementById('adjustModal').classList.add('open');
}

// ── Submit balance adjustment ─────────────────────────────
function submitAdjust() {
  const userId = document.getElementById('adjustUserId').value;
  const type   = document.getElementById('adjustType').value;
  const amount = parseFloat(document.getElementById('adjustAmount').value);
  const desc   = document.getElementById('adjustDesc').value.trim() ||
                 (type === 'credit' ? 'Admin credit' : 'Admin debit');
  const msgEl  = document.getElementById('adjustMsg');

  if (!amount || amount <= 0) {
    msgEl.textContent = 'Please enter a valid amount.';
    msgEl.className = 'msg err show'; return;
  }

  fetch('adjust_balance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, type, amount, description: desc })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      msgEl.textContent = `✅ Done! New balance: ₦${parseFloat(d.new_balance).toLocaleString('en-NG',{minimumFractionDigits:2})}`;
      msgEl.className = 'msg ok show';
      setTimeout(() => { closeModal('adjustModal'); location.reload(); }, 1800);
    } else {
      msgEl.textContent = d.error || 'Failed.';
      msgEl.className = 'msg err show';
    }
  })
  .catch(() => { msgEl.textContent = 'Network error.'; msgEl.className = 'msg err show'; });
}

// ── Create admin ──────────────────────────────────────────
function submitAddAdmin() {
  const name  = document.getElementById('newAdminName').value.trim();
  const email = document.getElementById('newAdminEmail').value.trim();
  const pwd   = document.getElementById('newAdminPwd').value;
  const role  = document.getElementById('newAdminRole').value;
  const msgEl = document.getElementById('addAdminMsg');

  if (!name || !email || !pwd) {
    msgEl.textContent = 'All fields are required.'; msgEl.className = 'msg err show'; return;
  }
  if (pwd.length < 8) {
    msgEl.textContent = 'Password must be at least 8 characters.'; msgEl.className = 'msg err show'; return;
  }

  fetch('create_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password: pwd, role })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      msgEl.textContent = '✅ Admin created!'; msgEl.className = 'msg ok show';
      setTimeout(() => { closeModal('addAdminModal'); location.reload(); }, 1800);
    } else {
      msgEl.textContent = d.error || 'Failed.'; msgEl.className = 'msg err show';
    }
  })
  .catch(() => { msgEl.textContent = 'Network error.'; msgEl.className = 'msg err show'; });
}
</script>
</body>
</html>
