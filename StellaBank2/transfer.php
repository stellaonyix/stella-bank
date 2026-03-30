<?php
session_start();
include __DIR__ . "/config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not authenticated"]);
    exit();
}

$data      = json_decode(file_get_contents("php://input"), true);
$toAccount = trim($data['to_account'] ?? '');   // recipient's 10-digit account number
$amount    = floatval($data['amount']  ?? 0);
$reference = trim($data['reference']  ?? '') ?: ('TRF-' . strtoupper(bin2hex(random_bytes(4))));

// ── Validate ──────────────────────────────────────────────────
if (empty($toAccount) || !preg_match('/^\d{10}$/', $toAccount)) {
    echo json_encode(["error" => "Please enter a valid 10-digit account number."]);
    exit();
}
if ($amount <= 0) {
    echo json_encode(["error" => "Amount must be greater than zero."]);
    exit();
}

$senderId = (int) $_SESSION['user_id'];

// ── Fetch sender ──────────────────────────────────────────────
$s = $conn->prepare("SELECT id, name, email, account_number, balance FROM users WHERE id = ?");
$s->bind_param("i", $senderId);
$s->execute();
$sender = $s->get_result()->fetch_assoc();
$s->close();

if (!$sender) {
    echo json_encode(["error" => "Sender not found."]);
    exit();
}

// ── Block self-transfer ───────────────────────────────────────
if ($sender['account_number'] === $toAccount) {
    echo json_encode(["error" => "You cannot transfer money to your own account."]);
    exit();
}

// ── Check sufficient balance ──────────────────────────────────
if ((float)$sender['balance'] < $amount) {
    echo json_encode(["error" => "Insufficient balance."]);
    exit();
}

// ── Find recipient by account number ─────────────────────────
$r = $conn->prepare("SELECT id, name, account_number FROM users WHERE account_number = ?");
$r->bind_param("s", $toAccount);
$r->execute();
$recipient = $r->get_result()->fetch_assoc();
$r->close();

if (!$recipient) {
    echo json_encode(["error" => "No Stella Bank account found with that account number."]);
    exit();
}

// ── Execute atomically ────────────────────────────────────────
$conn->begin_transaction();

try {
    $fromAcc = $sender['account_number'];
    $toAcc   = $recipient['account_number'];

    // 1. Deduct sender balance
    $d = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $d->bind_param("di", $amount, $senderId);
    $d->execute();
    $d->close();

    // 2. Credit recipient balance
    $c = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $c->bind_param("di", $amount, $recipient['id']);
    $c->execute();
    $c->close();

    // 3. Log DEBIT transaction for sender (from_account → to_account)
    $descDebit = "Transfer to " . $recipient['name'] . " (Acct: " . $toAcc . ")";
    $t1 = $conn->prepare(
        "INSERT INTO transactions
            (user_id, type, amount, description, reference, from_account, to_account)
         VALUES (?, 'debit', ?, ?, ?, ?, ?)"
    );
    $t1->bind_param("idssss", $senderId, $amount, $descDebit, $reference, $fromAcc, $toAcc);
    $t1->execute();
    $t1->close();

    // 4. Log CREDIT transaction for recipient (from_account → to_account)
    $descCredit = "Transfer from " . $sender['name'] . " (Acct: " . $fromAcc . ")";
    $t2 = $conn->prepare(
        "INSERT INTO transactions
            (user_id, type, amount, description, reference, from_account, to_account)
         VALUES (?, 'credit', ?, ?, ?, ?, ?)"
    );
    $t2->bind_param("idssss", $recipient['id'], $amount, $descCredit, $reference, $fromAcc, $toAcc);
    $t2->execute();
    $t2->close();

    $conn->commit();

    // 5. Return sender's new balance
    $b = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $b->bind_param("i", $senderId);
    $b->execute();
    $newBalance = $b->get_result()->fetch_assoc()['balance'];
    $b->close();

    echo json_encode([
        "success"        => true,
        "new_balance"    => $newBalance,
        "recipient_name" => $recipient['name'],
        "from_account"   => $fromAcc,
        "to_account"     => $toAcc,
        "reference"      => $reference,
        "message"        => "Transfer successful"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Transfer failed. Please try again."]);
}
?>
