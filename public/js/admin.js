document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-delete-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const thing = form.dataset.deleteForm || 'this record';
      if (!window.confirm(`Delete ${thing}? This action cannot be undone.`)) {
        event.preventDefault();
      }
    });
  });
});
