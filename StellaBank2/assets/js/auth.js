// Client-side auth helpers (localStorage for demo only).
// Attaches to pages that have the expected IDs:
// - Login page: form id="loginForm", inputs id="loginEmail", id="loginPassword"
// - Register page: form id="registerForm", inputs id="regName", id="regEmail", id="regPassword"
// - Dashboard page: optional logout button id="logoutBtn"

document.addEventListener('DOMContentLoaded', () => {
  // Register page
  const registerForm = document.getElementById('registerForm');
  if(registerForm){
    registerForm.addEventListener('submit', (e)=>{
      e.preventDefault();
      handleRegister();
    });
  }

  // Login page
  const loginForm = document.getElementById('loginForm');
  if(loginForm){
    loginForm.addEventListener('submit', (e)=>{
      e.preventDefault();
      handleLogin();
    });
  }

  // Logout button (client-side)
  const logoutBtn = document.getElementById('logoutBtn');
  if(logoutBtn){
    logoutBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      clientLogout();
    });
  }
});

function handleRegister(){
  const name = (document.getElementById('regName') || {}).value || '';
  const email = (document.getElementById('regEmail') || {}).value || '';
  const password = (document.getElementById('regPassword') || {}).value || '';

  if(!name || !email || !password){
    alert('All fields are required.');
    return;
  }

  // Basic client-side email uniqueness check
  if(localStorage.getItem(email)){
    alert('An account with this email already exists (client-side).');
    return;
  }

  const user = { name, email, password, balance: 120500 };
  localStorage.setItem(email, JSON.stringify(user));
  alert('Registered successfully (client-side). You can now login.');
  // Redirect to login (index.php)
  window.location.href = 'index.php';
}

function handleLogin(){
  const email = (document.getElementById('loginEmail') || {}).value || '';
  const password = (document.getElementById('loginPassword') || {}).value || '';

  if(!email || !password){
    alert('Please fill in both email and password');
    return;
  }

  const userRaw = localStorage.getItem(email);
  if(!userRaw){
    alert('User not found (client-side). If you registered on the server, use server login.');
    return;
  }

  const user = JSON.parse(userRaw);
  if(user.password !== password){
    alert('Invalid credentials (client-side)');
    return;
  }

  // Mark current user in sessionStorage for client-side dashboard
  sessionStorage.setItem('currentUser', email);
  // Redirect to dashboard. If server session exists this will show server dashboard;
  // otherwise the modified dashboard page will use client-side storage.
  window.location.href = 'dashboard.php';
}

function clientLogout(){
  sessionStorage.removeItem('currentUser');
  // Don't remove localStorage user data (preserve accounts); only clear session marker.
  window.location.href = 'index.php';
}