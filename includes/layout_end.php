<?php // layout_end.php ?>
  </div><!-- /.page-content -->
</div><!-- /.main-wrap -->

<div class="toast-container" id="toastContainer"></div>

<script>
(function(){
  function updateClock(){
    var el = document.getElementById('topbar-clock');
    if(el) el.textContent = new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  updateClock();
  setInterval(updateClock, 1000);

  window.showToast = function(msg, type, dur){
    type = type || 'success';
    dur  = dur  || 3200;
    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = '<span>' + msg + '</span>';
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(function(){
      el.style.opacity = '0';
      el.style.transition = '.3s';
      setTimeout(function(){ el.remove(); }, 300);
    }, dur);
  };

  <?php if(isset($_SESSION['flash'])): ?>
  showToast(<?= json_encode($_SESSION['flash']['msg'], JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($_SESSION['flash']['type']) ?>);
  <?php unset($_SESSION['flash']); endif; ?>
})();
</script>
</body>
</html>