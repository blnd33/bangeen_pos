<?php
require_once __DIR__ . '/includes/config.php';
$page_title = LANG === 'ar' ? 'طباعة بطاقات الباركود' : 'Print Barcode Cards';
$active_nav = 'catpos';
require_once __DIR__ . '/includes/layout.php';

$db   = DB::get();
$cats = $db->query("SELECT * FROM categories ORDER BY name_ar")->fetchAll();
$values = [1000, 2000, 3000, 4000, 5000, 6000, 7000, 10000];
?>
<style>
@media print {
  .no-print{display:none!important}
  body{background:#fff;color:#000!important;font-weight:800!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body *{color:#000!important;font-weight:800!important}
  .cat-section{page-break-inside:avoid;margin-bottom:1.5rem}
  .cat-section-title{border-bottom:3px solid #000!important;font-weight:900!important}
  .bc-card{border:2px solid #000!important}
  .bc-cat-name,.bc-value,.bc-raw{color:#000!important;font-weight:900!important}
}
.no-print{margin-bottom:1rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
.cat-section{margin-bottom:2rem;border:1px solid #e0d5c5;border-radius:12px;padding:1rem;background:#fdfaf6}
.cat-section-title{font-size:1rem;font-weight:900;margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:2px solid;display:flex;align-items:center;gap:.5rem}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem}
.bc-card{border:1.5px solid #d0c5b0;border-radius:8px;padding:.6rem .4rem;text-align:center;background:#fff;page-break-inside:avoid}
.bc-cat-name{font-size:.75rem;font-weight:900;color:#000;margin-bottom:.3rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bc-svg{width:100%;height:48px}
.bc-value{font-size:.9rem;font-weight:900;font-family:monospace;margin-top:.2rem;color:#000}
.bc-raw{font-size:.62rem;color:#000;margin-top:.1rem;font-family:monospace;font-weight:800}
</style>

<div class="no-print">
  <button class="btn btn-primary" onclick="window.print()">
    <i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة الكل':'Print All' ?>
  </button>
  <a href="catpos.php?lang=<?= LANG ?>" class="btn btn-secondary">
    <i class="fa fa-arrow-left"></i> <?= LANG==='ar'?'رجوع':'Back to POS' ?>
  </a>
  <span style="font-size:.8rem;color:var(--muted)">
    <?= LANG==='ar'?'امسح أي باركود في نقطة البيع لإدخال قيمته تلقائياً':'Scan any barcode in Category POS to auto-enter its value' ?>
  </span>
</div>

<div class="barcode-sheet">
  <?php foreach ($cats as $c): ?>
  <?php $name = LANG==='ar' ? $c['name_ar'] : ($c['name_en']?:$c['name_ar']); ?>
  <div class="cat-section">
    <div class="cat-section-title" style="color:<?= htmlspecialchars($c['color']) ?>;border-color:<?= htmlspecialchars($c['color']) ?>">
      <span style="width:14px;height:14px;border-radius:50%;background:<?= htmlspecialchars($c['color']) ?>;display:inline-block"></span>
      <?= htmlspecialchars($name) ?>
    </div>
    <div class="cards-grid">
      <?php foreach ($values as $val): ?>
      <?php
        $barcode_data = 'CATVAL_' . $c['id'] . '_' . $val;
        $display_val  = number_format($val) . ' ' . get_setting('currency_symbol','IQD');
      ?>
      <div class="bc-card">
        <div class="bc-cat-name"><?= htmlspecialchars($name) ?></div>
        <svg class="bc-svg" data-val="<?= htmlspecialchars($barcode_data) ?>"></svg>
        <div class="bc-value"><?= $display_val ?></div>
        <div class="bc-raw"><?= htmlspecialchars($barcode_data) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script src="/bangeen_pos/lib/jsbarcode.min.js"></script>
<script>
function renderBarcodes() {
  document.querySelectorAll('svg[data-val]').forEach(svg => {
    try {
      JsBarcode(svg, svg.dataset.val, {
        format:'CODE128', width:1.8, height:42,
        displayValue:false, margin:2,
        background:'transparent', lineColor:'#000000'
      });
    } catch(e) {
      svg.innerHTML = '<text x="50%" y="55%" text-anchor="middle" font-size="8" fill="#999">ERR</text>';
    }
  });
}
if (typeof JsBarcode !== 'undefined') {
  renderBarcodes();
} else {
  document.querySelector('script[src*="jsbarcode"]').onload = renderBarcodes;
  setTimeout(renderBarcodes, 1000);
}
</script>
