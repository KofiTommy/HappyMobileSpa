document.querySelectorAll('[data-delete-gallery]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Delete this photo or video from the gallery and homepage carousel?')) event.preventDefault();
  });
});
