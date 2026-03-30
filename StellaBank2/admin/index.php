<?php
session_start();
include __DIR__ . "/../config/db.php";

// Already logged in as admin → go straight to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No admin account found with that email.";
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
  <title>Stella Bank — Admin Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --bg:#050d1a; --card:rgba(10,25,47,0.85); --border:rgba(231,76,60,0.3);
            --red:#e74c3c; --text:#e8f0fe; --muted:rgba(232,240,254,0.5); }
    html, body { height:100%; background:var(--bg); color:var(--text);
                 font-family:'DM Sans',sans-serif; overflow:hidden; }
    #bg-canvas { position:fixed; inset:0; z-index:0; }
    .scene { position:relative; z-index:1; display:flex; align-items:center;
             justify-content:center; min-height:100vh; padding:20px; }
    .card { width:100%; max-width:420px; background:var(--card);
            border:1px solid var(--border); border-radius:24px; padding:44px 40px 40px;
            backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
            box-shadow:0 0 60px rgba(231,76,60,0.1),0 24px 80px rgba(0,0,0,0.6);
            animation:cardIn .7s cubic-bezier(.22,1,.36,1) both; }
    @keyframes cardIn { from{opacity:0;transform:translateY(30px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }
    .logo { display:flex; align-items:center; gap:10px; margin-bottom:28px; }
    .logo-icon { width:38px; height:38px; background:linear-gradient(135deg,#e74c3c,#c0392b);
                 border-radius:10px; display:flex; align-items:center; justify-content:center;
                 font-size:18px; box-shadow:0 0 20px rgba(231,76,60,0.4); }
    .logo-text { font-family:'Syne',sans-serif; font-weight:800; font-size:20px; letter-spacing:-.3px; }
    .logo-text span { color:#e74c3c; }
    .restricted-badge { display:inline-block; background:rgba(231,76,60,0.15);
      border:1px solid rgba(231,76,60,0.4); color:#e74c3c; font-size:11px; font-weight:700;
      padding:4px 12px; border-radius:20px; letter-spacing:.8px;
      text-transform:uppercase; margin-bottom:16px; }
    h2 { font-family:'Syne',sans-serif; font-weight:700; font-size:26px;
         letter-spacing:-.4px; margin-bottom:6px; }
    .sub { color:var(--muted); font-size:14px; margin-bottom:28px; }
    .alert { border-radius:10px; padding:11px 14px; font-size:13px; margin-bottom:16px; display:none; }
    .alert.show { display:block; }
    .alert-error { background:rgba(231,76,60,0.12); border:1px solid rgba(231,76,60,0.35); color:#ffb3ad; }
    .field { margin-bottom:16px; }
    label { display:block; font-size:12px; font-weight:500; color:var(--muted);
            letter-spacing:.5px; text-transform:uppercase; margin-bottom:7px; }
    input { width:100%; background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,0.1); border-radius:12px;
            padding:13px 16px; color:var(--text); font-size:15px; outline:none;
            transition:border-color .2s,box-shadow .2s,background .2s; }
    input::placeholder { color:rgba(255,255,255,0.25); }
    input:focus { border-color:rgba(231,76,60,0.6); background:rgba(231,76,60,0.06);
                  box-shadow:0 0 0 3px rgba(231,76,60,0.12); }
    .btn { width:100%; padding:14px; background:linear-gradient(135deg,#e74c3c,#c0392b);
           color:#fff; font-family:'Syne',sans-serif; font-weight:700; font-size:15px;
           border:none; border-radius:12px; cursor:pointer;
           box-shadow:0 4px 20px rgba(231,76,60,0.35);
           transition:transform .15s,box-shadow .15s; }
    .btn:hover { transform:translateY(-1px); box-shadow:0 8px 30px rgba(231,76,60,0.5); }
    .btn:active { transform:translateY(1px); }
    .back { text-align:center; font-size:13px; color:var(--muted); margin-top:22px; }
    .back a { color:#e74c3c; text-decoration:none; font-weight:500; }
    .back a:hover { text-decoration:underline; }
    @media (max-width:460px) { .card { padding:36px 24px 32px; } }
  </style>
</head>
<body>
<canvas id="bg-canvas"></canvas>
<div class="scene">
  <div class="card">
    <div class="logo">
      <div class="logo-icon">🔐</div>
      <div class="logo-text">Stella <span>Admin</span></div>
    </div>

    <div class="restricted-badge">⛔ Restricted Access</div>
    <h2>Admin Login</h2>
    <p class="sub">Authorised personnel only. All access is logged.</p>

    <div class="alert alert-error <?php echo $error ? 'show' : ''; ?>">
      <?php echo htmlspecialchars($error); ?>
    </div>

    <form method="POST">
      <div class="field">
        <label>Admin Email</label>
        <input type="email" name="email" placeholder="admin@stellabank.com" required
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" name="login" class="btn">Login to Admin Panel →</button>
    </form>

    <p class="back"><a href="../index.php">← Back to Customer Login</a></p>
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
  scene.add(new THREE.Points(sg,new THREE.PointsMaterial({color:0xe74c3c,size:0.05,transparent:true,opacity:.55})));
  let mx=0,my=0;
  document.addEventListener('mousemove',e=>{mx=(e.clientX/innerWidth-.5)*2;my=(e.clientY/innerHeight-.5)*2;});
  window.addEventListener('resize',()=>{camera.aspect=innerWidth/innerHeight;camera.updateProjectionMatrix();renderer.setSize(innerWidth,innerHeight);});
  let t=0;(function animate(){requestAnimationFrame(animate);t+=.008;scene.children[0].rotation.y=t*.04;scene.children[0].rotation.x=t*.015;camera.position.x+=(mx*.25-camera.position.x)*.04;camera.position.y+=(-my*.2-camera.position.y)*.04;camera.lookAt(scene.position);renderer.render(scene,camera);})();
})();
</script>
</body>
</html>
