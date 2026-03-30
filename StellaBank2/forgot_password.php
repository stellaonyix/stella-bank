<?php
session_start();
include __DIR__ . "/config/db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer-master/src/Exception.php';
require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/src/SMTP.php';

$message = "";
$isError = false;

if (isset($_POST['send'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $isError = true;
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $message = "If that email is registered, a reset link has been sent.";
        } else {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $email, $token, $expires);
            $ins->execute();

            $resetLink = "http://localhost/StellaBank_Complete/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'onyixanigbo@gmail.com';
                $mail->Password   = 'mnwuyrmsesvuunhk';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('onyixanigbo@gmail.com', 'Stella Bank');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Stella Bank — Password Reset';
                $mail->Body    = "
                    <div style='font-family:Arial,sans-serif; max-width:500px; margin:auto;
                                background:#0f1c33; color:white; padding:30px; border-radius:16px;'>
                        <h2 style='color:#27ae60;'>⭐ Stella Bank</h2>
                        <h3>Password Reset Request</h3>
                        <p>You asked to reset your password. Click the button below:</p>
                        <a href='{$resetLink}'
                           style='display:inline-block; margin:20px 0; padding:12px 24px;
                                  background:#27ae60; color:white; border-radius:10px;
                                  text-decoration:none; font-weight:bold;'>
                            Reset My Password
                        </a>
                        <p style='opacity:.6; font-size:13px;'>
                            This link expires in <strong>1 hour</strong>.<br>
                            If you did not request this, ignore this email.
                        </p>
                    </div>
                ";

                $mail->send();
                $message = "If that email is registered, a reset link has been sent.";

            } catch (Exception $e) {
                $message = "Email could not be sent. Error: " . $mail->ErrorInfo;
                $isError = true;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stella Bank – Forgot Password</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* ── Canvas ── */
    #bg-canvas {
      position: fixed;
      inset: 0;
      z-index: -1;
      pointer-events: none;
    }
  </style>
</head>
<body>

<div class="page active">
  <div class="auth-container">
    <h2>🔑 Forgot Password</h2>
    <p style="opacity:.6; margin-bottom:20px; font-size:14px;">
      Enter your email and we'll send you a reset link.
    </p>

    <?php if ($message): ?>
      <div class="form-error show" style="
        background: <?php echo $isError ? 'rgba(231,76,60,0.15)' : 'rgba(39,174,96,0.15)'; ?>;
        border-color: <?php echo $isError ? 'rgba(231,76,60,0.4)' : 'rgba(39,174,96,0.4)'; ?>;
        color: <?php echo $isError ? '#ffb3ad' : '#a8f0c6'; ?>;">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <input type="email" name="email" placeholder="Your email address" required>
      <button type="submit" name="send" class="btn-primary">Send Reset Link</button>
    </form>

    <p class="switch">
      <a href="index.php" style="color:#27ae60;">← Back to Login</a>
    </p>
  </div>
</div>
<canvas id="bg-canvas"></canvas>



<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
(function () {
  const canvas   = document.getElementById('bg-canvas');
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);

  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
  camera.position.z = 5;

  // Starfield
  const starGeo = new THREE.BufferGeometry();
  const STARS = 1800;
  const pos   = new Float32Array(STARS * 3);
  const sizes = new Float32Array(STARS);
  for (let i = 0; i < STARS; i++) {
    pos[i*3]   = (Math.random() - 0.5) * 40;
    pos[i*3+1] = (Math.random() - 0.5) * 40;
    pos[i*3+2] = (Math.random() - 0.5) * 40;
    sizes[i]   = Math.random() * 1.8 + 0.3;
  }
  starGeo.setAttribute('position', new THREE.BufferAttribute(pos,   3));
  starGeo.setAttribute('size',     new THREE.BufferAttribute(sizes, 1));
  const stars = new THREE.Points(starGeo, new THREE.PointsMaterial({
    color: 0x27ae60, size: 0.05, sizeAttenuation: true, transparent: true, opacity: 0.75
  }));
  scene.add(stars);

  // Floating wireframe orbs
  const ORB_DATA = [
    { r:0.28, x: 1.8, y: 1.0, z:-1,   speed:0.0008, color:0x27ae60 },
    { r:0.18, x:-2.2, y:-0.6, z: 0.5, speed:0.0012, color:0x1abc9c },
    { r:0.22, x: 0.5, y:-1.8, z:-0.8, speed:0.0006, color:0x2ecc71 },
    { r:0.14, x:-1.4, y: 1.6, z: 1.2, speed:0.0015, color:0x16a085 },
    { r:0.32, x: 2.6, y:-1.2, z:-2,   speed:0.0005, color:0x27ae60 },
  ];
  const orbs = ORB_DATA.map(d => {
    const mesh = new THREE.Mesh(
      new THREE.IcosahedronGeometry(d.r, 1),
      new THREE.MeshBasicMaterial({ color: d.color, wireframe: true, transparent: true, opacity: 0.35 })
    );
    mesh.position.set(d.x, d.y, d.z);
    mesh.userData = { oy: d.y, speed: d.speed, phase: Math.random() * Math.PI * 2 };
    scene.add(mesh);
    return mesh;
  });

  // Mouse parallax
  let mx = 0, my = 0;
  document.addEventListener('mousemove', e => {
    mx = (e.clientX / window.innerWidth  - 0.5) * 2;
    my = (e.clientY / window.innerHeight - 0.5) * 2;
  });

  // Resize handler
  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });

  // Animation loop
  let t = 0;
  (function animate() {
    requestAnimationFrame(animate);
    t += 0.008;
    stars.rotation.y = t * 0.04;
    stars.rotation.x = t * 0.015;
    orbs.forEach(o => {
      o.position.y  = o.userData.oy + Math.sin(t * 60 * o.userData.speed + o.userData.phase) * 0.4;
      o.rotation.x += o.userData.speed * 60;
      o.rotation.y += o.userData.speed * 40;
    });
    camera.position.x += (mx * 0.25 - camera.position.x) * 0.04;
    camera.position.y += (-my * 0.2  - camera.position.y) * 0.04;
    camera.lookAt(scene.position);
    renderer.render(scene, camera);
  })();
})();
</script>

</body>
</html>