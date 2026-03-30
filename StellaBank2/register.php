<?php
session_start();
include __DIR__ . "/config/db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

// ── Generate a unique 10-digit account number ─────────────────
function generateAccountNumber($conn) {
    do {
        // Prefix "22" + 8 random digits = 10 digits total
        $number = '22' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $chk    = $conn->prepare("SELECT id FROM users WHERE account_number = ?");
        $chk->bind_param("s", $number);
        $chk->execute();
        $chk->store_result();
        $exists = $chk->num_rows > 0;
        $chk->close();
    } while ($exists);
    return $number;
}

if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $dup->bind_param("s", $email);
        $dup->execute();
        $dup->store_result();

        if ($dup->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            $hashed        = password_hash($password, PASSWORD_DEFAULT);
            $accountNumber = generateAccountNumber($conn);

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password, account_number) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $name, $email, $hashed, $accountNumber);

            if ($stmt->execute()) {
                header("Location: index.php?registered=1");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $dup->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stella Bank – Register</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #050d1a; --card: rgba(10,25,47,0.78);
      --border: rgba(39,174,96,0.28); --green: #27ae60;
      --text: #e8f0fe; --muted: rgba(232,240,254,0.5);
    }
    html, body { height: 100%; background: var(--bg); color: var(--text);
      font-family: 'DM Sans', sans-serif; overflow: hidden; }
    #bg-canvas { position: fixed; inset: 0; z-index: 0; }
    .scene { position: relative; z-index: 1; display: flex; align-items: center;
      justify-content: center; min-height: 100vh; padding: 20px; }
    .card { width: 100%; max-width: 430px; background: var(--card);
      border: 1px solid var(--border); border-radius: 24px; padding: 44px 40px 40px;
      backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
      box-shadow: 0 0 60px rgba(39,174,96,0.08), 0 24px 80px rgba(0,0,0,0.5);
      animation: cardIn .7s cubic-bezier(.22,1,.36,1) both; }
    @keyframes cardIn { from{opacity:0;transform:translateY(30px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }
    .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 32px; }
    .logo-star { width: 38px; height: 38px; background: linear-gradient(135deg,#27ae60,#1a7a43);
      border-radius: 10px; display: flex; align-items: center; justify-content: center;
      font-size: 18px; box-shadow: 0 0 20px rgba(39,174,96,0.4); }
    .logo-text { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 20px; letter-spacing: -.3px; }
    .logo-text span { color: #2ecc71; }
    h2 { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 26px;
         letter-spacing: -.4px; margin-bottom: 6px; }
    .sub { color: var(--muted); font-size: 14px; margin-bottom: 28px; }
    .field { margin-bottom: 16px; }
    label { display: block; font-size: 12px; font-weight: 500; color: var(--muted);
      letter-spacing: .5px; text-transform: uppercase; margin-bottom: 7px; }
    input { width: 100%; background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;
      padding: 13px 16px; color: var(--text); font-size: 15px; outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s; }
    input::placeholder { color: rgba(255,255,255,0.25); }
    input:focus { border-color: rgba(39,174,96,0.6); background: rgba(39,174,96,0.06);
      box-shadow: 0 0 0 3px rgba(39,174,96,0.12); }
    .alert { border-radius: 10px; padding: 11px 14px; font-size: 13px; margin-bottom: 16px; display: none; }
    .alert.show { display: block; }
    .alert-error { background: rgba(231,76,60,0.12); border: 1px solid rgba(231,76,60,0.35); color: #ffb3ad; }
    .btn-primary { width: 100%; padding: 14px; background: linear-gradient(135deg,#27ae60,#1e9651);
      color: #fff; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 15px;
      border: none; border-radius: 12px; cursor: pointer;
      box-shadow: 0 4px 20px rgba(39,174,96,0.35); transition: transform .15s, box-shadow .15s; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 30px rgba(39,174,96,0.45); }
    .switch { text-align: center; font-size: 13px; color: var(--muted); margin-top: 22px; }
    .switch a { color: #2ecc71; text-decoration: none; font-weight: 500; }
    .switch a:hover { text-decoration: underline; }
    @media (max-width: 460px) { .card { padding: 36px 24px 32px; } }
  </style>
</head>
<body>
<canvas id="bg-canvas"></canvas>
<div class="scene">
  <div class="card">
    <div class="logo">
      <div class="logo-star">⭐</div>
      <div class="logo-text">Stella <span>Bank</span></div>
    </div>
    <h2>Create Account 🏦</h2>
    <p class="sub">Join Stella Bank — your account number is auto-generated.</p>

    <div class="alert alert-error <?php echo $error ? 'show' : ''; ?>">
      <?php echo htmlspecialchars($error); ?>
    </div>

    <form method="POST">
      <div class="field"><label>Full Name</label>
        <input type="text" name="name" placeholder="Enter your full name" required></div>
      <div class="field"><label>Email Address</label>
        <input type="email" name="email" placeholder="Enter your email" required></div>
      <div class="field"><label>Password</label>
        <input type="password" name="password" placeholder="Min 6 characters" required></div>
      <button type="submit" name="register" class="btn-primary">Create Account →</button>
    </form>

    <p class="switch">Already have an account? <a href="index.php">Login</a></p>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
(function(){
  const canvas=document.getElementById('bg-canvas');
  const renderer=new THREE.WebGLRenderer({canvas,antialias:true,alpha:true});
  renderer.setPixelRatio(Math.min(devicePixelRatio,2));
  renderer.setSize(innerWidth,innerHeight);
  const scene=new THREE.Scene(),camera=new THREE.PerspectiveCamera(60,innerWidth/innerHeight,0.1,1000);
  camera.position.z=5;
  const sg=new THREE.BufferGeometry(),N=1800,p=new Float32Array(N*3);
  for(let i=0;i<N;i++){p[i*3]=(Math.random()-.5)*40;p[i*3+1]=(Math.random()-.5)*40;p[i*3+2]=(Math.random()-.5)*40;}
  sg.setAttribute('position',new THREE.BufferAttribute(p,3));
  const stars=new THREE.Points(sg,new THREE.PointsMaterial({color:0x27ae60,size:0.05,transparent:true,opacity:.75}));
  scene.add(stars);
  const OD=[{r:.28,x:1.8,y:1,z:-1,s:.0008,c:0x27ae60},{r:.18,x:-2.2,y:-.6,z:.5,s:.0012,c:0x1abc9c},{r:.22,x:.5,y:-1.8,z:-.8,s:.0006,c:0x2ecc71},{r:.14,x:-1.4,y:1.6,z:1.2,s:.0015,c:0x16a085},{r:.32,x:2.6,y:-1.2,z:-2,s:.0005,c:0x27ae60}];
  const orbs=OD.map(d=>{const m=new THREE.Mesh(new THREE.IcosahedronGeometry(d.r,1),new THREE.MeshBasicMaterial({color:d.c,wireframe:true,transparent:true,opacity:.35}));m.position.set(d.x,d.y,d.z);m.userData={oy:d.y,speed:d.s,phase:Math.random()*Math.PI*2};scene.add(m);return m;});
  let mx=0,my=0;document.addEventListener('mousemove',e=>{mx=(e.clientX/innerWidth-.5)*2;my=(e.clientY/innerHeight-.5)*2;});
  window.addEventListener('resize',()=>{camera.aspect=innerWidth/innerHeight;camera.updateProjectionMatrix();renderer.setSize(innerWidth,innerHeight);});
  let t=0;(function animate(){requestAnimationFrame(animate);t+=.008;stars.rotation.y=t*.04;stars.rotation.x=t*.015;orbs.forEach(o=>{o.position.y=o.userData.oy+Math.sin(t*60*o.userData.speed+o.userData.phase)*.4;o.rotation.x+=o.userData.speed*60;o.rotation.y+=o.userData.speed*40;});camera.position.x+=(mx*.25-camera.position.x)*.04;camera.position.y+=(-my*.2-camera.position.y)*.04;camera.lookAt(scene.position);renderer.render(scene,camera);})();
})();
</script>
</body>
</html>
