<?php
require_once __DIR__ . '/auth_guard.php';
include    __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

requireSuperAdmin();   // only superadmin may create other admins

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$data     = json_decode(file_get_contents("php://input"), true);
$name     = trim($data['name']     ?? '');
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');
$role     = trim($data['role']     ?? 'admin');

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(["error" => "All fields are required."]);
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["error" => "Invalid email address."]);
    exit();
}
if (strlen($password) < 8) {
    echo json_encode(["error" => "Password must be at least 8 characters."]);
    exit();
}
if (!in_array($role, ['admin', 'superadmin'])) {
    $role = 'admin';
}

// Duplicate check
$dup = $conn->prepare("SELECT id FROM admins WHERE email = ?");
$dup->bind_param("s", $email);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $dup->close();
    echo json_encode(["error" => "An admin with this email already exists."]);
    exit();
}
$dup->close();

$hashed = password_hash($password, PASSWORD_DEFAULT);
$ins    = $conn->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?)");
$ins->bind_param("ssss", $name, $email, $hashed, $role);

if ($ins->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to create admin account."]);
}
$ins->close();
?>
