document.querySelectorAll('[data-delete-gallery]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm('Delete this photo from the gallery and homepage carousel?')) event.preventDefault();
  });
});
