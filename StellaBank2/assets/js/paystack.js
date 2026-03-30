/* paystack.js — handles Paystack inline payment and syncs balance with server */

function payWithPaystack() {
  // Get user email from a meta tag or server-rendered element
  const emailEl = document.getElementById('userEmailMeta');
  const email   = emailEl ? emailEl.value : '';

  const amount = 5000; // Naira

  let handler = PaystackPop.setup({
    key: 'pk_test_d92e80a243cdcd11d91fe0cbfd20e8328efa2449', // Replace with your live key for production
    email: email,
    amount: amount * 100, // Paystack uses kobo
    currency: 'NGN',

    callback: function(response) {
      // Payment successful — update balance on the server
      fetch('update_balance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount: amount,
          reference: response.reference
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('✅ Account funded successfully! Ref: ' + response.reference);
          // Animate new balance from server
          updateBalanceDisplay(parseFloat(data.new_balance));

          // Add a transaction entry to the UI
          const txList = document.getElementById('transactions');
          const div    = document.createElement('div');
          div.className = 'tx';
          div.innerHTML = `<span>Account Funded</span><span class="credit">+₦${amount.toLocaleString('en-NG', {minimumFractionDigits:2})}</span>`;
          txList.prepend(div);
        } else {
          alert('Payment received but balance update failed: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(() => {
        alert('Payment received but could not reach server. Please contact support.');
      });
    },

    onClose: function() {
      alert('Transaction was not completed.');
    }
  });

  handler.openIframe();
}
