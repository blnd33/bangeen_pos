<?php
require_once __DIR__ . '/includes/config.php';
$page_title = t('dashboard');
$active_nav = 'dashboard';
require_once __DIR__ . '/includes/layout.php';

$db = DB::get();

try {
    $today = date('Y-m-d');
    $s = $db->prepare("SELECT COALESCE(SUM(total),0) as rev, COUNT(*) as cnt FROM sales WHERE DATE(created_at)=? AND status='completed'");
    $s->execute([$today]);
    $today_stats = $s->fetch();
    $total_products = (int)$db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
    $low_stock = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold AND is_active=1")->fetchColumn();
    $month_rev = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status='completed'")->fetchColumn();
    $dash_from = $_GET['dash_from'] ?? date('Y-m-d');
    $dash_to   = $_GET['dash_to']   ?? date('Y-m-d');
    $recent_stmt = $db->prepare("SELECT s.invoice_number, s.total, s.payment_method, s.created_at FROM sales s WHERE DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.created_at DESC LIMIT 20");
    $recent_stmt->execute([$dash_from, $dash_to]);
    $recent = $recent_stmt->fetchAll();
    $top = $db->query("SELECT p.name_ar, p.name_en, SUM(si.quantity) as qty, SUM(si.subtotal) as rev FROM sale_items si JOIN products p ON si.product_id=p.id GROUP BY si.product_id ORDER BY qty DESC LIMIT 5")->fetchAll();
    $chart = $db->query("SELECT DATE(created_at) as d, COALESCE(SUM(total),0) as rev FROM sales WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND status='completed' GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
} catch (Exception $e) {
    $today_stats = ['rev'=>0,'cnt'=>0];
    $total_products = $low_stock = 0;
    $month_rev = 0;
    $recent = $top = $chart = [];
}
?>


<div class="grid-4 mb-2">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(196,146,42,.12);color:var(--brand)"><i class="fa fa-money-bill-wave"></i></div>
    <div class="stat-label"><?= t('today_sales') ?></div>
    <div class="stat-value"><?= format_currency((float)$today_stats['rev']) ?></div>
    <div class="stat-sub"><?= $today_stats['cnt'] ?> <?= t('invoices') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-chart-line"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'مبيعات الشهر':'Month Sales' ?></div>
    <div class="stat-value"><?= format_currency($month_rev) ?></div>
    <div class="stat-sub"><?= date('M Y') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:var(--info)"><i class="fa fa-box-open"></i></div>
    <div class="stat-label"><?= t('total_products') ?></div>
    <div class="stat-value"><?= $total_products ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'منتج نشط':'active products' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(217,119,6,.12);color:var(--warning)"><i class="fa fa-triangle-exclamation"></i></div>
    <div class="stat-label"><?= t('low_stock_alert') ?></div>
    <div class="stat-value" style="color:<?= $low_stock>0?'var(--warning)':'var(--success)' ?>"><?= $low_stock ?></div>
    <div class="stat-sub"><a href="http://localhost/bangeen_pos/stock.php?lang=<?= LANG ?>" style="color:inherit"><?= LANG==='ar'?'عرض المخزون':'View stock' ?></a></div>
  </div>
</div>

<div class="grid-2 gap-2">
  <div class="card">
    <div class="card-title flex-between" style="flex-wrap:wrap;gap:.5rem">
      <span><i class="fa fa-receipt text-brand"></i> <?= LANG==='ar'?'آخر المبيعات':'Recent Sales' ?></span>
      <form method="GET" style="display:flex;align-items:center;gap:.35rem;flex-wrap:wrap">
        <input type="hidden" name="lang" value="<?= LANG ?>">
        <input type="date" name="dash_from" value="<?= $dash_from ?? date('Y-m-d') ?>" style="width:130px;font-size:.78rem;padding:.2rem .4rem">
        <input type="date" name="dash_to"   value="<?= $dash_to   ?? date('Y-m-d') ?>" style="width:130px;font-size:.78rem;padding:.2rem .4rem">
        <button class="btn btn-sm btn-primary"><i class="fa fa-filter"></i></button>
        <a href="http://localhost/bangeen_pos/sales.php?lang=<?= LANG ?>" class="btn btn-sm btn-secondary"><?= LANG==='ar'?'الكل':'All' ?></a>
      </form>
    </div>
    <?php if (empty($recent)): ?>
      <p class="text-muted text-center" style="padding:2rem"><?= t('no_results') ?></p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th><?= t('invoice') ?></th>
          <th><?= t('total') ?></th>
          <th><?= t('payment_method') ?></th>
          <th><?= t('date') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td class="mono fw-bold" style="color:var(--brand)"><?= sanitize($r['invoice_number']) ?></td>
          <td class="fw-bold"><?= format_currency((float)$r['total']) ?></td>
          <td><span class="badge badge-brand"><?= t($r['payment_method']) ?></span></td>
          <td class="mono text-muted" style="font-size:.78rem"><?= date('d/m H:i',strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:1rem">
    <div class="card">
      <div class="card-title"><i class="fa fa-star text-brand"></i> <?= t('top_products') ?></div>
      <?php if (empty($top)): ?>
        <p class="text-muted text-center" style="padding:1rem"><?= t('no_results') ?></p>
      <?php else: ?>
      <?php foreach ($top as $i => $p): ?>
      <div class="flex-between" style="padding:.5rem 0;border-bottom:1px solid var(--border)">
        <div class="flex-center gap-1">
          <span style="width:22px;height:22px;background:var(--brand-soft);color:var(--brand);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800"><?= $i+1 ?></span>
          <span style="font-size:.88rem"><?= sanitize(LANG==='ar'?$p['name_ar']:($p['name_en']?:$p['name_ar'])) ?></span>
        </div>
        <span class="badge badge-brand"><?= $p['qty'] ?> <?= LANG==='ar'?'قطعة':'pcs' ?></span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-title"><i class="fa fa-chart-bar text-brand"></i> <?= LANG==='ar'?'المبيعات (7 أيام)':'Sales (7 days)' ?></div>
      <canvas id="salesChart" height="120"></canvas>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const chartData = <?= json_encode($chart) ?>;
new Chart(document.getElementById('salesChart').getContext('2d'),{
  type:'bar',
  data:{labels:chartData.map(r=>r.d),datasets:[{label:'Sales',data:chartData.map(r=>parseFloat(r.rev)),backgroundColor:'rgba(196,146,42,.25)',borderColor:'#C4922A',borderWidth:2,borderRadius:6}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>