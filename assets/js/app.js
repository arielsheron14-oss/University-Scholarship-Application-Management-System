document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
      }
      
document.addEventListener('DOMContentLoaded', () => {
  document.body.style.opacity = '0';
  document.body.style.transition = 'opacity 0.8s ease';
  setTimeout(() => {
    document.body.style.opacity = '1';
  }, 100);

  
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) return;
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerText = 'Sending... ✨';
        btn.style.opacity = '0.7';
      }
    });
  });

  
  const inputs = document.querySelectorAll('input, select, textarea');
  inputs.forEach(input => {
    input.addEventListener('focus', () => {
      input.style.borderColor = '#4f46e5';
      input.style.boxShadow = '0 0 0 3px rgba(79,70,229,0.2)';
      input.style.transition = 'all 0.3s ease';
    });
    input.addEventListener('blur', () => {
      input.style.borderColor = '';
      input.style.boxShadow = '';
    });
  });

 
  const cards = document.querySelectorAll('.card, .scholarship-card, div');
  cards.forEach(card => {
    card.addEventListener('mouseenter', () => {
      card.style.transform = 'translateY(-5px)';
      card.style.transition = 'transform 0.3s ease';
      card.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = 'translateY(0)';
      card.style.boxShadow = '';
    });
  });
});


function showSuccessToast(message) {
  const toast = document.createElement('div');
  toast.innerText = message;
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: #22c55e;
    color: white;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: bold;
    z-index: 9999;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
  `;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}
  
    });
  });
});
