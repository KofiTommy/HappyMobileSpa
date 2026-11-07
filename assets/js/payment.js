const autoCheckout = document.querySelector('form[data-auto-submit]');

if (autoCheckout) {
  const button = autoCheckout.querySelector('button[name="action"][value="pay"]');
  autoCheckout.addEventListener('submit', () => {
    if (button) {
      button.disabled = true;
      button.textContent = 'Opening secure checkout…';
    }
  }, { once: true });
  autoCheckout.requestSubmit();
}
