<?php
require_once __DIR__ . '/auth_guard.php';
include    __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$data   = json_decode(file_get_contents("php://input"), true);
$userId = (int)    ($data['user_id']     ?? 0);
$type   = trim(     $data['type']        ?? '');   // 'credit' or 'debit'
$amount = floatval( $data['amount']      ?? 0);
$desc   = trim(     $data['description'] ?? 'Admin adjustment');

// ── Authorization: only superadmin can debit ──────────────────
if ($type === 'debit') {
    requireSuperAdmin();
}

if (!in_array($type, ['credit', 'debit'])) {
    echo json_encode(["error" => "Invalid transaction type."]);
    exit();
}
if ($userId <= 0 || $amount <= 0) {
    echo json_encode(["error" => "Invalid user ID or amount."]);
    exit();
}

// ── Fetch user ────────────────────────────────────────────────
$u = $conn->prepare("SELECT id, balance FROM users WHERE id = ?");
$u->bind_param("i", $userId);
$u->execute();
$user = $u->get_result()->fetch_assoc();
$u->close();

if (!$user) {
    echo json_encode(["error" => "User not found."]);
    exit();
}

if ($type === 'debit' && (float)$user['balance'] < $amount) {
    echo json_encode(["error" => "User has insufficient balance for this debit."]);
    exit();
}

// ── Execute atomically ────────────────────────────────────────
$conn->begin_transaction();

try {
    if ($type === 'credit') {
        $upd = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    } else {
        $upd = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    }
    $upd->bind_param("di", $amount, $userId);
    $upd->execute();
    $upd->close();

    $ref = 'ADM-' . strtoupper(bin2hex(random_bytes(4)));
    $ins = $conn->prepare(
        "INSERT INTO transactions (user_id, type, amount, description, reference)
         VALUES (?, ?, ?, ?, ?)"
    );
    $ins->bind_param("isdss", $userId, $type, $amount, $desc, $ref);
    $ins->execute();
    $ins->close();

    $conn->commit();

    $q = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $q->bind_param("i", $userId);
    $q->execute();
    $newBalance = $q->get_result()->fetch_assoc()['balance'];
    $q->close();

    echo json_encode(["success" => true, "new_balance" => $newBalance]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Adjustment failed. Please try again."]);
}
?>
