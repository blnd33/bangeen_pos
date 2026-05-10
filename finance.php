<?php
// ============================================================
// Bangeen Crystal POS — Finance Section
// بهنگین کریستال — قسم المالية
// ============================================================
require_once __DIR__ . '/includes/config.php';
$page_title = t('finance');
$active_nav = 'finance';
require_once __DIR__ . '/includes/layout.php';
require_role('admin','manager');

$db  = DB::get();
$tab = $_GET['tab'] ?? 'sales';

// ── Date Range Helpers ──────────────────────────────────────
$quick = $_GET['quick'] ?? '';
switch ($quick) {
    case 'today':
        $from = $to = date('Y-m-d'); break;
    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':
        $from = date('Y-01-01');
        $to   = date('Y-12-31'); break;
    default: // month
        $from = date('Y-m-01');
        $to   = date('Y-m-t');
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
}
$type = $_GET['type'] ?? 'daily';

// ── AJAX Handlers ───────────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add_expense') {
        $cat   = $_POST['category']    ?? 'other';
        $dar   = trim($_POST['desc_ar'] ?? '');
        $den   = trim($_POST['desc_en'] ?? '');
        $amt   = (float)($_POST['amount'] ?? 0);
        $dt    = $_POST['expense_date'] ?? date('Y-m-d');
        $uid   = current_user()['id'];
        $stmt  = $db->prepare("INSERT INTO expenses (category,description_ar,description_en,amount,expense_date,user_id) VALUES(?,?,?,?,?,?)");
        $ok    = $stmt->execute([$cat,$dar,$den,$amt,$dt,$uid]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    if ($action === 'edit_expense') {
        $id  = (int)$_POST['id'];
        $cat = $_POST['category']    ?? 'other';
        $dar = trim($_POST['desc_ar'] ?? '');
        $den = trim($_POST['desc_en'] ?? '');
        $amt = (float)($_POST['amount'] ?? 0);
        $dt  = $_POST['expense_date'] ?? date('Y-m-d');
        $stmt= $db->prepare("UPDATE expenses SET category=?,description_ar=?,description_en=?,amount=?,expense_date=? WHERE id=?");
        $ok  = $stmt->execute([$cat,$dar,$den,$amt,$dt,$id]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    if ($action === 'delete_expense') {
        $id  = (int)$_POST['id'];
        $ok  = $db->prepare("DELETE FROM expenses WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    if ($action === 'add_debt') {
        $type2 = $_POST['dtype']      ?? 'we_owe';
        $party = trim($_POST['party'] ?? '');
        $dar   = trim($_POST['desc_ar'] ?? '');
        $den   = trim($_POST['desc_en'] ?? '');
        $amt   = (float)($_POST['amount'] ?? 0);
        $paid  = (float)($_POST['amount_paid'] ?? 0);
        $ddue  = $_POST['due_date']  ?: null;
        $stat  = $_POST['status']    ?? 'pending';
        $uid   = current_user()['id'];
        $stmt  = $db->prepare("INSERT INTO debts (type,party_name,description_ar,description_en,amount,amount_paid,due_date,status,user_id) VALUES(?,?,?,?,?,?,?,?,?)");
        $ok    = $stmt->execute([$type2,$party,$dar,$den,$amt,$paid,$ddue,$stat,$uid]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    if ($action === 'edit_debt') {
        $id    = (int)$_POST['id'];
        $type2 = $_POST['dtype']      ?? 'we_owe';
        $party = trim($_POST['party'] ?? '');
        $dar   = trim($_POST['desc_ar'] ?? '');
        $den   = trim($_POST['desc_en'] ?? '');
        $amt   = (float)($_POST['amount'] ?? 0);
        $paid  = (float)($_POST['amount_paid'] ?? 0);
        $ddue  = $_POST['due_date']  ?: null;
        $stat  = $_POST['status']    ?? 'pending';
        $stmt  = $db->prepare("UPDATE debts SET type=?,party_name=?,description_ar=?,description_en=?,amount=?,amount_paid=?,due_date=?,status=? WHERE id=?");
        $ok    = $stmt->execute([$type2,$party,$dar,$den,$amt,$paid,$ddue,$stat,$id]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    if ($action === 'delete_debt') {
        $id = (int)$_POST['id'];
        $ok = $db->prepare("DELETE FROM debts WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    if ($action === 'mark_debt_paid') {
        $id  = (int)$_POST['id'];
        $ok  = $db->prepare("UPDATE debts SET status='paid', amount_paid=amount WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>$ok]);
        exit;
    }
    echo json_encode(['success'=>false,'error'=>'unknown action']);
    exit;
}

// ── Sales Report Data ───────────────────────────────────────
switch ($type) {
    case 'monthly': $grp = "DATE_FORMAT(created_at,'%Y-%m')"; break;
    case 'yearly':  $grp = "YEAR(created_at)"; break;
    default:        $grp = "DATE(created_at)";
}
$sales_rows = $db->prepare("SELECT $grp as period, COUNT(*) as invoices, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount_total),0) as discounts, COALESCE(SUM(tax_amount),0) as taxes, COALESCE(AVG(total),0) as avg_ticket FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY $grp ORDER BY period ASC");
$sales_rows->execute([$from,$to]);
$sales_rows = $sales_rows->fetchAll();

$sales_totals = $db->prepare("SELECT COUNT(*) as invoices, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount_total),0) as discounts, COALESCE(SUM(tax_amount),0) as taxes, COALESCE(AVG(total),0) as avg_ticket FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
$sales_totals->execute([$from,$to]);
$sales_totals = $sales_totals->fetch();

$top_products = $db->prepare("SELECT p.name_ar,p.name_en,SUM(si.quantity) as qty,SUM(si.subtotal) as rev FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY si.product_id ORDER BY rev DESC LIMIT 10");
$top_products->execute([$from,$to]);
$top_products = $top_products->fetchAll();

$payment_breakdown = $db->prepare("SELECT payment_method, COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY payment_method");
$payment_breakdown->execute([$from,$to]);
$payment_breakdown = $payment_breakdown->fetchAll();

// ── Expenses Data ───────────────────────────────────────────
$expenses_rows = $db->prepare("SELECT e.*, u.full_name_ar, u.full_name_en FROM expenses e LEFT JOIN users u ON e.user_id=u.id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC");
$expenses_rows->execute([$from,$to]);
$expenses_rows = $expenses_rows->fetchAll();

$exp_total = array_sum(array_column($expenses_rows, 'amount'));
$exp_by_cat= $db->prepare("SELECT category, COALESCE(SUM(amount),0) as total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$exp_by_cat->execute([$from,$to]);
$exp_by_cat= $exp_by_cat->fetchAll();

// ── P&L Data ────────────────────────────────────────────────
$cogs = $db->prepare("SELECT COALESCE(SUM(p.cost * si.quantity),0) as cogs FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'");
$cogs->execute([$from,$to]);
$cogs = (float)$cogs->fetchColumn();

$gross_profit  = (float)$sales_totals['revenue'] - $cogs;
$net_profit    = $gross_profit - $exp_total;
$gross_margin  = $sales_totals['revenue'] > 0 ? ($gross_profit / $sales_totals['revenue'] * 100) : 0;
$net_margin    = $sales_totals['revenue'] > 0 ? ($net_profit   / $sales_totals['revenue'] * 100) : 0;

$pl_trend = $db->prepare("SELECT DATE_FORMAT(s.created_at,'%Y-%m') as month, COALESCE(SUM(s.total),0) as revenue, COALESCE(SUM(p.cost*si.quantity),0) as cogs FROM sales s JOIN sale_items si ON s.id=si.sale_id JOIN products p ON si.product_id=p.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY month ORDER BY month");
$pl_trend->execute([$from,$to]);
$pl_trend = $pl_trend->fetchAll();

// ── Debts Data ──────────────────────────────────────────────
$debts_all   = $db->query("SELECT * FROM debts ORDER BY status ASC, due_date ASC")->fetchAll();
$owed_to_us  = array_filter($debts_all, fn($d)=>$d['type']==='owed_to_us');
$we_owe      = array_filter($debts_all, fn($d)=>$d['type']==='we_owe');
$tot_owed_us = array_sum(array_map(fn($d)=>$d['amount']-$d['amount_paid'], $owed_to_us));
$tot_we_owe  = array_sum(array_map(fn($d)=>$d['amount']-$d['amount_paid'], $we_owe));
$net_debt    = $tot_owed_us - $tot_we_owe;

// ── Cash Flow Data ──────────────────────────────────────────
switch ($type) {
    case 'monthly': $cf_grp = "DATE_FORMAT(created_at,'%Y-%m')"; break;
    case 'yearly':  $cf_grp = "YEAR(created_at)"; break;
    default:        $cf_grp = "DATE(created_at)";
}
$cf_sales = $db->prepare("SELECT $cf_grp as period, COALESCE(SUM(total),0) as cash_in FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY $cf_grp ORDER BY period ASC");
$cf_sales->execute([$from,$to]);
$cf_sales = $cf_sales->fetchAll();

switch ($type) {
    case 'monthly': $ef_grp = "DATE_FORMAT(expense_date,'%Y-%m')"; break;
    case 'yearly':  $ef_grp = "YEAR(expense_date)"; break;
    default:        $ef_grp = "DATE(expense_date)";
}
$cf_exp = $db->prepare("SELECT $ef_grp as period, COALESCE(SUM(amount),0) as cash_out FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY $ef_grp ORDER BY period ASC");
$cf_exp->execute([$from,$to]);
$cf_exp = $cf_exp->fetchAll();

$cf_map = [];
foreach ($cf_sales as $r) { $cf_map[$r['period']]['in']  = (float)$r['cash_in']; }
foreach ($cf_exp   as $r) { $cf_map[$r['period']]['out'] = (float)$r['cash_out']; }
ksort($cf_map);
$cf_rows = [];
foreach ($cf_map as $p => $v) {
    $cf_rows[] = ['period'=>$p,'cash_in'=>$v['in']??0,'cash_out'=>$v['out']??0,'net'=>($v['in']??0)-($v['out']??0)];
}
$total_cash_in  = array_sum(array_column($cf_rows,'cash_in'));
$total_cash_out = array_sum(array_column($cf_rows,'cash_out'));
$net_cash_flow  = $total_cash_in - $total_cash_out;

// ── Expense categories ──────────────────────────────────────
$exp_cats = [
    'rent'        => LANG==='ar'?'إيجار':'Rent',
    'salaries'    => LANG==='ar'?'رواتب':'Salaries',
    'utilities'   => LANG==='ar'?'مرافق':'Utilities',
    'supplies'    => LANG==='ar'?'مستلزمات':'Supplies',
    'maintenance' => LANG==='ar'?'صيانة':'Maintenance',
    'marketing'   => LANG==='ar'?'تسويق':'Marketing',
    'other'       => LANG==='ar'?'أخرى':'Other',
];
$exp_cat_icons = [
    'rent'=>'fa-building','salaries'=>'fa-users','utilities'=>'fa-bolt',
    'supplies'=>'fa-boxes-stacked','maintenance'=>'fa-wrench',
    'marketing'=>'fa-bullhorn','other'=>'fa-circle-dot',
];

if (isset($_GET['export']) && $_GET['export'] === 'expenses') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="expenses_'.$from.'_'.$to.'.csv"');
    echo "\xEF\xBB\xBF";
    echo LANG==='ar' ? "التاريخ,الفئة,الوصف,المبلغ\n" : "Date,Category,Description,Amount\n";
    foreach ($expenses_rows as $r) {
        $desc = LANG==='ar' ? $r['description_ar'] : ($r['description_en']?:$r['description_ar']);
        $cat  = $exp_cats[$r['category']] ?? $r['category'];
        echo "{$r['expense_date']},{$cat},{$desc},{$r['amount']}\n";
    }
    exit;
}
?>

<style>
.fin-tabs{display:flex;gap:.4rem;margin-bottom:1.25rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:.35rem;flex-wrap:wrap}
.fin-tab{padding:.45rem 1rem;border-radius:8px;font-size:.84rem;font-weight:700;cursor:pointer;text-decoration:none;color:var(--text2);transition:all .15s;display:flex;align-items:center;gap:.4rem;white-space:nowrap}
.fin-tab:hover{background:var(--surface2);color:var(--text)}
.fin-tab.active{background:var(--brand);color:#fff;box-shadow:0 3px 10px rgba(196,146,42,.3)}
.fin-tab i{font-size:.82rem}
.date-filter-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:.8rem 1rem;margin-bottom:1.1rem}
.quick-btns{display:flex;gap:.4rem;margin-bottom:.65rem;flex-wrap:wrap}
.quick-btn{padding:.3rem .75rem;border-radius:7px;border:1px solid var(--border);background:var(--surface2);color:var(--text2);font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .12s;font-family:var(--font-en)}
.quick-btn:hover,.quick-btn.active{border-color:var(--brand);color:var(--brand);background:var(--brand-soft)}
.date-inputs{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.date-inputs input{width:150px}
.date-inputs select{width:140px}
.type-label{font-size:.72rem;color:var(--muted);font-family:var(--font-en);text-transform:uppercase;letter-spacing:.05em;margin-<?= ALIGN_START ?>:.25rem}
.pl-row{display:flex;align-items:center;justify-content:space-between;padding:.65rem .85rem;border-radius:8px;margin-bottom:.3rem}
.pl-row.header{background:var(--brand-soft);font-weight:800;font-size:.95rem;color:var(--text)}
.pl-row.sub{background:var(--surface2)}
.pl-row.result{background:var(--surface);border:1.5px solid var(--border);font-weight:700}
.pl-row.positive{border-color:rgba(22,163,74,.3);background:rgba(22,163,74,.05)}
.pl-row.negative{border-color:rgba(220,38,38,.3);background:rgba(220,38,38,.05)}
.pl-row .pl-label{font-size:.88rem}
.pl-row .pl-val{font-family:var(--font-en);font-size:.95rem;font-weight:800}
.progress-bar-wrap{height:7px;background:var(--surface2);border-radius:99px;overflow:hidden;margin-top:.3rem;min-width:80px}
.progress-bar-fill{height:100%;border-radius:99px;background:var(--brand);transition:width .4s}
.chart-card{position:relative;min-height:220px}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.1rem}
@media(max-width:900px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.cat-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border-radius:6px;font-size:.75rem;font-weight:700;background:var(--surface2);color:var(--text2)}
</style>

<div class="fin-tabs">
  <?php
  $tabs = [
    ['key'=>'sales',     'icon'=>'fa-chart-line',      'ar'=>'تقرير المبيعات',   'en'=>'Sales Report'],
    ['key'=>'expenses',  'icon'=>'fa-money-bill-wave', 'ar'=>'المصروفات',        'en'=>'Expenses'],
    ['key'=>'pl',        'icon'=>'fa-scale-balanced',  'ar'=>'الأرباح والخسائر','en'=>'P&L Statement'],
    ['key'=>'debts',     'icon'=>'fa-handshake',       'ar'=>'الديون',           'en'=>'Debts'],
    ['key'=>'cashflow',  'icon'=>'fa-water',           'ar'=>'التدفق النقدي',   'en'=>'Cash Flow'],
    ['key'=>'inventory', 'icon'=>'fa-boxes-stacked',   'ar'=>'قيمة المخزون',    'en'=>'Inventory Value'],
  ];
  foreach ($tabs as $t):
    $active = $tab===$t['key'] ? 'active' : '';
    $label  = LANG==='ar' ? $t['ar'] : $t['en'];
    $url    = "?tab={$t['key']}&from={$from}&to={$to}&type={$type}&lang=".LANG;
  ?>
  <a href="<?= $url ?>" class="fin-tab <?= $active ?>"><i class="fa <?= $t['icon'] ?>"></i> <?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php
$qk = $quick ?: 'month';
$filter_base = "?tab={$tab}&type={$type}&lang=".LANG;
?>
<div class="date-filter-bar">
  <div class="quick-btns">
    <a href="<?= $filter_base ?>&quick=today"  class="quick-btn <?= $qk==='today'?'active':'' ?>"><?= LANG==='ar'?'اليوم':'Today' ?></a>
    <a href="<?= $filter_base ?>&quick=week"   class="quick-btn <?= $qk==='week' ?'active':'' ?>"><?= LANG==='ar'?'هذا الأسبوع':'This Week' ?></a>
    <a href="<?= $filter_base ?>&quick=month"  class="quick-btn <?= ($qk==='month'||(!$quick&&!$_GET['from']))?'active':'' ?>"><?= LANG==='ar'?'هذا الشهر':'This Month' ?></a>
    <a href="<?= $filter_base ?>&quick=year"   class="quick-btn <?= $qk==='year' ?'active':'' ?>"><?= LANG==='ar'?'هذا العام':'This Year' ?></a>
  </div>
  <form method="GET" class="date-inputs">
    <input type="hidden" name="tab"  value="<?= $tab ?>">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <select name="type">
      <option value="daily"   <?= $type==='daily'  ?'selected':'' ?>><?= t('daily') ?></option>
      <option value="monthly" <?= $type==='monthly'?'selected':'' ?>><?= t('monthly') ?></option>
      <option value="yearly"  <?= $type==='yearly' ?'selected':'' ?>><?= t('yearly') ?></option>
    </select>
    <span class="type-label"><?= LANG==='ar'?'من':'From' ?></span>
    <input type="date" name="from" value="<?= $from ?>">
    <span class="type-label"><?= LANG==='ar'?'إلى':'To' ?></span>
    <input type="date" name="to"   value="<?= $to ?>">
    <button class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> <?= t('filter') ?></button>
    <?php if ($tab==='expenses'): ?>
    <a href="?tab=expenses&from=<?= $from ?>&to=<?= $to ?>&export=expenses&lang=<?= LANG ?>" class="btn btn-secondary btn-sm"><i class="fa fa-download"></i> CSV</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($tab === 'sales'): ?>
<div class="kpi-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(196,146,42,.12);color:var(--brand)"><i class="fa fa-money-bill-wave"></i></div>
    <div class="stat-label"><?= t('total_sales') ?></div>
    <div class="stat-value"><?= format_currency((float)$sales_totals['revenue']) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'إجمالي الإيرادات':'Total Revenue' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-receipt"></i></div>
    <div class="stat-label"><?= t('invoices') ?></div>
    <div class="stat-value"><?= number_format((int)$sales_totals['invoices']) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'عدد الفواتير':'Total Invoices' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:var(--info)"><i class="fa fa-chart-line"></i></div>
    <div class="stat-label"><?= t('avg_ticket') ?></div>
    <div class="stat-value"><?= format_currency((float)$sales_totals['avg_ticket']) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'متوسط قيمة الفاتورة':'Average Invoice Value' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(217,119,6,.12);color:var(--warning)"><i class="fa fa-tag"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'إجمالي الخصومات':'Total Discounts' ?></div>
    <div class="stat-value"><?= format_currency((float)$sales_totals['discounts']) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'الخصومات الممنوحة':'Discounts Given' ?></div>
  </div>
</div>
<div class="grid-2 gap-2 mb-2">
  <div class="card chart-card">
    <div class="card-title"><i class="fa fa-chart-area text-brand"></i> <?= LANG==='ar'?'مخطط الإيرادات':'Revenue Chart' ?></div>
    <canvas id="salesChart" height="180"></canvas>
  </div>
  <div class="card">
    <div class="card-title"><i class="fa fa-pie-chart text-brand"></i> <?= LANG==='ar'?'توزيع طرق الدفع':'Payment Methods' ?></div>
    <canvas id="payChart" height="140"></canvas>
    <div style="margin-top:1rem">
    <?php foreach ($payment_breakdown as $pb): ?>
    <div class="flex-between mb-1" style="font-size:.85rem">
      <span class="badge badge-brand"><?= t($pb['payment_method']) ?></span>
      <span class="fw-bold"><?= format_currency((float)$pb['total']) ?></span>
      <span class="text-muted"><?= $pb['cnt'] ?> <?= t('invoices') ?></span>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="grid-2 gap-2">
  <div class="card" style="padding:0">
    <div style="padding:1rem 1.25rem .5rem;font-weight:700"><i class="fa fa-trophy text-brand"></i> <?= t('top_products') ?></div>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th><?= t('product_name') ?></th><th><?= t('quantity') ?></th><th><?= t('total_sales') ?></th></tr></thead>
      <tbody>
      <?php if (empty($top_products)): ?><tr><td colspan="4" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr><?php endif; ?>
      <?php foreach ($top_products as $i=>$p): ?>
      <tr>
        <td style="color:var(--brand);font-weight:800"><?= $i+1 ?></td>
        <td><?= sanitize(LANG==='ar'?$p['name_ar']:($p['name_en']?:$p['name_ar'])) ?></td>
        <td class="mono fw-bold"><?= number_format((int)$p['qty']) ?></td>
        <td class="text-brand fw-bold"><?= format_currency((float)$p['rev']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card" style="padding:0">
    <div style="padding:1rem 1.25rem .5rem;font-weight:700"><i class="fa fa-table text-brand"></i> <?= LANG==='ar'?'بيانات الفترة':'Period Data' ?></div>
    <div class="table-wrap"><table>
      <thead><tr>
        <th><?= LANG==='ar'?'الفترة':'Period' ?></th>
        <th><?= t('invoices') ?></th>
        <th><?= t('total_sales') ?></th>
        <th><?= t('avg_ticket') ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($sales_rows)): ?><tr><td colspan="4" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr><?php endif; ?>
      <?php foreach ($sales_rows as $r): ?>
      <tr>
        <td class="mono fw-bold"><?= sanitize($r['period']) ?></td>
        <td><?= number_format((int)$r['invoices']) ?></td>
        <td class="text-brand fw-bold"><?= format_currency((float)$r['revenue']) ?></td>
        <td class="text-muted"><?= format_currency((float)$r['avg_ticket']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const salesRows = <?= json_encode($sales_rows) ?>;
const payRows   = <?= json_encode($payment_breakdown) ?>;
const isRtl     = <?= DIR==='rtl'?'true':'false' ?>;
new Chart(document.getElementById('salesChart'),{
  type:'bar',
  data:{labels:salesRows.map(r=>r.period),datasets:[
    {label:'<?= LANG==="ar"?"الإيرادات":"Revenue" ?>',data:salesRows.map(r=>parseFloat(r.revenue)),backgroundColor:'rgba(196,146,42,.75)',borderRadius:6,yAxisID:'y'},
    {label:'<?= LANG==="ar"?"الفواتير":"Invoices" ?>',data:salesRows.map(r=>parseInt(r.invoices)),borderColor:'#0ea5e9',backgroundColor:'rgba(14,165,233,.08)',borderWidth:2,type:'line',pointRadius:4,tension:.35,yAxisID:'y2',fill:true}
  ]},
  options:{responsive:true,plugins:{legend:{labels:{color:'#5C4A3A'}}},
    scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9C8A7A',font:{family:'monospace'}}},
      y2:{position:isRtl?'left':'right',beginAtZero:true,grid:{display:false},ticks:{color:'#9C8A7A'}},
      x:{grid:{display:false},ticks:{color:'#9C8A7A'}}}}
});
if (payRows.length) {
  new Chart(document.getElementById('payChart'),{type:'doughnut',
    data:{labels:payRows.map(r=>r.payment_method),datasets:[{data:payRows.map(r=>parseFloat(r.total)),backgroundColor:['#C4922A','#16a34a','#0ea5e9'],borderWidth:0}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#5C4A3A',padding:12}}},cutout:'60%'}
  });
}
</script>

<?php elseif ($tab === 'expenses'): ?>
<div class="kpi-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(220,38,38,.12);color:var(--danger)"><i class="fa fa-money-bill-wave"></i></div>
    <div class="stat-label"><?= t('total_expenses') ?></div>
    <div class="stat-value" style="color:var(--danger)"><?= format_currency($exp_total) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'إجمالي مصروفات الفترة':'Total Period Expenses' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(196,146,42,.12);color:var(--brand)"><i class="fa fa-money-bill-wave"></i></div>
    <div class="stat-label"><?= t('total_sales') ?></div>
    <div class="stat-value"><?= format_currency((float)$sales_totals['revenue']) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'إيرادات نفس الفترة':'Revenue Same Period' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-chart-pie"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'نسبة المصروفات':'Expense Ratio' ?></div>
    <div class="stat-value"><?= $sales_totals['revenue']>0 ? number_format($exp_total/$sales_totals['revenue']*100,1).'%' : '—' ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'من إجمالي الإيرادات':'Of Total Revenue' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:var(--info)"><i class="fa fa-list"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'عدد المصروفات':'Expense Count' ?></div>
    <div class="stat-value"><?= count($expenses_rows) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'إدخالات في الفترة':'Entries in Period' ?></div>
  </div>
</div>
<div class="grid-2 gap-2 mb-2">
  <div class="card">
    <div class="card-title"><i class="fa fa-chart-pie text-brand"></i> <?= LANG==='ar'?'توزيع حسب الفئة':'By Category' ?></div>
    <canvas id="expCatChart" height="160"></canvas>
  </div>
  <div class="card">
    <div class="card-title"><i class="fa fa-layer-group text-brand"></i> <?= LANG==='ar'?'تفاصيل الفئات':'Category Details' ?></div>
    <?php if (empty($exp_by_cat)): ?>
      <p class="text-muted text-center" style="padding:2rem"><?= t('no_results') ?></p>
    <?php else: foreach ($exp_by_cat as $ec): $pct = $exp_total>0 ? $ec['total']/$exp_total*100 : 0; ?>
    <div style="margin-bottom:.85rem">
      <div class="flex-between mb-1">
        <span style="font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:.4rem">
          <i class="fa <?= $exp_cat_icons[$ec['category']]??'fa-circle-dot' ?>" style="color:var(--brand)"></i>
          <?= sanitize($exp_cats[$ec['category']]??$ec['category']) ?>
        </span>
        <span class="fw-bold mono" style="font-size:.88rem"><?= format_currency((float)$ec['total']) ?></span>
      </div>
      <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= number_format($pct,1) ?>%"></div></div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<div class="card" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem" class="flex-between">
    <span style="font-weight:700"><i class="fa fa-list-ul text-brand"></i> <?= LANG==='ar'?'قائمة المصروفات':'Expense List' ?></span>
    <button class="btn btn-primary btn-sm" onclick="openExpModal()"><i class="fa fa-plus"></i> <?= t('add_expense') ?></button>
  </div>
  <div class="table-wrap"><table>
    <thead><tr>
      <th><?= t('date') ?></th><th><?= LANG==='ar'?'الفئة':'Category' ?></th>
      <th><?= LANG==='ar'?'الوصف':'Description' ?></th><th><?= t('amount') ?></th>
      <th><?= LANG==='ar'?'بواسطة':'By' ?></th><th><?= t('actions') ?></th>
    </tr></thead>
    <tbody>
    <?php if (empty($expenses_rows)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr><?php endif; ?>
    <?php foreach ($expenses_rows as $e): ?>
    <tr id="exp-row-<?= $e['id'] ?>">
      <td class="mono"><?= date('d/m/Y',strtotime($e['expense_date'])) ?></td>
      <td><span class="cat-badge"><i class="fa <?= $exp_cat_icons[$e['category']]??'fa-circle-dot' ?>"></i> <?= sanitize($exp_cats[$e['category']]??$e['category']) ?></span></td>
      <td><?= sanitize(LANG==='ar'?($e['description_ar']?:'—'):($e['description_en']?:$e['description_ar']?:'—')) ?></td>
      <td class="fw-bold mono" style="color:var(--danger)"><?= format_currency((float)$e['amount']) ?></td>
      <td class="text-muted" style="font-size:.8rem"><?= sanitize(LANG==='ar'?($e['full_name_ar']?:'—'):($e['full_name_en']?:$e['full_name_ar']?:'—')) ?></td>
      <td>
        <button class="btn btn-secondary btn-sm" onclick='editExp(<?= json_encode($e,JSON_UNESCAPED_UNICODE) ?>)'><i class="fa fa-pen"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteExp(<?= $e['id'] ?>)"><i class="fa fa-trash"></i></button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const expCatData = <?= json_encode($exp_by_cat) ?>;
const expCats    = <?= json_encode($exp_cats) ?>;
const catColors  = ['#C4922A','#16a34a','#0ea5e9','#d97706','#8b5cf6','#ec4899','#6b7280'];
if (expCatData.length) {
  new Chart(document.getElementById('expCatChart'),{type:'doughnut',
    data:{labels:expCatData.map(r=>expCats[r.category]||r.category),datasets:[{data:expCatData.map(r=>parseFloat(r.total)),backgroundColor:catColors.slice(0,expCatData.length),borderWidth:0}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#5C4A3A',padding:10,font:{size:11}}}},cutout:'55%'}
  });
}
let editingExpId = null;
function openExpModal(data) {
  editingExpId = data ? data.id : null;
  const lang  = '<?= LANG ?>';
  const title = data ? (lang==='ar'?'تعديل المصروف':'Edit Expense') : (lang==='ar'?'إضافة مصروف جديد':'Add New Expense');
  const catOpts = Object.entries(expCats).map(([k,v])=>`<option value="${k}" ${data&&data.category===k?'selected':''}>${v}</option>`).join('');
  document.getElementById('finModal').innerHTML = `
    <div class="modal" style="max-width:500px">
      <div class="modal-header">
        <div class="modal-title"><i class="fa fa-money-bill-wave text-brand"></i> ${title}</div>
        <button class="modal-close" onclick="closeFinModal()">✕</button>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label>${lang==='ar'?'الفئة':'Category'}</label><select id="expCat">${catOpts}</select></div>
        <div class="form-group"><label>${lang==='ar'?'المبلغ':'Amount'}</label><input type="number" id="expAmt" min="0" step="0.01" value="${data?data.amount:''}" placeholder="0.00"></div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label>${lang==='ar'?'الوصف بالعربية':'Description (Arabic)'}</label><input type="text" id="expDar" value="${data?(data.description_ar||''):''}" placeholder="${lang==='ar'?'وصف المصروف':'Arabic description'}"></div>
        <div class="form-group"><label>${lang==='ar'?'الوصف بالإنجليزية':'Description (English)'}</label><input type="text" id="expDen" value="${data?(data.description_en||''):''}" placeholder="Expense description"></div>
      </div>
      <div class="form-group mb-2"><label>${lang==='ar'?'التاريخ':'Date'}</label><input type="date" id="expDate" value="${data?data.expense_date:'<?= date('Y-m-d') ?>'}"></div>
      <div class="flex gap-1">
        <button class="btn btn-primary btn-full" onclick="saveExpense()"><i class="fa fa-check"></i> ${lang==='ar'?'حفظ':'Save'}</button>
        <button class="btn btn-secondary" onclick="closeFinModal()">${lang==='ar'?'إلغاء':'Cancel'}</button>
      </div>
    </div>`;
  document.getElementById('finModal').classList.add('open');
}
function editExp(data) { openExpModal(data); }
function saveExpense() {
  const fd = new FormData();
  fd.append('action', editingExpId ? 'edit_expense' : 'add_expense');
  if (editingExpId) fd.append('id', editingExpId);
  fd.append('category',    document.getElementById('expCat').value);
  fd.append('desc_ar',     document.getElementById('expDar').value);
  fd.append('desc_en',     document.getElementById('expDen').value);
  fd.append('amount',      document.getElementById('expAmt').value);
  fd.append('expense_date',document.getElementById('expDate').value);
  fetch('finance.php?lang=<?= LANG ?>', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) { closeFinModal(); location.reload(); }
    else alert('<?= LANG==="ar"?"حدث خطأ!":"Error occurred!" ?>');
  });
}
function deleteExp(id) {
  if (!confirm('<?= LANG==="ar"?"هل أنت متأكد من الحذف؟":"Are you sure you want to delete?" ?>')) return;
  const fd = new FormData();
  fd.append('action','delete_expense'); fd.append('id', id);
  fetch('finance.php?lang=<?= LANG ?>', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) document.getElementById('exp-row-'+id)?.remove(); });
}
</script>

<?php elseif ($tab === 'pl'): ?>
<div class="kpi-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(196,146,42,.12);color:var(--brand)"><i class="fa fa-money-bill-wave"></i></div>
    <div class="stat-label"><?= t('revenue') ?></div>
    <div class="stat-value"><?= format_currency((float)$sales_totals['revenue']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(217,119,6,.12);color:var(--warning)"><i class="fa fa-boxes-stacked"></i></div>
    <div class="stat-label"><?= t('cost_of_goods') ?></div>
    <div class="stat-value" style="color:var(--warning)"><?= format_currency($cogs) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $gross_profit>=0?'rgba(22,163,74,.12)':'rgba(220,38,38,.12)' ?>;color:<?= $gross_profit>=0?'var(--success)':'var(--danger)' ?>"><i class="fa fa-chart-line"></i></div>
    <div class="stat-label"><?= t('gross_profit') ?></div>
    <div class="stat-value" style="color:<?= $gross_profit>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency($gross_profit) ?></div>
    <div class="stat-sub"><?= number_format($gross_margin,1) ?>% <?= LANG==='ar'?'هامش':'margin' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $net_profit>=0?'rgba(22,163,74,.12)':'rgba(220,38,38,.12)' ?>;color:<?= $net_profit>=0?'var(--success)':'var(--danger)' ?>"><i class="fa fa-scale-balanced"></i></div>
    <div class="stat-label"><?= t('net_profit') ?></div>
    <div class="stat-value" style="color:<?= $net_profit>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency($net_profit) ?></div>
    <div class="stat-sub"><?= number_format($net_margin,1) ?>% <?= LANG==='ar'?'هامش صافي':'net margin' ?></div>
  </div>
</div>
<div class="grid-2 gap-2 mb-2">
  <div class="card">
    <div class="card-title"><i class="fa fa-file-invoice text-brand"></i> <?= LANG==='ar'?'بيان الأرباح والخسائر':'Profit & Loss Statement' ?></div>
    <div class="pl-row header"><span class="pl-label"><?= LANG==='ar'?'الإيرادات':'Revenue' ?></span><span class="pl-val" style="color:var(--brand)"><?= format_currency((float)$sales_totals['revenue']) ?></span></div>
    <div style="margin:0 0 .75rem;padding:0 .5rem">
      <div class="pl-row sub"><span class="pl-label" style="color:var(--text2)"><?= LANG==='ar'?'  المبيعات الصافية':'  Net Sales' ?></span><span class="pl-val" style="font-size:.85rem"><?= format_currency((float)$sales_totals['revenue']) ?></span></div>
      <div class="pl-row sub"><span class="pl-label" style="color:var(--muted)"><?= LANG==='ar'?'  الخصومات الممنوحة':'  Discounts Given' ?></span><span class="pl-val" style="font-size:.85rem;color:var(--danger)">- <?= format_currency((float)$sales_totals['discounts']) ?></span></div>
    </div>
    <div class="pl-row header"><span class="pl-label"><?= LANG==='ar'?'تكلفة البضاعة المباعة':'Cost of Goods Sold' ?></span><span class="pl-val" style="color:var(--warning)">- <?= format_currency($cogs) ?></span></div>
    <div style="margin:.5rem 0">
      <div class="pl-row result <?= $gross_profit>=0?'positive':'negative' ?>">
        <span class="pl-label fw-bold"><?= LANG==='ar'?'الربح الإجمالي':'Gross Profit' ?></span>
        <span class="pl-val" style="color:<?= $gross_profit>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency($gross_profit) ?> <small style="font-size:.7rem">(<?= number_format($gross_margin,1) ?>%)</small></span>
      </div>
    </div>
    <div class="pl-row header"><span class="pl-label"><?= LANG==='ar'?'المصروفات التشغيلية':'Operating Expenses' ?></span><span class="pl-val" style="color:var(--danger)">- <?= format_currency($exp_total) ?></span></div>
    <div style="margin:0 0 .75rem;padding:0 .5rem">
    <?php foreach ($exp_by_cat as $ec): ?>
      <div class="pl-row sub">
        <span class="pl-label" style="color:var(--text2);display:flex;align-items:center;gap:.4rem"><i class="fa <?= $exp_cat_icons[$ec['category']]??'fa-circle-dot' ?>" style="font-size:.75rem"></i> <?= sanitize($exp_cats[$ec['category']]??$ec['category']) ?></span>
        <span class="pl-val" style="font-size:.85rem;color:var(--danger)">- <?= format_currency((float)$ec['total']) ?></span>
      </div>
    <?php endforeach; ?>
    </div>
    <div class="pl-row result <?= $net_profit>=0?'positive':'negative' ?>" style="border-width:2px;padding:.85rem">
      <span class="pl-label fw-bold" style="font-size:1rem"><?= t('net_profit') ?></span>
      <span class="pl-val" style="font-size:1.15rem;color:<?= $net_profit>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency($net_profit) ?> <small style="font-size:.72rem">(<?= number_format($net_margin,1) ?>%)</small></span>
    </div>
  </div>
  <div class="card">
    <div class="card-title"><i class="fa fa-chart-bar text-brand"></i> <?= LANG==='ar'?'مخطط الأداء المالي':'Financial Performance' ?></div>
    <canvas id="plChart" height="200"></canvas>
    <div class="divider"></div>
    <div class="grid-3" style="gap:.5rem;text-align:center">
      <div>
        <div style="font-size:.7rem;color:var(--muted);font-family:var(--font-en);text-transform:uppercase"><?= LANG==='ar'?'هامش الإجمالي':'Gross Margin' ?></div>
        <div style="font-size:1.25rem;font-weight:800;color:<?= $gross_margin>=0?'var(--success)':'var(--danger)' ?>"><?= number_format($gross_margin,1) ?>%</div>
      </div>
      <div>
        <div style="font-size:.7rem;color:var(--muted);font-family:var(--font-en);text-transform:uppercase"><?= LANG==='ar'?'هامش الصافي':'Net Margin' ?></div>
        <div style="font-size:1.25rem;font-weight:800;color:<?= $net_margin>=0?'var(--success)':'var(--danger)' ?>"><?= number_format($net_margin,1) ?>%</div>
      </div>
      <div>
        <div style="font-size:.7rem;color:var(--muted);font-family:var(--font-en);text-transform:uppercase"><?= LANG==='ar'?'نسبة المصروفات':'Expense Ratio' ?></div>
        <div style="font-size:1.25rem;font-weight:800;color:var(--warning)"><?= $sales_totals['revenue']>0 ? number_format($exp_total/$sales_totals['revenue']*100,1).'%' : '—' ?></div>
      </div>
    </div>
  </div>
</div>
<?php if (!empty($pl_trend)): ?>
<div class="card">
  <div class="card-title"><i class="fa fa-chart-line text-brand"></i> <?= LANG==='ar'?'اتجاه الأداء الشهري':'Monthly Performance Trend' ?></div>
  <canvas id="plTrendChart" height="130"></canvas>
</div>
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const plData={revenue:<?= (float)$sales_totals['revenue'] ?>,cogs:<?= $cogs ?>,expenses:<?= $exp_total ?>,grossProfit:<?= $gross_profit ?>,netProfit:<?= $net_profit ?>};
const lang='<?= LANG ?>';
new Chart(document.getElementById('plChart'),{type:'bar',
  data:{labels:[lang==='ar'?'الإيرادات':'Revenue',lang==='ar'?'تكلفة البضاعة':'COGS',lang==='ar'?'الربح الإجمالي':'Gross Profit',lang==='ar'?'المصروفات':'Expenses',lang==='ar'?'صافي الربح':'Net Profit'],
    datasets:[{data:[plData.revenue,plData.cogs,plData.grossProfit,plData.expenses,plData.netProfit],
      backgroundColor:['rgba(196,146,42,.75)','rgba(217,119,6,.75)','rgba(22,163,74,.75)','rgba(220,38,38,.75)',plData.netProfit>=0?'rgba(22,163,74,.9)':'rgba(220,38,38,.9)'],borderRadius:8}]},
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9C8A7A',font:{family:'monospace'}}},x:{grid:{display:false},ticks:{color:'#5C4A3A',font:{size:11}}}}}
});
<?php if (!empty($pl_trend)): ?>
const plTrend=<?= json_encode($pl_trend) ?>;
new Chart(document.getElementById('plTrendChart'),{type:'line',
  data:{labels:plTrend.map(r=>r.month),datasets:[
    {label:lang==='ar'?'الإيرادات':'Revenue',data:plTrend.map(r=>parseFloat(r.revenue)),borderColor:'#C4922A',backgroundColor:'rgba(196,146,42,.07)',borderWidth:2.5,fill:true,tension:.35,pointRadius:4,pointBackgroundColor:'#C4922A'},
    {label:lang==='ar'?'تكلفة البضاعة':'COGS',data:plTrend.map(r=>parseFloat(r.cogs)),borderColor:'#d97706',backgroundColor:'rgba(217,119,6,.05)',borderWidth:2,fill:false,tension:.35,pointRadius:3}
  ]},
  options:{responsive:true,plugins:{legend:{labels:{color:'#5C4A3A'}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9C8A7A',font:{family:'monospace'}}},x:{grid:{display:false},ticks:{color:'#9C8A7A'}}}}
});
<?php endif; ?>
</script>

<?php elseif ($tab === 'debts'): ?>
<div class="kpi-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-arrow-down"></i></div>
    <div class="stat-label"><?= t('total_owed_to_us') ?></div>
    <div class="stat-value" style="color:var(--success)"><?= format_currency($tot_owed_us) ?></div>
    <div class="stat-sub"><?= count(array_filter($owed_to_us,fn($d)=>$d['status']!=='paid')) ?> <?= LANG==='ar'?'دين نشط':'active' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(220,38,38,.12);color:var(--danger)"><i class="fa fa-arrow-up"></i></div>
    <div class="stat-label"><?= t('total_we_owe') ?></div>
    <div class="stat-value" style="color:var(--danger)"><?= format_currency($tot_we_owe) ?></div>
    <div class="stat-sub"><?= count(array_filter($we_owe,fn($d)=>$d['status']!=='paid')) ?> <?= LANG==='ar'?'دين نشط':'active' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $net_debt>=0?'rgba(22,163,74,.12)':'rgba(220,38,38,.12)' ?>;color:<?= $net_debt>=0?'var(--success)':'var(--danger)' ?>"><i class="fa fa-scale-balanced"></i></div>
    <div class="stat-label"><?= t('net_debt_position') ?></div>
    <div class="stat-value" style="color:<?= $net_debt>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency(abs($net_debt)) ?></div>
    <div class="stat-sub"><?= $net_debt>=0?(LANG==='ar'?'لصالحنا':'In our favor'):(LANG==='ar'?'علينا':'We owe more') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(220,38,38,.12);color:var(--danger)"><i class="fa fa-triangle-exclamation"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'ديون متأخرة':'Overdue Debts' ?></div>
    <div class="stat-value" style="color:var(--danger)"><?= count(array_filter($debts_all,fn($d)=>$d['status']==='overdue')) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'تجاوزت تاريخ الاستحقاق':'Past due date' ?></div>
  </div>
</div>
<div class="flex-between mb-2">
  <span style="font-weight:700;font-size:1rem"><i class="fa fa-handshake text-brand"></i> <?= t('debts') ?></span>
  <button class="btn btn-primary btn-sm" onclick="openDebtModal()"><i class="fa fa-plus"></i> <?= t('add_debt') ?></button>
</div>
<div class="grid-2 gap-2">
  <div class="card" style="padding:0">
    <div style="padding:1rem 1.25rem .5rem">
      <span style="font-weight:700;color:var(--success)"><i class="fa fa-arrow-down"></i> <?= LANG==='ar'?'مستحق لنا':'Owed to Us' ?></span>
      <span class="text-muted" style="font-size:.8rem;margin-<?= ALIGN_START ?>:.5rem">(<?= format_currency($tot_owed_us) ?>)</span>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th><?= t('party_name') ?></th><th><?= LANG==='ar'?'المبلغ الكلي':'Total' ?></th><th><?= LANG==='ar'?'المتبقي':'Remaining' ?></th><th><?= t('due_date') ?></th><th><?= t('status') ?></th><th></th></tr></thead>
      <tbody>
      <?php if (empty($owed_to_us)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr><?php endif; ?>
      <?php foreach ($owed_to_us as $d): $rem=$d['amount']-$d['amount_paid']; $ov=$d['due_date']&&strtotime($d['due_date'])<time()&&$d['status']!=='paid'; ?>
      <tr id="debt-row-<?= $d['id'] ?>">
        <td class="fw-bold"><?= sanitize($d['party_name']) ?></td>
        <td class="mono"><?= format_currency((float)$d['amount']) ?></td>
        <td class="fw-bold" style="color:var(--success)"><?= format_currency((float)$rem) ?></td>
        <td class="mono text-muted" style="font-size:.8rem<?= $ov?';color:var(--danger)':'' ?>"><?= $d['due_date']?date('d/m/Y',strtotime($d['due_date'])):'—' ?></td>
        <td><?= renderDebtStatus($d['status'],LANG) ?></td>
        <td>
          <?php if($d['status']!=='paid'): ?><button class="btn btn-success btn-sm" onclick="markDebtPaid(<?= $d['id'] ?>)"><i class="fa fa-check"></i></button><?php endif; ?>
          <button class="btn btn-secondary btn-sm" onclick='editDebt(<?= json_encode($d,JSON_UNESCAPED_UNICODE) ?>)'><i class="fa fa-pen"></i></button>
          <button class="btn btn-danger btn-sm" onclick="deleteDebt(<?= $d['id'] ?>)"><i class="fa fa-trash"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card" style="padding:0">
    <div style="padding:1rem 1.25rem .5rem">
      <span style="font-weight:700;color:var(--danger)"><i class="fa fa-arrow-up"></i> <?= LANG==='ar'?'نحن مدينون':'We Owe' ?></span>
      <span class="text-muted" style="font-size:.8rem;margin-<?= ALIGN_START ?>:.5rem">(<?= format_currency($tot_we_owe) ?>)</span>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th><?= t('party_name') ?></th><th><?= LANG==='ar'?'المبلغ الكلي':'Total' ?></th><th><?= LANG==='ar'?'المتبقي':'Remaining' ?></th><th><?= t('due_date') ?></th><th><?= t('status') ?></th><th></th></tr></thead>
      <tbody>
      <?php if (empty($we_owe)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr><?php endif; ?>
      <?php foreach ($we_owe as $d): $rem=$d['amount']-$d['amount_paid']; $ov=$d['due_date']&&strtotime($d['due_date'])<time()&&$d['status']!=='paid'; ?>
      <tr id="debt-row-<?= $d['id'] ?>">
        <td class="fw-bold"><?= sanitize($d['party_name']) ?></td>
        <td class="mono"><?= format_currency((float)$d['amount']) ?></td>
        <td class="fw-bold" style="color:var(--danger)"><?= format_currency((float)$rem) ?></td>
        <td class="mono text-muted" style="font-size:.8rem<?= $ov?';color:var(--danger)':'' ?>"><?= $d['due_date']?date('d/m/Y',strtotime($d['due_date'])):'—' ?></td>
        <td><?= renderDebtStatus($d['status'],LANG) ?></td>
        <td>
          <?php if($d['status']!=='paid'): ?><button class="btn btn-success btn-sm" onclick="markDebtPaid(<?= $d['id'] ?>)"><i class="fa fa-check"></i></button><?php endif; ?>
          <button class="btn btn-secondary btn-sm" onclick='editDebt(<?= json_encode($d,JSON_UNESCAPED_UNICODE) ?>)'><i class="fa fa-pen"></i></button>
          <button class="btn btn-danger btn-sm" onclick="deleteDebt(<?= $d['id'] ?>)"><i class="fa fa-trash"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php
function renderDebtStatus(string $s, string $lang): string {
    $map=['pending'=>['class'=>'badge-warning','ar'=>'قيد الانتظار','en'=>'Pending'],'partial'=>['class'=>'badge-info','ar'=>'جزئي','en'=>'Partial'],'paid'=>['class'=>'badge-success','ar'=>'مدفوع','en'=>'Paid'],'overdue'=>['class'=>'badge-danger','ar'=>'متأخر','en'=>'Overdue']];
    $d=$map[$s]??$map['pending'];
    return "<span class=\"badge {$d['class']}\">{$d[$lang]}</span>";
}
?>
<script>
let editingDebtId=null;
const lang='<?= LANG ?>';
function openDebtModal(data){
  editingDebtId=data?data.id:null;
  const title=data?(lang==='ar'?'تعديل الدين':'Edit Debt'):(lang==='ar'?'إضافة دين جديد':'Add New Debt');
  const statuses=['pending','partial','paid','overdue'];
  const statLabels={pending:lang==='ar'?'قيد الانتظار':'Pending',partial:lang==='ar'?'جزئي':'Partial',paid:lang==='ar'?'مدفوع':'Paid',overdue:lang==='ar'?'متأخر':'Overdue'};
  const statOpts=statuses.map(s=>`<option value="${s}" ${data&&data.status===s?'selected':''}>${statLabels[s]}</option>`).join('');
  document.getElementById('finModal').innerHTML=`
    <div class="modal" style="max-width:540px">
      <div class="modal-header"><div class="modal-title"><i class="fa fa-handshake text-brand"></i> ${title}</div><button class="modal-close" onclick="closeFinModal()">✕</button></div>
      <div class="form-row" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label>${lang==='ar'?'النوع':'Type'}</label><select id="dType"><option value="we_owe" ${!data||data.type==='we_owe'?'selected':''}>${lang==='ar'?'نحن مدينون':'We Owe'}</option><option value="owed_to_us" ${data&&data.type==='owed_to_us'?'selected':''}>${lang==='ar'?'مستحق لنا':'Owed to Us'}</option></select></div>
        <div class="form-group"><label>${lang==='ar'?'اسم الطرف':'Party Name'}</label><input type="text" id="dParty" value="${data?data.party_name:''}" placeholder="${lang==='ar'?'اسم الشخص أو الشركة':'Person or company name'}"></div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label>${lang==='ar'?'الوصف بالعربية':'Description (Arabic)'}</label><input type="text" id="dDar" value="${data?(data.description_ar||''):''}" placeholder="${lang==='ar'?'وصف الدين':'Arabic description'}"></div>
        <div class="form-group"><label>${lang==='ar'?'الوصف بالإنجليزية':'Description (English)'}</label><input type="text" id="dDen" value="${data?(data.description_en||''):''}" placeholder="Debt description"></div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
        <div class="form-group"><label>${lang==='ar'?'المبلغ الكلي':'Total Amount'}</label><input type="number" id="dAmt" min="0" step="0.01" value="${data?data.amount:''}" placeholder="0.00"></div>
        <div class="form-group"><label>${lang==='ar'?'المبلغ المدفوع':'Amount Paid'}</label><input type="number" id="dPaid" min="0" step="0.01" value="${data?data.amount_paid:'0'}" placeholder="0.00"></div>
        <div class="form-group"><label>${lang==='ar'?'الحالة':'Status'}</label><select id="dStatus">${statOpts}</select></div>
      </div>
      <div class="form-group mb-2"><label>${lang==='ar'?'تاريخ الاستحقاق':'Due Date'}</label><input type="date" id="dDue" value="${data&&data.due_date?data.due_date:''}"></div>
      <div class="flex gap-1">
        <button class="btn btn-primary btn-full" onclick="saveDebt()"><i class="fa fa-check"></i> ${lang==='ar'?'حفظ':'Save'}</button>
        <button class="btn btn-secondary" onclick="closeFinModal()">${lang==='ar'?'إلغاء':'Cancel'}</button>
      </div>
    </div>`;
  document.getElementById('finModal').classList.add('open');
}
function editDebt(data){openDebtModal(data);}
function saveDebt(){
  const fd=new FormData();
  fd.append('action',editingDebtId?'edit_debt':'add_debt');
  if(editingDebtId)fd.append('id',editingDebtId);
  fd.append('dtype',document.getElementById('dType').value);
  fd.append('party',document.getElementById('dParty').value);
  fd.append('desc_ar',document.getElementById('dDar').value);
  fd.append('desc_en',document.getElementById('dDen').value);
  fd.append('amount',document.getElementById('dAmt').value);
  fd.append('amount_paid',document.getElementById('dPaid').value);
  fd.append('due_date',document.getElementById('dDue').value);
  fd.append('status',document.getElementById('dStatus').value);
  fetch('finance.php?lang=<?= LANG ?>',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){closeFinModal();location.reload();}else alert(lang==='ar'?'حدث خطأ!':'Error occurred!');
  });
}
function deleteDebt(id){
  if(!confirm(lang==='ar'?'هل أنت متأكد من الحذف؟':'Are you sure you want to delete?'))return;
  const fd=new FormData();fd.append('action','delete_debt');fd.append('id',id);
  fetch('finance.php?lang=<?= LANG ?>',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success)document.getElementById('debt-row-'+id)?.remove();});
}
function markDebtPaid(id){
  if(!confirm(lang==='ar'?'تحديد هذا الدين كمدفوع؟':'Mark this debt as paid?'))return;
  const fd=new FormData();fd.append('action','mark_debt_paid');fd.append('id',id);
  fetch('finance.php?lang=<?= LANG ?>',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success)location.reload();});
}
</script>

<?php elseif ($tab === 'cashflow'): ?>
<div class="kpi-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-arrow-trend-up"></i></div>
    <div class="stat-label"><?= t('cash_in') ?></div>
    <div class="stat-value" style="color:var(--success)"><?= format_currency($total_cash_in) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'مبيعات نقدية وكارد':'Sales Revenue' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(220,38,38,.12);color:var(--danger)"><i class="fa fa-arrow-trend-down"></i></div>
    <div class="stat-label"><?= t('cash_out') ?></div>
    <div class="stat-value" style="color:var(--danger)"><?= format_currency($total_cash_out) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'إجمالي المصروفات':'Total Expenses' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $net_cash_flow>=0?'rgba(22,163,74,.12)':'rgba(220,38,38,.12)' ?>;color:<?= $net_cash_flow>=0?'var(--success)':'var(--danger)' ?>"><i class="fa fa-water"></i></div>
    <div class="stat-label"><?= t('net_cash') ?></div>
    <div class="stat-value" style="color:<?= $net_cash_flow>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency(abs($net_cash_flow)) ?></div>
    <div class="stat-sub"><?= $net_cash_flow>=0?(LANG==='ar'?'تدفق إيجابي':'Positive Flow'):(LANG==='ar'?'تدفق سلبي':'Negative Flow') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:var(--info)"><i class="fa fa-percent"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'كفاءة التدفق':'Cash Efficiency' ?></div>
    <div class="stat-value"><?= $total_cash_in>0?number_format($net_cash_flow/$total_cash_in*100,1).'%':'—' ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'صافي من الإيرادات':'Net of Revenue' ?></div>
  </div>
</div>
<div class="card chart-card mb-2">
  <div class="card-title"><i class="fa fa-water text-brand"></i> <?= LANG==='ar'?'مخطط التدفق النقدي':'Cash Flow Chart' ?></div>
  <canvas id="cfChart" height="180"></canvas>
</div>
<div class="card" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem;font-weight:700"><i class="fa fa-table text-brand"></i> <?= LANG==='ar'?'تفاصيل التدفق النقدي':'Cash Flow Details' ?></div>
  <div class="table-wrap"><table>
    <thead><tr><th><?= LANG==='ar'?'الفترة':'Period' ?></th><th><?= t('cash_in') ?></th><th><?= t('cash_out') ?></th><th><?= t('net_cash') ?></th><th><?= LANG==='ar'?'المؤشر':'Indicator' ?></th></tr></thead>
    <tbody>
    <?php if(empty($cf_rows)): ?><tr><td colspan="5" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr><?php endif; ?>
    <?php foreach($cf_rows as $r): $pos=$r['net']>=0; ?>
    <tr>
      <td class="mono fw-bold"><?= sanitize($r['period']) ?></td>
      <td class="fw-bold" style="color:var(--success)"><?= format_currency((float)$r['cash_in']) ?></td>
      <td style="color:var(--danger)"><?= format_currency((float)$r['cash_out']) ?></td>
      <td class="fw-bold" style="color:<?= $pos?'var(--success)':'var(--danger)' ?>"><?= format_currency(abs((float)$r['net'])) ?></td>
      <td><?= $pos?"<span class=\"badge badge-success\"><i class=\"fa fa-arrow-up\"></i> ".(LANG==='ar'?'إيجابي':'Positive')."</span>":"<span class=\"badge badge-danger\"><i class=\"fa fa-arrow-down\"></i> ".(LANG==='ar'?'سلبي':'Negative')."</span>" ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if(!empty($cf_rows)): ?>
    <tfoot>
      <tr style="background:var(--surface2);font-weight:800">
        <td><?= LANG==='ar'?'الإجمالي':'Total' ?></td>
        <td style="color:var(--success)"><?= format_currency($total_cash_in) ?></td>
        <td style="color:var(--danger)"><?= format_currency($total_cash_out) ?></td>
        <td style="color:<?= $net_cash_flow>=0?'var(--success)':'var(--danger)' ?>"><?= format_currency($net_cash_flow) ?></td>
        <td></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const cfRows=<?= json_encode($cf_rows) ?>;
const lang='<?= LANG ?>',isRtl=<?= DIR==='rtl'?'true':'false' ?>;
new Chart(document.getElementById('cfChart'),{type:'bar',
  data:{labels:cfRows.map(r=>r.period),datasets:[
    {label:lang==='ar'?'النقد الداخل':'Cash In',data:cfRows.map(r=>r.cash_in),backgroundColor:'rgba(22,163,74,.7)',borderRadius:5},
    {label:lang==='ar'?'النقد الخارج':'Cash Out',data:cfRows.map(r=>r.cash_out),backgroundColor:'rgba(220,38,38,.65)',borderRadius:5},
    {label:lang==='ar'?'صافي النقد':'Net Cash',data:cfRows.map(r=>r.net),borderColor:cfRows.map(r=>r.net>=0?'#16a34a':'#dc2626'),backgroundColor:'rgba(14,165,233,.08)',borderWidth:2.5,type:'line',pointRadius:5,tension:.3,fill:true,pointBackgroundColor:cfRows.map(r=>r.net>=0?'#16a34a':'#dc2626')}
  ]},
  options:{responsive:true,plugins:{legend:{labels:{color:'#5C4A3A'}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9C8A7A',font:{family:'monospace'}}},x:{grid:{display:false},ticks:{color:'#9C8A7A'}}}}
});
</script>

<?php elseif ($tab === 'inventory'):
  $inv_totals = $db->query("SELECT
    SUM(stock_qty) as total_units,
    SUM(stock_qty * cost) as total_cost_value,
    SUM(stock_qty * price) as total_retail_value,
    COUNT(*) as total_products,
    COUNT(CASE WHEN stock_qty <= 0 THEN 1 END) as out_of_stock
    FROM products WHERE is_active=1")->fetch();
  $inv_products = $db->query("SELECT id, name_ar, name_en, stock_qty, cost, price,
    (stock_qty * cost) as total_cost,
    (stock_qty * price) as total_retail
    FROM products WHERE is_active=1 ORDER BY (stock_qty * cost) DESC")->fetchAll();
?>
<div class="kpi-grid">
  <div class="stat-card" style="cursor:pointer" onclick="document.getElementById('invProductTable').scrollIntoView({behavior:'smooth'})">
    <div class="stat-icon" style="background:rgba(196,146,42,.12);color:var(--brand)"><i class="fa fa-boxes-stacked"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'القيمة الإجمالية بالتكلفة':'Total Inventory Cost' ?></div>
    <div class="stat-value"><?= format_currency((float)($inv_totals['total_cost_value']??0)) ?></div>
    <div class="stat-sub" style="color:var(--brand);font-weight:700"><?= LANG==='ar'?'انقر لعرض التفاصيل ↓':'Click to see details ↓' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success)"><i class="fa fa-tag"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'القيمة بسعر التجزئة':'Total Retail Value' ?></div>
    <div class="stat-value" style="color:var(--success)"><?= format_currency((float)($inv_totals['total_retail_value']??0)) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'القيمة البيعية للمخزون':'Potential Revenue' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:var(--info)"><i class="fa fa-cubes"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'إجمالي الوحدات':'Total Units' ?></div>
    <div class="stat-value"><?= number_format((int)($inv_totals['total_units']??0)) ?></div>
    <div class="stat-sub"><?= number_format((int)($inv_totals['total_products']??0)).' '.(LANG==='ar'?'منتج':'products') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(220,38,38,.12);color:var(--danger)"><i class="fa fa-triangle-exclamation"></i></div>
    <div class="stat-label"><?= LANG==='ar'?'نفذ من المخزون':'Out of Stock' ?></div>
    <div class="stat-value" style="color:var(--danger)"><?= number_format((int)($inv_totals['out_of_stock']??0)) ?></div>
    <div class="stat-sub"><?= LANG==='ar'?'منتج نفد مخزونه':'Products out of stock' ?></div>
  </div>
</div>
<?php
$margin_value = (float)($inv_totals['total_retail_value']??0) - (float)($inv_totals['total_cost_value']??0);
$margin_pct = (float)($inv_totals['total_cost_value']??0) > 0 ? ($margin_value / (float)$inv_totals['total_cost_value'] * 100) : 0;
?>
<div class="card mb-2">
  <div class="card-title"><i class="fa fa-chart-pie text-brand"></i> <?= LANG==='ar'?'ملخص قيمة المخزون':'Inventory Value Summary' ?></div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem">
    <div class="pl-row sub"><span class="pl-label"><?= LANG==='ar'?'إجمالي التكلفة':'Total Cost' ?></span><span class="pl-val" style="color:var(--warning)"><?= format_currency((float)($inv_totals['total_cost_value']??0)) ?></span></div>
    <div class="pl-row result positive"><span class="pl-label"><?= LANG==='ar'?'هامش الربح المتوقع':'Expected Margin' ?></span><span class="pl-val" style="color:var(--success)"><?= format_currency($margin_value) ?> <small>(<?= number_format($margin_pct,1) ?>%)</small></span></div>
    <div class="pl-row header"><span class="pl-label"><?= LANG==='ar'?'القيمة البيعية':'Retail Value' ?></span><span class="pl-val" style="color:var(--brand)"><?= format_currency((float)($inv_totals['total_retail_value']??0)) ?></span></div>
  </div>
</div>
<div class="card" id="invProductTable" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem;font-weight:700;display:flex;justify-content:space-between;align-items:center">
    <span><i class="fa fa-list text-brand"></i> <?= LANG==='ar'?'قائمة قيمة المنتجات':'Product Value List' ?></span>
    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة':'Print' ?></button>
  </div>
  <div class="table-wrap"><table>
    <thead><tr>
      <th>#</th><th><?= LANG==='ar'?'المنتج':'Product' ?></th>
      <th><?= LANG==='ar'?'الكمية':'Qty' ?></th>
      <th><?= LANG==='ar'?'تكلفة الوحدة':'Unit Cost' ?></th>
      <th><?= LANG==='ar'?'سعر البيع':'Unit Price' ?></th>
      <th><?= LANG==='ar'?'إجمالي التكلفة':'Total Cost' ?></th>
      <th><?= LANG==='ar'?'إجمالي البيع':'Total Retail' ?></th>
    </tr></thead>
    <tbody>
    <?php if (empty($inv_products)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
    <?php else: foreach ($inv_products as $i=>$p): ?>
    <tr <?= $p['stock_qty'] <= 0 ? 'style="opacity:.5"' : '' ?>>
      <td style="color:var(--brand);font-weight:700"><?= $i+1 ?></td>
      <td class="fw-bold"><?= sanitize(LANG==='ar'?$p['name_ar']:($p['name_en']?:$p['name_ar'])) ?></td>
      <td class="mono fw-bold <?= $p['stock_qty']<=0?'text-danger':($p['stock_qty']<=5?'text-warning':'') ?>"><?= number_format((int)$p['stock_qty']) ?></td>
      <td class="mono"><?= format_currency((float)$p['cost']) ?></td>
      <td class="mono" style="color:var(--brand)"><?= format_currency((float)$p['price']) ?></td>
      <td class="mono fw-bold" style="color:var(--warning)"><?= format_currency((float)$p['total_cost']) ?></td>
      <td class="mono fw-bold text-brand"><?= format_currency((float)$p['total_retail']) ?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($inv_products)): ?>
    <tfoot>
      <tr style="background:var(--surface2);font-weight:800">
        <td colspan="2"><?= LANG==='ar'?'الإجمالي':'TOTAL' ?></td>
        <td class="mono"><?= number_format((int)($inv_totals['total_units']??0)) ?></td>
        <td colspan="2"></td>
        <td class="mono fw-bold" style="color:var(--warning)"><?= format_currency((float)($inv_totals['total_cost_value']??0)) ?></td>
        <td class="mono fw-bold text-brand"><?= format_currency((float)($inv_totals['total_retail_value']??0)) ?></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table></div>
</div>

<?php endif; ?>

<!-- ── SHARED MODAL ── -->
<div class="modal-overlay" id="finModal" onclick="if(event.target===this) closeFinModal()"></div>
<script>function closeFinModal(){document.getElementById('finModal').classList.remove('open');}</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>