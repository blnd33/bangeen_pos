<?php
require_once __DIR__ . '/includes/config.php';
$page_title = t('reports');
$active_nav = 'reports';
require_once __DIR__ . '/includes/layout.php';
require_role('admin','manager');
$db = DB::get();

$type   = $_GET['type'] ?? 'daily';
$export = isset($_GET['export']);
$quick  = $_GET['quick'] ?? '';

// Quick date presets
switch ($quick) {
    case 'today':
        $from = date('Y-m-d');
        $to   = date('Y-m-d');
        break;
    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = date('Y-m-d');
        break;
    case 'month':
        $from = date('Y-m-01');
        $to   = date('Y-m-d');
        break;
    case 'year':
        $from = date('Y-01-01');
        $to   = date('Y-m-d');
        break;
    case 'last_month':
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t', strtotime('last day of last month'));
        break;
    default:
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-d');
        break;
}

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

// Daily detailed sold items report
$daily_items = $db->prepare("
  SELECT
    p.name_ar, p.name_en,
    SUM(si.quantity) as qty_sold,
    p.cost as unit_cost,
    AVG(si.unit_price) as avg_price,
    SUM(si.subtotal) as total_before_discount,
    SUM(CASE WHEN si.discount_pct > 0 THEN si.unit_price * si.quantity * si.discount_pct / 100 ELSE 0 END) as total_discount,
    SUM(si.subtotal) as final_total
  FROM sale_items si
  JOIN products p ON si.product_id = p.id
  JOIN sales s ON si.sale_id = s.id
  WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'
  GROUP BY si.product_id, p.name_ar, p.name_en, p.cost
  ORDER BY final_total DESC
");
$daily_items->execute([$from, $to]);
$daily_items = $daily_items->fetchAll();

$daily_grand_total    = array_sum(array_column($daily_items, 'final_total'));
$daily_total_discount = array_sum(array_column($daily_items, 'total_discount'));
$daily_total_cost     = array_sum(array_map(fn($r)=>$r['unit_cost']*$r['qty_sold'], $daily_items));

// ── Category POS sales (catpos.php — product_id IS NULL OR barcode like CAT-%) ──
$cat_sales_stmt = $db->prepare("
    SELECT si.product_name_ar AS cat_ar, si.product_name_en AS cat_en,
           COUNT(*) AS sale_rows,
           SUM(si.unit_price) AS gross,
           SUM(CASE WHEN si.unit_price > si.subtotal THEN si.unit_price - si.subtotal ELSE 0 END) AS item_discount,
           SUM(si.subtotal) AS net
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE (si.product_id IS NULL OR si.barcode_text LIKE 'CAT-%')
      AND DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'
    GROUP BY si.product_name_en, si.product_name_ar ORDER BY net DESC
");
$cat_sales_stmt->execute([$from, $to]);
$cat_sales = $cat_sales_stmt->fetchAll();
$cat_gross_total    = array_sum(array_column($cat_sales, 'gross'));
$cat_discount_total = array_sum(array_column($cat_sales, 'item_discount'));
$cat_net_total      = array_sum(array_column($cat_sales, 'net'));
$currency           = get_setting('currency_symbol', 'IQD');

// ── Expenses for the selected period ──────────────────────
$expense_rows    = [];
$total_expenses  = 0.0;
$expense_cats    = [];   // category → total
$has_exp_table   = false;
try {
    $desc_col = LANG==='ar'
        ? "COALESCE(e.description_ar, e.description_en, '')"
        : "COALESCE(e.description_en, e.description_ar, '')";
    $exp_stmt = $db->prepare("
        SELECT e.id,
               e.category,
               $desc_col AS description,
               e.amount,
               e.expense_date,
               u.full_name_".LANG." AS added_by
        FROM expenses e
        LEFT JOIN users u ON e.user_id = u.id
        WHERE e.expense_date BETWEEN ? AND ?
        ORDER BY e.expense_date ASC, e.id ASC
    ");
    $exp_stmt->execute([$from, $to]);
    $expense_rows   = $exp_stmt->fetchAll();
    $total_expenses = (float)array_sum(array_column($expense_rows, 'amount'));
    $has_exp_table  = true;
    // Group by category
    foreach ($expense_rows as $ex) {
        $cat = $ex['category'] ?? 'other';
        $expense_cats[$cat] = ($expense_cats[$cat] ?? 0) + (float)$ex['amount'];
    }
    arsort($expense_cats);
} catch (\Exception $e) { /* expenses table not created yet */ }

// Combined net revenue (cat + detailed) minus expenses
$combined_sales_net = $cat_net_total + $daily_grand_total;
$net_after_expenses = $combined_sales_net - $total_expenses;

// CSV Export
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_'.$from.'_'.$to.'.csv"');
    echo "\xEF\xBB\xBF";
    echo LANG==='ar' ? "الفترة,الفواتير,الإيراد,متوسط الفاتورة\n" : "Period,Invoices,Revenue,Avg Ticket\n";
    foreach ($rows as $r) {
        echo "{$r['period']},{$r['invoices']},{$r['revenue']},{$r['avg_ticket']}\n";
    }
    exit;
}
?>

<!-- Filters -->
<div class="card mb-2" style="padding:.9rem">
  <form method="GET" id="reportForm">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="quick" id="quickInput" value="<?= htmlspecialchars($quick) ?>">

    <!-- Quick presets -->
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.75rem">
      <?php
        $presets = [
          'today'      => LANG==='ar'?'اليوم':'Today',
          'week'       => LANG==='ar'?'هذا الأسبوع':'This Week',
          'month'      => LANG==='ar'?'هذا الشهر':'This Month',
          'last_month' => LANG==='ar'?'الشهر الماضي':'Last Month',
          'year'       => LANG==='ar'?'هذا العام':'This Year',
        ];
        foreach ($presets as $key => $lbl):
          $active = $quick === $key ? 'background:var(--brand);color:#fff;border-color:var(--brand)' : '';
      ?>
      <button type="button"
              style="padding:.35rem .8rem;border-radius:8px;border:1px solid var(--border);background:var(--surface2);cursor:pointer;font-size:.8rem;font-weight:700;<?= $active ?>"
              onclick="setQuick('<?= $key ?>')">
        <?= $lbl ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Grouping + Date range + Filter button -->
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
      <select name="type" style="width:130px;padding:.45rem .7rem;border-radius:8px;border:1px solid var(--border);background:var(--surface2);font-size:.84rem">
        <option value="daily"   <?= $type==='daily'  ?'selected':'' ?>><?= LANG==='ar'?'يومي':'Daily' ?></option>
        <option value="monthly" <?= $type==='monthly'?'selected':'' ?>><?= LANG==='ar'?'شهري':'Monthly' ?></option>
        <option value="yearly"  <?= $type==='yearly' ?'selected':'' ?>><?= LANG==='ar'?'سنوي':'Yearly' ?></option>
      </select>

      <span style="font-size:.8rem;color:var(--muted)"><?= LANG==='ar'?'من:':'From:' ?></span>
      <input type="date" name="from" id="fromDate" value="<?= $from ?>"
             style="padding:.42rem .6rem;border-radius:8px;border:1px solid var(--border);font-size:.84rem;background:var(--surface2)"
             onchange="document.getElementById('quickInput').value=''">

      <span style="font-size:.8rem;color:var(--muted)"><?= LANG==='ar'?'إلى:':'To:' ?></span>
      <input type="date" name="to" id="toDate" value="<?= $to ?>"
             style="padding:.42rem .6rem;border-radius:8px;border:1px solid var(--border);font-size:.84rem;background:var(--surface2)"
             onchange="document.getElementById('quickInput').value=''">

      <button type="submit" class="btn btn-primary">
        <i class="fa fa-filter"></i> <?= LANG==='ar'?'تصفية':'Filter' ?>
      </button>
      <a href="?type=<?= $type ?>&from=<?= $from ?>&to=<?= $to ?>&export=1&lang=<?= LANG ?>" class="btn btn-secondary">
        <i class="fa fa-download"></i> CSV
      </a>
    </div>

    <div style="margin-top:.5rem;font-size:.78rem;color:var(--muted)">
      <i class="fa fa-calendar-days" style="color:var(--brand)"></i>
      <?= LANG==='ar'?'الفترة الحالية:':'Current period:' ?>
      <strong style="color:var(--text)"><?= $from ?></strong>
      <?= LANG==='ar'?'إلى':'to' ?>
      <strong style="color:var(--text)"><?= $to ?></strong>
    </div>
  </form>
</div>

<script>
function setQuick(key) {
  document.getElementById('quickInput').value = key;
  document.getElementById('reportForm').submit();
}
</script>

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

<!-- Detailed Sold Report -->
<div class="card mt-2" id="dailySoldReport" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem;font-weight:700;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
    <span style="font-size:1rem"><i class="fa fa-file-invoice text-brand"></i>
      <?= LANG==='ar'?'تقرير المبيعات التفصيلي':'Detailed Sold Report' ?>
      <span class="text-muted" style="font-size:.8rem;margin-<?= ALIGN_START ?>:.5rem">(<?= $from ?> → <?= $to ?>)</span>
    </span>
    <button class="btn btn-primary btn-sm" onclick="printDailySoldReport()">
      <i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة التقرير':'Print Report' ?>
    </button>
  </div>
  <div class="table-wrap">
    <table id="dailySoldTable">
      <thead><tr>
        <th>#</th>
        <th><?= LANG==='ar'?'المنتج':'Product' ?></th>
        <th><?= LANG==='ar'?'الكمية المباعة':'Qty Sold' ?></th>
        <th><?= LANG==='ar'?'تكلفة الوحدة':'Unit Cost' ?></th>
        <th><?= LANG==='ar'?'متوسط سعر البيع':'Avg Sell Price' ?></th>
        <th><?= LANG==='ar'?'الخصم':'Discount' ?></th>
        <th><?= LANG==='ar'?'الإجمالي النهائي':'Final Total' ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($daily_items)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
      <?php else: foreach ($daily_items as $i=>$r): ?>
      <tr>
        <td style="color:var(--brand);font-weight:700"><?= $i+1 ?></td>
        <td class="fw-bold"><?= sanitize(LANG==='ar'?$r['name_ar']:($r['name_en']?:$r['name_ar'])) ?></td>
        <td class="mono fw-bold"><?= number_format((int)$r['qty_sold']) ?></td>
        <td class="mono text-muted"><?= format_currency((float)$r['unit_cost']) ?></td>
        <td class="mono" style="color:var(--brand)"><?= format_currency((float)$r['avg_price']) ?></td>
        <td class="mono" style="color:var(--danger)"><?= $r['total_discount']>0 ? '- '.format_currency((float)$r['total_discount']) : '—' ?></td>
        <td class="mono fw-bold text-brand"><?= format_currency((float)$r['final_total']) ?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($daily_items)): ?>
      <tfoot>
        <tr style="background:var(--surface2);font-weight:800;font-size:.9rem">
          <td colspan="2"><?= LANG==='ar'?'الإجمالي':'TOTAL' ?></td>
          <td class="mono"><?= number_format(array_sum(array_column($daily_items,'qty_sold'))) ?></td>
          <td class="mono text-muted"><?= format_currency($daily_total_cost) ?></td>
          <td></td>
          <td class="mono" style="color:var(--danger)"><?= $daily_total_discount>0 ? '- '.format_currency($daily_total_discount) : '—' ?></td>
          <td class="mono fw-bold text-brand" style="font-size:1rem"><?= format_currency($daily_grand_total) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CATEGORY POS SALES
══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-top:1rem">

  <!-- Card title WITH print buttons -->
  <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
    <span>
      <i class="fa fa-tags" style="color:var(--brand)"></i>
      <?= LANG==='ar' ? 'مبيعات نقطة بيع الفئات' : 'Category POS Sales' ?>
      <span style="font-size:.75rem;font-weight:400;color:var(--muted);margin-<?= ALIGN_START ?>:.5rem">
        (<?= $from ?> &rarr; <?= $to ?>)
      </span>
    </span>

    <!-- ✅ PRINT BUTTONS -->
    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a href="print_report.php?period=daily&from=<?= $from ?>&to=<?= $to ?>"
         target="_blank"
         style="display:inline-flex;align-items:center;gap:.3rem;padding:.32rem .75rem;
                border-radius:7px;background:var(--brand);color:#fff;font-size:.78rem;
                font-weight:700;text-decoration:none;border:none;cursor:pointer">
        <i class="fa fa-print"></i> <?= LANG==='ar'?'يومي':'Daily' ?>
      </a>
      <a href="print_report.php?period=monthly&quick=month"
         target="_blank"
         style="display:inline-flex;align-items:center;gap:.3rem;padding:.32rem .75rem;
                border-radius:7px;background:var(--surface2);color:var(--text);
                border:1px solid var(--border);font-size:.78rem;font-weight:700;text-decoration:none">
        <i class="fa fa-print"></i> <?= LANG==='ar'?'شهري':'Monthly' ?>
      </a>
      <a href="print_report.php?period=yearly&quick=year"
         target="_blank"
         style="display:inline-flex;align-items:center;gap:.3rem;padding:.32rem .75rem;
                border-radius:7px;background:var(--surface2);color:var(--text);
                border:1px solid var(--border);font-size:.78rem;font-weight:700;text-decoration:none">
        <i class="fa fa-print"></i> <?= LANG==='ar'?'سنوي':'Yearly' ?>
      </a>
    </div>
  </div>

  <?php if (empty($cat_sales)): ?>
    <p style="color:var(--muted);font-size:.85rem;padding:.5rem 0">
      <?= LANG==='ar' ? 'لا توجد مبيعات فئات في هذه الفترة' : 'No category POS sales in this period' ?>
    </p>
  <?php else: ?>

  <!-- Summary cards -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1rem">
    <div style="background:var(--surface2);border-radius:10px;padding:.85rem;text-align:center;border:1px solid var(--border)">
      <div style="font-size:.7rem;color:var(--muted);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em"><?= LANG==='ar'?'إجمالي قبل الخصم':'Gross Total' ?></div>
      <div style="font-size:1.25rem;font-weight:800;color:var(--text);font-family:var(--font-en)"><?= number_format($cat_gross_total) ?></div>
      <div style="font-size:.7rem;color:var(--muted)"><?= $currency ?></div>
    </div>
    <div style="background:rgba(220,38,38,.06);border-radius:10px;padding:.85rem;text-align:center;border:1px solid rgba(220,38,38,.15)">
      <div style="font-size:.7rem;color:var(--danger);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em"><?= LANG==='ar'?'إجمالي الخصومات':'Total Discounts' ?></div>
      <div style="font-size:1.25rem;font-weight:800;color:var(--danger);font-family:var(--font-en)"><?= $cat_discount_total > 0 ? '-'.number_format($cat_discount_total) : '0' ?></div>
      <div style="font-size:.7rem;color:var(--muted)"><?= $currency ?></div>
    </div>
    <div style="background:rgba(196,146,42,.08);border-radius:10px;padding:.85rem;text-align:center;border:1px solid rgba(196,146,42,.25)">
      <div style="font-size:.7rem;color:var(--brand);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em"><?= LANG==='ar'?'صافي المبيعات':'Net Total' ?></div>
      <div style="font-size:1.25rem;font-weight:800;color:var(--brand);font-family:var(--font-en)"><?= number_format($cat_net_total) ?></div>
      <div style="font-size:.7rem;color:var(--muted)"><?= $currency ?></div>
    </div>
  </div>

  <!-- Detailed table per category -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th><?= LANG==='ar'?'الفئة':'Category' ?></th>
          <th style="text-align:right"><?= LANG==='ar'?'عدد الفواتير':'Invoices' ?></th>
          <th style="text-align:right"><?= LANG==='ar'?'المبلغ قبل الخصم':'Before Discount' ?></th>
          <th style="text-align:right"><?= LANG==='ar'?'الخصم':'Discount' ?></th>
          <th style="text-align:right"><?= LANG==='ar'?'بعد الخصم':'After Discount' ?></th>
          <th style="text-align:right"><?= LANG==='ar'?'النسبة من الكل':'Share' ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cat_sales as $idx => $cs):
          $catName = LANG==='ar' ? $cs['cat_ar'] : ($cs['cat_en']?:$cs['cat_ar']);
          $share   = $cat_net_total > 0 ? round($cs['net'] / $cat_net_total * 100, 1) : 0;
          $gross   = (float)$cs['gross'];
          $disc    = (float)$cs['item_discount'];
          $net     = (float)$cs['net'];
        ?>
        <tr>
          <td style="color:var(--brand);font-weight:700"><?= $idx+1 ?></td>
          <td><strong><?= htmlspecialchars($catName) ?></strong></td>
          <td style="text-align:right;font-family:var(--font-en)"><?= number_format((int)$cs['sale_rows']) ?></td>
          <td style="text-align:right;font-family:var(--font-en);color:var(--text2)"><?= number_format($gross) ?> <?= $currency ?></td>
          <td style="text-align:right;font-family:var(--font-en);color:var(--danger)">
            <?= $disc > 0 ? '- '.number_format($disc).' '.$currency : '<span style="color:var(--muted)">—</span>' ?>
          </td>
          <td style="text-align:right;font-family:var(--font-en);font-weight:800;color:var(--brand);font-size:.95rem"><?= number_format($net) ?> <?= $currency ?></td>
          <td style="text-align:right">
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:.4rem">
              <div style="width:60px;height:7px;background:var(--border);border-radius:3px;overflow:hidden">
                <div style="width:<?= $share ?>%;height:100%;background:var(--brand);border-radius:3px"></div>
              </div>
              <span style="font-size:.78rem;font-weight:700;font-family:var(--font-en)"><?= $share ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--surface2);font-weight:800">
          <td colspan="2"><?= LANG==='ar'?'الإجمالي الكلي':'Grand Total' ?></td>
          <td style="text-align:right;font-family:var(--font-en)"><?= number_format(array_sum(array_column($cat_sales,'sale_rows'))) ?></td>
          <td style="text-align:right;font-family:var(--font-en);color:var(--text2)"><?= number_format($cat_gross_total) ?> <?= $currency ?></td>
          <td style="text-align:right;font-family:var(--font-en);color:var(--danger)"><?= $cat_discount_total > 0 ? '- '.number_format($cat_discount_total).' '.$currency : '—' ?></td>
          <td style="text-align:right;font-family:var(--font-en);font-weight:900;color:var(--brand);font-size:1.05rem"><?= number_format($cat_net_total) ?> <?= $currency ?></td>
          <td style="text-align:right;font-weight:800">100%</td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     EXPENSES
══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-top:1rem">

  <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
    <span>
      <i class="fa fa-file-invoice-dollar" style="color:var(--danger)"></i>
      <?= LANG==='ar' ? 'المصروفات' : 'Expenses' ?>
      <span style="font-size:.75rem;font-weight:400;color:var(--muted);margin-<?= ALIGN_START ?>:.5rem">
        (<?= $from ?> &rarr; <?= $to ?>)
      </span>
    </span>
    <a href="finance.php?tab=expenses&lang=<?= LANG ?>"
       style="display:inline-flex;align-items:center;gap:.3rem;padding:.32rem .75rem;
              border-radius:7px;background:var(--surface2);color:var(--text);
              border:1px solid var(--border);font-size:.78rem;font-weight:700;text-decoration:none">
      <i class="fa fa-plus"></i> <?= LANG==='ar'?'إضافة مصروف':'Add Expense' ?>
    </a>
  </div>

  <?php if (!$has_exp_table): ?>
    <p style="color:var(--muted);font-size:.85rem;padding:.5rem 0">
      <?= LANG==='ar' ? 'جدول المصروفات غير مُهيأ بعد' : 'Expenses table not set up yet' ?>
    </p>

  <?php elseif (empty($expense_rows)): ?>
    <p style="color:var(--muted);font-size:.85rem;padding:.5rem 0;text-align:center">
      <?= LANG==='ar' ? 'لا توجد مصروفات في هذه الفترة' : 'No expenses recorded in this period' ?>
    </p>

  <?php else: ?>

  <!-- Summary cards -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1rem">
    <div style="background:rgba(220,38,38,.06);border-radius:10px;padding:.85rem;text-align:center;border:1px solid rgba(220,38,38,.15)">
      <div style="font-size:.7rem;color:var(--danger);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em"><?= LANG==='ar'?'إجمالي المصروفات':'Total Expenses' ?></div>
      <div style="font-size:1.25rem;font-weight:800;color:var(--danger);font-family:var(--font-en)">-<?= number_format($total_expenses) ?></div>
      <div style="font-size:.7rem;color:var(--muted)"><?= $currency ?></div>
    </div>
    <div style="background:var(--surface2);border-radius:10px;padding:.85rem;text-align:center;border:1px solid var(--border)">
      <div style="font-size:.7rem;color:var(--muted);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em"><?= LANG==='ar'?'عدد المصروفات':'No. of Entries' ?></div>
      <div style="font-size:1.25rem;font-weight:800;font-family:var(--font-en)"><?= count($expense_rows) ?></div>
      <div style="font-size:.7rem;color:var(--muted)"><?= LANG==='ar'?'إدخال':'entries' ?></div>
    </div>
    <div style="background:<?= $net_after_expenses >= 0 ? 'rgba(196,146,42,.08)' : 'rgba(220,38,38,.06)' ?>;border-radius:10px;padding:.85rem;text-align:center;border:1px solid <?= $net_after_expenses >= 0 ? 'rgba(196,146,42,.25)' : 'rgba(220,38,38,.15)' ?>">
      <div style="font-size:.7rem;color:<?= $net_after_expenses >= 0 ? 'var(--brand)' : 'var(--danger)' ?>;margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em"><?= LANG==='ar'?'صافي الربح (بعد المصروفات)':'Net Profit (after expenses)' ?></div>
      <div style="font-size:1.25rem;font-weight:800;color:<?= $net_after_expenses >= 0 ? 'var(--brand)' : 'var(--danger)' ?>;font-family:var(--font-en)"><?= number_format($net_after_expenses) ?></div>
      <div style="font-size:.7rem;color:var(--muted)"><?= $currency ?></div>
    </div>
  </div>

  <?php if (!empty($expense_cats)): ?>
  <!-- Category breakdown mini-bars -->
  <div style="margin-bottom:1rem;padding:.75rem;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.6rem;font-weight:700">
      <?= LANG==='ar'?'المصروفات حسب الفئة':'Expenses by Category' ?>
    </div>
    <div style="display:flex;flex-direction:column;gap:.45rem">
    <?php
    $expCatLabels = [
      'rent'       => ['ar'=>'إيجار',         'en'=>'Rent'],
      'salaries'   => ['ar'=>'رواتب',          'en'=>'Salaries'],
      'utilities'  => ['ar'=>'فواتير',         'en'=>'Utilities'],
      'supplies'   => ['ar'=>'لوازم',          'en'=>'Supplies'],
      'transport'  => ['ar'=>'نقل',            'en'=>'Transport'],
      'marketing'  => ['ar'=>'تسويق',          'en'=>'Marketing'],
      'maintenance'=> ['ar'=>'صيانة',          'en'=>'Maintenance'],
      'other'      => ['ar'=>'أخرى',           'en'=>'Other'],
    ];
    foreach ($expense_cats as $cat => $catTotal):
      $catLbl = LANG==='ar'
        ? ($expCatLabels[$cat]['ar'] ?? $cat)
        : ($expCatLabels[$cat]['en'] ?? $cat);
      $pct = $total_expenses > 0 ? round($catTotal / $total_expenses * 100, 1) : 0;
    ?>
    <div style="display:flex;align-items:center;gap:.6rem">
      <span style="width:90px;font-size:.78rem;font-weight:600;flex-shrink:0"><?= htmlspecialchars($catLbl) ?></span>
      <div style="flex:1;height:8px;background:var(--border);border-radius:4px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:var(--danger);border-radius:4px;opacity:.75"></div>
      </div>
      <span style="font-size:.78rem;font-family:var(--font-en);font-weight:700;color:var(--danger);white-space:nowrap">
        <?= number_format($catTotal) ?> <?= $currency ?>
      </span>
      <span style="font-size:.72rem;color:var(--muted);width:38px;text-align:right"><?= $pct ?>%</span>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Detailed expenses table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th><?= LANG==='ar'?'التاريخ':'Date' ?></th>
          <th><?= LANG==='ar'?'الفئة':'Category' ?></th>
          <th><?= LANG==='ar'?'الوصف / ما أُنفق عليه':'Description / What was spent on' ?></th>
          <th><?= LANG==='ar'?'أُضيف بواسطة':'Added By' ?></th>
          <th style="text-align:right"><?= LANG==='ar'?'المبلغ':'Amount' ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expense_rows as $idx => $ex):
          $cat = $ex['category'] ?? 'other';
          $catLbl = LANG==='ar'
            ? ($expCatLabels[$cat]['ar'] ?? $cat)
            : ($expCatLabels[$cat]['en'] ?? $cat);
          $desc = trim($ex['description'] ?? '');
        ?>
        <tr>
          <td style="color:var(--danger);font-weight:700"><?= $idx+1 ?></td>
          <td class="mono text-muted" style="font-size:.82rem"><?= date('d/m/Y', strtotime($ex['expense_date'])) ?></td>
          <td>
            <span style="display:inline-block;padding:.15rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700;background:rgba(220,38,38,.1);color:var(--danger)">
              <?= htmlspecialchars($catLbl) ?>
            </span>
          </td>
          <td style="max-width:280px">
            <?php if ($desc): ?>
              <span style="font-weight:600"><?= htmlspecialchars($desc) ?></span>
            <?php else: ?>
              <span style="color:var(--muted);font-style:italic;font-size:.82rem">
                <?= LANG==='ar'?'بدون وصف':'No description' ?>
              </span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--muted)"><?= sanitize($ex['added_by'] ?? '—') ?></td>
          <td style="text-align:right;font-family:var(--font-en);font-weight:800;color:var(--danger);font-size:.95rem">
            -<?= number_format((float)$ex['amount']) ?> <?= $currency ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:rgba(220,38,38,.06);font-weight:800">
          <td colspan="5" style="color:var(--danger)"><?= LANG==='ar'?'إجمالي المصروفات':'Total Expenses' ?></td>
          <td style="text-align:right;font-family:var(--font-en);color:var(--danger);font-size:1.05rem">
            -<?= number_format($total_expenses) ?> <?= $currency ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     NET PROFIT SUMMARY BOX
══════════════════════════════════════════════════════════ -->
<?php if ($has_exp_table && ($combined_sales_net > 0 || $total_expenses > 0)): ?>
<div class="card" style="margin-top:1rem;border:2px solid <?= $net_after_expenses >= 0 ? 'rgba(196,146,42,.4)' : 'rgba(220,38,38,.4)' ?>">
  <div class="card-title" style="font-size:1rem">
    <i class="fa fa-calculator" style="color:<?= $net_after_expenses >= 0 ? 'var(--brand)' : 'var(--danger)' ?>"></i>
    <?= LANG==='ar'?'ملخص الربح والخسارة':'Profit & Loss Summary' ?>
    <span style="font-size:.75rem;font-weight:400;color:var(--muted);margin-<?= ALIGN_START ?>:.5rem">(<?= $from ?> → <?= $to ?>)</span>
  </div>
  <div style="display:flex;flex-direction:column;gap:.5rem;max-width:420px">
    <div style="display:flex;justify-content:space-between;font-size:.9rem;padding:.3rem 0;border-bottom:1px solid var(--border)">
      <span style="color:var(--muted)"><?= LANG==='ar'?'مبيعات الفئات (كاتبوس)':'Category POS Sales' ?></span>
      <span class="mono fw-bold" style="color:var(--brand)"><?= number_format($cat_net_total) ?> <?= $currency ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.9rem;padding:.3rem 0;border-bottom:1px solid var(--border)">
      <span style="color:var(--muted)"><?= LANG==='ar'?'المبيعات التفصيلية':'Detailed Product Sales' ?></span>
      <span class="mono fw-bold" style="color:var(--brand)"><?= number_format($daily_grand_total) ?> <?= $currency ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.9rem;padding:.3rem 0;border-bottom:1px solid var(--border)">
      <span style="color:var(--muted)"><?= LANG==='ar'?'صافي الإيرادات':'Net Revenue' ?></span>
      <span class="mono fw-bold"><?= number_format($combined_sales_net) ?> <?= $currency ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.9rem;padding:.3rem 0;border-bottom:2px solid var(--border)">
      <span style="color:var(--danger)"><?= LANG==='ar'?'إجمالي المصروفات':'Total Expenses' ?></span>
      <span class="mono fw-bold" style="color:var(--danger)">-<?= number_format($total_expenses) ?> <?= $currency ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:900;padding:.5rem 0;color:<?= $net_after_expenses >= 0 ? 'var(--brand)' : 'var(--danger)' ?>">
      <span><?= LANG==='ar'?'صافي الربح':'NET PROFIT' ?></span>
      <span class="mono"><?= number_format($net_after_expenses) ?> <?= $currency ?></span>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function printDailySoldReport() {
  const lang = '<?= LANG ?>';
  const dir  = '<?= DIR ?>';
  const from = '<?= $from ?>';
  const to   = '<?= $to ?>';
  const storeName = '<?= store_name() ?>';
  const table = document.getElementById('dailySoldTable').outerHTML;
  const title = lang==='ar' ? 'تقرير المبيعات التفصيلي' : 'Detailed Sales Report';
  const period = lang==='ar' ? `الفترة: ${from} — ${to}` : `Period: ${from} — ${to}`;
  const grandTotal = '<?= format_currency($daily_grand_total) ?>';
  const html = `<!DOCTYPE html><html lang="${lang}" dir="${dir}"><head>
    <meta charset="UTF-8"><title>${title}</title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:'Tajawal','Tahoma','Arial',sans-serif;font-size:14px;font-weight:800;padding:20px;color:#000;direction:${dir};-webkit-print-color-adjust:exact;print-color-adjust:exact}
      body,table,th,td,div,span,strong{color:#000!important;font-weight:800}
      strong,b,h2,tfoot td,.grand-total{font-weight:900!important}
      h2{text-align:center;margin-bottom:4px;font-size:21px}
      .sub{text-align:center;color:#000;font-size:13px;font-weight:800;margin-bottom:12px}
      table{width:100%;border-collapse:collapse;margin-top:10px}
      th{background:#000;color:#fff!important;padding:7px;text-align:${dir==='rtl'?'right':'left'};font-size:13px;font-weight:900!important}
      td{padding:6px 7px;border-bottom:1px solid #000;font-size:13px}
      tfoot td{background:#fff;font-weight:900;border-top:3px solid #000}
      tr:nth-child(even){background:#fff}
      .grand-total{text-align:center;margin-top:15px;font-size:20px;font-weight:900;border:3px solid #000;padding:10px;border-radius:0;background:#fff;color:#000!important}
      @media print{@page{margin:10mm};button{display:none}body{font-weight:900}}
    </style>
  </head><body>
    <h2>${storeName}</h2>
    <div class="sub">${title}<br>${period}</div>
    ${table}
    <div class="grand-total">${lang==='ar'?'الإجمالي الكلي للفترة':'Grand Total for Period'}: ${grandTotal}</div>
    <script>window.onload=function(){setTimeout(function(){window.print()},400)}<\/script>
  </body></html>`;
  const w = window.open('','_blank','width=900,height=700');
  w.document.write(html); w.document.close();
}
</script>

<script src="/bangeen_pos/lib/chart.min.js"></script>
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
