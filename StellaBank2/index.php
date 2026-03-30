<?php
session_start();
include __DIR__ . "/config/db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Wrong password!";
        }
    } else {
        $error = "No account found with that email.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stella Bank – Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:        #050d1a;
      --card:      rgba(10, 25, 47, 0.75);
      --border:    rgba(39, 174, 96, 0.25);
      --green:     #27ae60;
      --green-glow:#2ecc71;
      --text:      #e8f0fe;
      --muted:     rgba(232,240,254,0.5);
    }

    html, body {
      height: 100%;
      background: var(--bg);
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      overflow: hidden;
    }

    /* ── Canvas ── */
    #bg-canvas {
      position: fixed;
      inset: 0;
      z-index: 0;
    }

    /* ── Page layout ── */
    .scene {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    /* ── Card ── */
    .card {
      width: 100%;
      max-width: 420px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 44px 40px 40px;
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      box-shadow:
        0 0 60px rgba(39,174,96,0.08),
        0 24px 80px rgba(0,0,0,0.5);
      animation: cardIn .7s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(30px) scale(.97); }
      to   { opacity: 1; transform: translateY(0)   scale(1);   }
    }

    /* ── Logo ── */
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 32px;
    }

    .logo-star {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, #27ae60, #1a7a43);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      box-shadow: 0 0 20px rgba(39,174,96,0.4);
    }

    .logo-text {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 20px;
      letter-spacing: -.3px;
    }

    .logo-text span { color: var(--green-glow); }

    /* ── Headings ── */
    h2 {
      font-family: 'Syne', sans-serif;
      font-weight: 700;
      font-size: 26px;
      letter-spacing: -.4px;
      margin-bottom: 6px;
    }

    .sub {
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 28px;
    }

    /* ── Alert banners ── */
    .alert {
      border-radius: 10px;
      padding: 11px 14px;
      font-size: 13px;
      margin-bottom: 16px;
      display: none;
    }
    .alert.show { display: block; }
    .alert-success {
      background: rgba(39,174,96,0.12);
      border: 1px solid rgba(39,174,96,0.35);
      color: #a8f0c6;
    }
    .alert-error {
      background: rgba(231,76,60,0.12);
      border: 1px solid rgba(231,76,60,0.35);
      color: #ffb3ad;
    }

    /* ── Form ── */
    .field { margin-bottom: 16px; }

    label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: var(--muted);
      letter-spacing: .5px;
      text-transform: uppercase;
      margin-bottom: 7px;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      padding: 13px 16px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }

    input::placeholder { color: rgba(255,255,255,0.25); }

    input:focus {
      border-color: rgba(39,174,96,0.6);
      background: rgba(39,174,96,0.06);
      box-shadow: 0 0 0 3px rgba(39,174,96,0.12);
    }

    /* ── Forgot ── */
    .forgot {
      text-align: right;
      margin-top: -8px;
      margin-bottom: 22px;
    }
    .forgot a {
      font-size: 12px;
      color: var(--muted);
      text-decoration: none;
      transition: color .2s;
    }
    .forgot a:hover { color: var(--green-glow); }

    /* ── Button ── */
    .btn-primary {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #27ae60, #1e9651);
      color: #fff;
      font-family: 'Syne', sans-serif;
      font-weight: 700;
      font-size: 15px;
      letter-spacing: .2px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: transform .15s, box-shadow .15s;
      box-shadow: 0 4px 20px rgba(39,174,96,0.35);
    }

    .btn-primary::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,0.12), transparent);
      opacity: 0;
      transition: opacity .2s;
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 30px rgba(39,174,96,0.45);
    }
    .btn-primary:hover::after { opacity: 1; }
    .btn-primary:active { transform: translateY(1px); }

    /* ── Switch ── */
    .switch {
      text-align: center;
      font-size: 13px;
      color: var(--muted);
      margin-top: 22px;
    }
    .switch a {
      color: var(--green-glow);
      text-decoration: none;
      font-weight: 500;
    }
    .switch a:hover { text-decoration: underline; }

    /* ── Divider ── */
    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
      color: var(--muted);
      font-size: 12px;
    }
    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(255,255,255,0.08);
    }

    /* ── Responsive ── */
    @media (max-width: 460px) {
      .card { padding: 36px 24px 32px; }
    }
  </style>
</head>
<body>

<!-- 3-D animated background -->
<canvas id="bg-canvas"></canvas>

<div class="scene">
  <div class="card">

    <div class="logo">
      <div class="logo-star">⭐</div>
      <div class="logo-text">Stella <span>Bank</span></div>
    </div>

    <h2>Welcome Back 👋</h2>
    <p class="sub">Login to your Stella Bank account</p>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success show">✅ Registration successful! Please log in.</div>
    <?php endif; ?>

    <?php if (isset($_GET['reset'])): ?>
      <div class="alert alert-success show">✅ Password reset! You can now log in.</div>
    <?php endif; ?>

    <div class="alert alert-error <?php echo $error ? 'show' : ''; ?>">
      <?php echo htmlspecialchars($error); ?>
    </div>

    <form method="POST" id="loginForm">
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="Enter your email" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>

      <div class="forgot">
        <a href="forgot_password.php">Forgot Password?</a>
      </div>

      <button type="submit" name="login" class="btn-primary">Login →</button>
    </form>

    <div class="divider">or</div>

    <p class="switch">
      Don't have an account? <a href="register.php">Register</a>
    </p>

  </div>
</div>

<!-- Three.js r128 -->
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

  /* ── Starfield ─────────────────────────────────── */
  const starGeo = new THREE.BufferGeometry();
  const STARS    = 1800;
  const pos      = new Float32Array(STARS * 3);
  const sizes    = new Float32Array(STARS);

  for (let i = 0; i < STARS; i++) {
    pos[i * 3]     = (Math.random() - 0.5) * 40;
    pos[i * 3 + 1] = (Math.random() - 0.5) * 40;
    pos[i * 3 + 2] = (Math.random() - 0.5) * 40;
    sizes[i]       = Math.random() * 1.8 + 0.3;
  }

  starGeo.setAttribute('position', new THREE.BufferAttribute(pos,   3));
  starGeo.setAttribute('size',     new THREE.BufferAttribute(sizes, 1));

  const starMat = new THREE.PointsMaterial({
    color:       0x27ae60,
    size:        0.05,
    sizeAttenuation: true,
    transparent: true,
    opacity:     0.75,
  });
  const stars = new THREE.Points(starGeo, starMat);
  scene.add(stars);

  /* ── Floating orbs (icosahedrons) ──────────────── */
  const ORB_DATA = [
    { r: 0.28, x:  1.8, y:  1.0, z: -1,   speed: 0.0008, color: 0x27ae60 },
    { r: 0.18, x: -2.2, y: -0.6, z:  0.5, speed: 0.0012, color: 0x1abc9c },
    { r: 0.22, x:  0.5, y: -1.8, z: -0.8, speed: 0.0006, color: 0x2ecc71 },
    { r: 0.14, x: -1.4, y:  1.6, z:  1.2, speed: 0.0015, color: 0x16a085 },
    { r: 0.32, x:  2.6, y: -1.2, z: -2,   speed: 0.0005, color: 0x27ae60 },
  ];

  const orbs = ORB_DATA.map(d => {
    const geo  = new THREE.IcosahedronGeometry(d.r, 1);
    const mat  = new THREE.MeshBasicMaterial({
      color:       d.color,
      wireframe:   true,
      transparent: true,
      opacity:     0.35,
    });
    const mesh = new THREE.Mesh(geo, mat);
    mesh.position.set(d.x, d.y, d.z);
    mesh.userData = { ox: d.x, oy: d.y, speed: d.speed, phase: Math.random() * Math.PI * 2 };
    scene.add(mesh);
    return mesh;
  });

  /* ── Connecting lines (grid-like) ──────────────── */
  const lineGeo = new THREE.BufferGeometry();
  const LINE_PTS = 120;
  const lPos     = new Float32Array(LINE_PTS * 3);
  for (let i = 0; i < LINE_PTS; i++) {
    lPos[i * 3]     = (Math.random() - 0.5) * 30;
    lPos[i * 3 + 1] = (Math.random() - 0.5) * 30;
    lPos[i * 3 + 2] = (Math.random() - 0.5) * 10 - 5;
  }
  lineGeo.setAttribute('position', new THREE.BufferAttribute(lPos, 3));
  const lineMat = new THREE.LineBasicMaterial({ color: 0x27ae60, transparent: true, opacity: 0.06 });
  scene.add(new THREE.LineLoop(lineGeo, lineMat));

  /* ── Mouse parallax ────────────────────────────── */
  let mx = 0, my = 0;
  document.addEventListener('mousemove', e => {
    mx = (e.clientX / window.innerWidth  - 0.5) * 2;
    my = (e.clientY / window.innerHeight - 0.5) * 2;
  });

  /* ── Resize ─────────────────────────────────────── */
  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });

  /* ── Animate ────────────────────────────────────── */
  let t = 0;
  function animate() {
    requestAnimationFrame(animate);
    t += 0.008;

    // Rotate starfield slowly
    stars.rotation.y = t * 0.04;
    stars.rotation.x = t * 0.015;

    // Orb floating
    orbs.forEach(o => {
      const d = o.userData;
      o.position.y  = d.oy + Math.sin(t * 60 * d.speed + d.phase) * 0.4;
      o.rotation.x += d.speed * 60;
      o.rotation.y += d.speed * 40;
    });

    // Gentle camera parallax
    camera.position.x += (mx * 0.25 - camera.position.x) * 0.04;
    camera.position.y += (-my * 0.2  - camera.position.y) * 0.04;
    camera.lookAt(scene.position);

    renderer.render(scene, camera);
  }
  animate();
})();
</script>

</body>
</html>