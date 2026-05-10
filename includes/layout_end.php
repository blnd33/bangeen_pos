</div><!-- /.page-content -->
</div><!-- /.main-wrap -->

<!-- ── Toast container ───────────────────────────────────────── -->
<div class="toast-container" id="toastContainer"></div>

<!-- ── Scripts ───────────────────────────────────────────────── -->
<script>
/* ── Live clock ─────────────────────────────────────────────── */
(function () {
  const el = document.getElementById('topbar-clock');
  if (!el) return;
  function tick() {
    const now  = new Date();
    const hh   = String(now.getHours()).padStart(2, '0');
    const mm   = String(now.getMinutes()).padStart(2, '0');
    const ss   = String(now.getSeconds()).padStart(2, '0');
    el.textContent = hh + ':' + mm + ':' + ss;
  }
  tick();
  setInterval(tick, 1000);
})();

/* ── Toast helper ────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
  const icons = { success: '✓', error: '✗', warning: '⚠' };
  const colors = {
    success: 'rgba(22,163,74,.35)',
    error:   'rgba(220,38,38,.35)',
    warning: 'rgba(217,119,6,.35)'
  };
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.style.borderColor = colors[type] || colors.success;
  t.innerHTML = '<span>' + (icons[type] || '•') + '</span><span>' + msg + '</span>';
  document.getElementById('toastContainer').prepend(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; }, 3000);
  setTimeout(() => t.remove(), 3500);
}

/* ── Auto-show PHP flash toasts ─────────────────────────────── */
<?php if (!empty($flash)): ?>
window.addEventListener('DOMContentLoaded', () => {
  showToast(<?= json_encode($flash['msg']) ?>, <?= json_encode($flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success')) ?>);
});
<?php endif; ?>
</script>

</body>
</html>