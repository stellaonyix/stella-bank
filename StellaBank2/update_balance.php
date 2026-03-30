<?php
session_start();
include __DIR__ . "/config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not authenticated"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$data        = json_decode(file_get_contents("php://input"), true);
$amount      = floatval($data['amount']      ?? 0);
$reference   = trim($data['reference']       ?? '');
$description = trim($data['description']     ?? 'Account Funding via Paystack');

if ($amount <= 0) {
    echo json_encode(["error" => "Invalid amount"]);
    exit();
}

if (empty($reference)) {
    echo json_encode(["error" => "Payment reference is required"]);
    exit();
}

$id = (int) $_SESSION['user_id'];

// ── Credit & log atomically ───────────────────────────────────
$conn->begin_transaction();

try {
    $upd = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $upd->bind_param("di", $amount, $id);
    $upd->execute();
    $upd->close();

    $ins = $conn->prepare(
        "INSERT INTO transactions (user_id, type, amount, description, reference)
         VALUES (?, 'credit', ?, ?, ?)"
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
    echo json_encode(["error" => "Failed to update balance"]);
}
?>
