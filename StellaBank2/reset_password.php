<?php
session_start();
include __DIR__ . "/config/db.php";

$error      = "";
$success    = "";
$validToken = false;
$token      = trim($_GET['token'] ?? '');

if (!$token) {
    $error = "Invalid or missing reset link.";
} else {
    $stmt = $conn->prepare(
        "SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    } else {
        $resetRow   = $result->fetch_assoc();
        $validToken = true;
    }
    $stmt->close();
}

if (isset($_POST['reset']) && $validToken) {
    $newPassword     = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $upd->bind_param("ss", $hashed, $resetRow['email']);
        $upd->execute();
        $upd->close();

        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $mark->bind_param("s", $token);
        $mark->execute();
        $mark->close();

        $validToken = false;
        header("Location: index.php?reset=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stella Bank – Reset Password</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="page active">
  <div class="auth-container">
    <h2>🔒 Reset Password</h2>
    <p style="opacity:.6; margin-bottom:20px; font-size:14px;">Choose a new password for your account.</p>

    <?php if ($error): ?>
      <div class="form-error show"><?php echo htmlspecialchars($error); ?></div>
      <p class="switch">
        <a href="forgot_password.php" style="color:#27ae60;">Request a new link</a>
      </p>
    <?php endif; ?>

    <?php if ($validToken): ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="password" name="password" placeholder="New password (min 6 chars)" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <button type="submit" name="reset" class="btn-primary">Set New Password</button>
      </form>
    <?php endif; ?>

    <p class="switch">
      <a href="index.php" style="color:#27ae60;">← Back to Login</a>
    </p>
  </div>
</div>

</body>
</html>
