<?php
require_once __DIR__ . '/includes/config.php';
$page_title = t('reports');
$active_nav = 'reports';
require_once __DIR__ . '/includes/layout.php';
require_role('admin','manager');
$db = DB::get();

$type   = $_GET['type'] ?? 'daily';
$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
$export = isset($_GET['export']);

// Build SQL grouping
switch ($type) {
    case 'monthly': $group = "DATE_FORMAT(created_at,'%Y-%m')"; $label = "DATE_FORMAT(created_at,'%Y-%m')"; break;
    case 'yearly':  $group = "YEAR(created_at)";                  $label = "YEAR(created_at)";                break;
    default:        $group = "DATE(created_at)";                   $label = "DATE(created_at)";                break;
}

$stmt = $db->prepare("SELECT $label as period, COUNT(*) as invoices, SUM(total) as revenue, AVG(total) as avg_ticket FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY $group ORDER BY period ASC");
$stmt->execute([$from,$to]);
$rows = $stmt->fetchAll();

// Top products
$top = $db->prepare("SELECT p.name_ar,p.name_en,SUM(si.quantity) as qty,SUM(si.subtotal) as rev FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY si.product_id ORDER BY qty DESC LIMIT 10");
$top->execute([$from,$to]);
$top_products = $top->fetchAll();

// Summary
$totals = $db->prepare("SELECT COUNT(*) as invoices, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as avg_ticket FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
$totals->execute([$from,$to]);
$totals = $totals->fetch();

// CSV Export
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_'.$from.'_'.$to.'.csv"');
    echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8
    echo LANG==='ar' ? "الفترة,الفواتير,الإيراد,متوسط الفاتورة\n" : "Period,Invoices,Revenue,Avg Ticket\n";
    foreach ($rows as $r) {
        echo "{$r['period']},{$r['invoices']},{$r['revenue']},{$r['avg_ticket']}\n";
    }
    exit;
}
?>

<!-- Filters -->
<div class="card mb-2" style="padding:.9rem">
  <form class="flex gap-1 flex-wrap" method="GET">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <div class="flex gap-1">
      <select name="type" onchange="this.form.submit()" style="width:140px">
        <option value="daily"   <?= $type==='daily'  ?'selected':'' ?>><?= t('daily') ?></option>
        <option value="monthly" <?= $type==='monthly'?'selected':'' ?>><?= t('monthly') ?></option>
        <option value="yearly"  <?= $type==='yearly' ?'selected':'' ?>><?= t('yearly') ?></option>
      </select>
      <input type="date" name="from" value="<?= $from ?>">
      <input type="date" name="to"   value="<?= $to ?>">
      <button class="btn btn-primary"><i class="fa fa-filter"></i> <?= t('filter') ?></button>
    </div>
    <a href="?type=<?= $type ?>&from=<?= $from ?>&to=<?= $to ?>&export=1&lang=<?= LANG ?>" class="btn btn-secondary">
      <i class="fa fa-download"></i> <?= t('export') ?> CSV
    </a>
  </form>
</div>

<!-- Summary stats -->
<div class="grid-3 mb-2">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(196,146,42,.12);color:var(--brand)"><i class="fa fa-money-bill-wave"></i></div>
    <div class="stat-label"><?= t('total_sales') ?></div>
    <div class="stat-value"><?= format_currency((float)$totals['revenue']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-receipt"></i></div>
    <div class="stat-label"><?= t('invoices') ?></div>
    <div class="stat-value"><?= number_format((int)$totals['invoices']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:var(--info)"><i class="fa fa-chart-line"></i></div>
    <div class="stat-label"><?= t('avg_ticket') ?></div>
    <div class="stat-value"><?= format_currency((float)$totals['avg_ticket']) ?></div>
  </div>
</div>

<div class="grid-2 gap-2">
  <!-- Chart -->
  <div class="card">
    <div class="card-title"><i class="fa fa-chart-bar text-brand"></i> <?= LANG==='ar'?'مخطط المبيعات':'Sales Chart' ?></div>
    <canvas id="reportChart" height="180"></canvas>
  </div>

  <!-- Top Products -->
  <div class="card">
    <div class="card-title"><i class="fa fa-trophy text-brand"></i> <?= t('top_products') ?></div>
    <?php if (empty($top_products)): ?>
      <p class="text-muted text-center" style="padding:1rem"><?= t('no_results') ?></p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th><?= t('product_name') ?></th><th><?= t('quantity') ?></th><th><?= t('total_sales') ?></th></tr></thead>
        <tbody>
        <?php foreach ($top_products as $i=>$p): ?>
        <tr>
          <td style="color:var(--brand);font-weight:800"><?= $i+1 ?></td>
          <td><?= sanitize(LANG==='ar'?$p['name_ar']:($p['name_en']?:$p['name_ar'])) ?></td>
          <td class="mono fw-bold"><?= $p['qty'] ?></td>
          <td class="text-brand fw-bold"><?= format_currency((float)$p['rev']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Data Table -->
<div class="card mt-2" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem;font-weight:700;font-size:.95rem">
    <i class="fa fa-table text-brand"></i> <?= LANG==='ar'?'بيانات التقرير':'Report Data' ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th><?= LANG==='ar'?'الفترة':'Period' ?></th>
        <th><?= t('invoices') ?></th>
        <th><?= t('total_sales') ?></th>
        <th><?= t('avg_ticket') ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="4" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono fw-bold"><?= sanitize($r['period']) ?></td>
        <td><?= number_format((int)$r['invoices']) ?></td>
        <td class="text-brand fw-bold"><?= format_currency((float)$r['revenue']) ?></td>
        <td class="text-muted"><?= format_currency((float)$r['avg_ticket']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const chartRows = <?= json_encode($rows) ?>;
new Chart(document.getElementById('reportChart').getContext('2d'),{
  type:'line',
  data:{
    labels: chartRows.map(r=>r.period),
    datasets:[{
      label:'<?= LANG==="ar"?"المبيعات":"Revenue" ?>',
      data: chartRows.map(r=>parseFloat(r.revenue)),
      borderColor:'#C4922A',
      backgroundColor:'rgba(196,146,42,.08)',
      borderWidth:2.5,
      pointBackgroundColor:'#C4922A',
      pointRadius:4,
      tension:.35,
      fill:true,
    },{
      label:'<?= LANG==="ar"?"عدد الفواتير":"Invoices" ?>',
      data: chartRows.map(r=>parseInt(r.invoices)),
      borderColor:'#16a34a',
      backgroundColor:'rgba(22,163,74,.05)',
      borderWidth:2,
      pointBackgroundColor:'#16a34a',
      pointRadius:3,
      tension:.35,
      fill:false,
      yAxisID:'y2',
    }]
  },
  options:{
    responsive:true,
    plugins:{legend:{labels:{color:'#5C4A3A',font:{size:12}}}},
    scales:{
      y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9C8A7A',font:{family:'monospace'}}},
      y2:{position:'<?= DIR==="rtl"?"left":"right" ?>',beginAtZero:true,grid:{display:false},ticks:{color:'#9C8A7A'}},
      x:{grid:{display:false},ticks:{color:'#9C8A7A',font:{size:11}}}
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
