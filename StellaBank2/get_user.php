<?php
session_start();
include __DIR__ . "/config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not authenticated"]);
    exit();
}

$id   = (int) $_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT id, name, email, account_number, balance, created_at FROM users WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["error" => "User not found"]);
    exit();
}

echo json_encode($result->fetch_assoc());
$stmt->close();
?>
