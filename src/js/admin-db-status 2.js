// Admin DB Status page module
(function AdminDbStatus() {
  function handleRunCommandClick(e) {
    const btn = e.target.closest('[data-action="runCommand"]');
    if (!btn) return;
    e.preventDefault();
    try {
      const params = btn.dataset.params ? JSON.parse(btn.dataset.params) : {};
      const command = params.command || '';
      if (!command) return;
      runCommand(command);
    } catch (err) {
      alert('Invalid command parameters');
    }
  }

  function runCommand(cmd) {
    fetch(`db_api.php?action=${encodeURIComponent(cmd)}`)
      .then((res) => res.json())
      .then((data) => {
        alert(data.message || 'Command executed');
        if (data.success) {
          location.reload();
        }
      })
      .catch((error) => {
        alert('Error: ' + (error?.message || String(error)));
      });
  }

  function init() {
    document.addEventListener('click', handleRunCommandClick);

    // Auto-refresh every 30 seconds
    setInterval(() => {
      location.reload();
    }, 30000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
