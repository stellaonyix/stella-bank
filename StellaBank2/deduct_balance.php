<?php
session_start();
include __DIR__ . "/config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not authenticated"]);
    exit();
}

$data        = json_decode(file_get_contents("php://input"), true);
$amount      = floatval($data['amount']      ?? 0);
$reference   = trim($data['reference']       ?? '') ?: ('BILL-' . strtoupper(bin2hex(random_bytes(4))));
$description = trim($data['description']     ?? 'Bill Payment');

if ($amount <= 0) {
    echo json_encode(["error" => "Invalid amount"]);
    exit();
}

$id = (int) $_SESSION['user_id'];

// ── Check balance ─────────────────────────────────────────────
$check = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if (!$row || (float)$row['balance'] < $amount) {
    echo json_encode(["error" => "Insufficient balance"]);
    exit();
}

// ── Deduct & log atomically ───────────────────────────────────
$conn->begin_transaction();

try {
    $upd = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $upd->bind_param("di", $amount, $id);
    $upd->execute();
    $upd->close();

    // Save transaction record — no from/to account (bill payment, not a transfer)
    $ins = $conn->prepare(
        "INSERT INTO transactions (user_id, type, amount, description, reference)
         VALUES (?, 'debit', ?, ?, ?)"
    );
    $ins->bind_param("idss", $id, $amount, $description, $reference);
    $ins->execute();
    $ins->close();

    $conn->commit();

    $q = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $newBalance = $q->get_result()->fetch_assoc()['balance'];
    $q->close();

    echo json_encode(["success" => true, "new_balance" => $newBalance]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Payment failed. Please try again."]);
}
?>
