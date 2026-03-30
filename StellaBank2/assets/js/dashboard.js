/* dashboard.js — runs on the PHP-rendered dashboard page */

document.addEventListener('DOMContentLoaded', () => {
  // Animate the balance on page load
  const balanceEl = document.getElementById('balance');
  if (balanceEl) {
    // Parse current balance from the element text (e.g. "₦120,500.00")
    const raw     = balanceEl.textContent.replace(/[₦,]/g, '');
    const amount  = parseFloat(raw) || 0;
    animateBalance(amount);
  }
});

/* ========== BALANCE ANIMATION ========== */
function animateBalance(amount) {
  const balanceEl = document.getElementById('balance');
  if (!balanceEl) return;

  let current   = 0;
  const steps   = 60;
  const increment = amount / steps;

  const counter = setInterval(() => {
    current += increment;
    if (current >= amount) {
      current = amount;
      clearInterval(counter);
    }
    balanceEl.textContent = '₦' + current.toLocaleString('en-NG', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }, 20);
}

/* ========== UPDATE BALANCE IN UI after funding ========== */
function updateBalanceDisplay(newBalance) {
  animateBalance(newBalance);
}
