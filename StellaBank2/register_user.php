<?php
session_start();
include __DIR__ . "/config/db.php";

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$data     = json_decode(file_get_contents("php://input"), true);
$name     = trim($data['name']     ?? '');
$email    = trim($data['email']    ?? '');
$password =      $data['password'] ?? '';

if (!$name || !$email || !$password) {
    echo json_encode(["error" => "All fields are required"]);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(["error" => "Password must be at least 6 characters"]);
    exit();
}

// Check duplicate email
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(["error" => "Email already registered"]);
    $check->close();
    exit();
}
$check->close();

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt   = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashed);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Registration successful"]);
} else {
    echo json_encode(["error" => "Registration failed: " . $conn->error]);
}
$stmt->close();
?>
