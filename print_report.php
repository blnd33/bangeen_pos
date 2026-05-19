<?php
/**
 * Bangeen Crystal — Thermal Print Report (80mm paper)
 * Place at: bangeen_pos/print_report.php
 * Opens in new tab when clicking Daily/Monthly/Yearly buttons in reports.php
 */

require_once __DIR__ . '/includes/config.php';
require_role('admin', 'manager');

$db       = DB::get();
$currency = get_setting('currency_symbol', 'IQD');
$store    = store_name();
$is_ar    = (LANG === 'ar');

/* ── PERIOD ── */
$period = $_GET['period'] ?? 'daily';
$quick  = $_GET['quick']  ?? '';

switch ($quick) {
    case 'today':      $from = date('Y-m-d');      $to = date('Y-m-d');      break;
    case 'week':       $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); break;
    case 'month':      $from = date('Y-m-01');      $to = date('Y-m-d');      break;
    case 'year':       $from = date('Y-01-01');     $to = date('Y-m-d');      break;
    case 'last_month': $from = date('Y-m-01', strtotime('first day of last month'));
                       $to   = date('Y-m-t',  strtotime('last day of last month')); break;
    default:           $from = $_GET['from'] ?? date('Y-m-d');
                       $to   = $_GET['to']   ?? date('Y-m-d');               break;
}

$printed_at = date('d/m/Y  H:i:s');

/* ── HELPERS ── */
function pr_fc(float $n): string {
    global $currency;
    return number_format($n, 0, '.', ',') . ' ' . $currency;
}
function pr_t(string $ar, string $en): string {
    global $is_ar;
    return $is_ar ? $ar : $en;
}

/* ── CATPOS SALES ── */
$cat_stmt = $db->prepare("
    SELECT si.product_name_ar AS cat_ar, si.product_name_en AS cat_en,
           COUNT(*) AS sale_rows,
           SUM(si.unit_price) AS gross,
           SUM(CASE WHEN si.unit_price > si.subtotal THEN si.unit_price - si.subtotal ELSE 0 END) AS item_discount,
           SUM(si.subtotal) AS net
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE (si.product_id IS NULL OR si.barcode_text LIKE 'CAT-%')
      AND DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'
    GROUP BY si.product_name_en, si.product_name_ar
    ORDER BY net DESC
");
$cat_stmt->execute([$from, $to]);
$cat_sales          = $cat_stmt->fetchAll();
$cat_gross_total    = (float)array_sum(array_column($cat_sales, 'gross'));
$cat_discount_total = (float)array_sum(array_column($cat_sales, 'item_discount'));
$cat_net_total      = (float)array_sum(array_column($cat_sales, 'net'));

/* ── DETAILED SOLD ── */
$items_stmt = $db->prepare("
    SELECT p.name_ar, p.name_en,
           SUM(si.quantity) AS qty_sold,
           AVG(si.unit_price) AS avg_price,
           SUM(CASE WHEN si.discount_pct > 0 THEN si.unit_price*si.quantity*si.discount_pct/100 ELSE 0 END) AS total_discount,
           SUM(si.subtotal) AS final_total
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s    ON si.sale_id    = s.id
    WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'
    GROUP BY si.product_id, p.name_ar, p.name_en, p.cost
    ORDER BY final_total DESC
");
$items_stmt->execute([$from, $to]);
$sold_items          = $items_stmt->fetchAll();
$sold_grand_total    = (float)array_sum(array_column($sold_items, 'final_total'));
$sold_total_discount = (float)array_sum(array_column($sold_items, 'total_discount'));

/* ── EXPENSES ── */
$has_expenses = false; $expense_rows = []; $total_expenses = 0.0;
try {
    $desc_col = $is_ar ? 'COALESCE(description_ar, description_en)' : 'COALESCE(description_en, description_ar)';
    $exp = $db->prepare("SELECT $desc_col AS description, amount, expense_date AS created_at
                         FROM expenses
                         WHERE expense_date BETWEEN ? AND ?
                         ORDER BY expense_date ASC");
    $exp->execute([$from, $to]);
    $expense_rows   = $exp->fetchAll();
    $total_expenses = (float)array_sum(array_column($expense_rows, 'amount'));
    $has_expenses   = true;
} catch (\Exception $e) { /* table doesn't exist yet */ }

/* ── GRAND TOTAL ── */
$combined_net = $cat_net_total + $sold_grand_total;
$all_disc     = $cat_discount_total + $sold_total_discount;
$net_profit   = $combined_net - $total_expenses;

/* ── PERIOD LABEL ── */
$period_labels = [
    'daily'   => pr_t('يومي',  'Daily'),
    'monthly' => pr_t('شهري', 'Monthly'),
    'yearly'  => pr_t('سنوي', 'Yearly'),
];
$plabel = $period_labels[$period] ?? pr_t('يومي','Daily');
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= pr_t('تقرير','Report') ?> | <?= htmlspecialchars($store) ?></title>
<style>
/* ════════════ BASE ════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Tajawal', 'Courier New', monospace;
  background: #1C1410;
  color: #111;
  direction: <?= DIR ?>;
}

/* ════════════ SCREEN CONTROLS ════════════ */
.ctrl {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  padding: 10px 16px; background: #111;
  position: sticky; top: 0; z-index: 99; border-bottom: 1px solid #333;
}
.ctrl a, .ctrl button {
  text-decoration: none; padding: 5px 13px; border-radius: 6px;
  font-size: 11px; font-weight: 700; cursor: pointer; border: none;
  font-family: inherit;
}
.c-print  { background: #C4922A; color: #fff; }
.c-period { background: #2a2a2a; color: #aaa; border: 1px solid #444 !important; }
.c-period.on { background: #C4922A; color: #fff; border-color: #C4922A !important; }
.c-back   { color: #666 !important; font-size: 10px; margin-inline-start: auto; }
.ctrl form { display: flex; align-items: center; gap: 6px; }
.ctrl input[type=date] {
  padding: 4px 7px; border-radius: 5px; border: 1px solid #444;
  background: #222; color: #ccc; font-size: 10px; font-family: inherit;
}
.ctrl-sep { color: #444; font-size: 16px; }

/* ════════════ RECEIPT WRAPPER ════════════ */
.wrap { display: flex; justify-content: center; padding: 20px 10px; }

/* ════════════ RECEIPT (80mm = ~302px at 96dpi) ════════════ */
.receipt {
  width: 302px;
  background: #fff;
  padding: 10px 10px 18px;
  font-family: 'Tajawal', 'Courier New', 'Lucida Console', monospace;
  font-size: 12.5px;
  font-weight: 800;
  line-height: 1.5;
  color: #000;
  box-shadow: 0 0 30px rgba(0,0,0,.6);
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
.receipt, .receipt * { color:#000 !important; font-weight:800; }

/* ── Typography ── */
.tc  { text-align: center; }
.tr  { text-align: end; }
.b   { font-weight: 900 !important; }
.lg  { font-size: 15px; font-weight: 900 !important; }
.sm  { font-size: 10.8px; }
.dim { color: #000 !important; }

/* ── Dividers ── */
hr.dbl  { border: none; border-top: 2px solid #000; margin: 5px 0; }
hr.dash { border: none; border-top: 2px dashed #000; margin: 4px 0; }
.sp     { height: 4px; display: block; }

/* ── Two-column row ── */
.row {
  display: flex; justify-content: space-between;
  align-items: baseline; gap: 4px; margin: 2px 0;
}
.row .l { flex: 1; min-width: 0; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.row .r { white-space: nowrap; font-weight: 900; }
.row.sm .l, .row.sm .r { font-size: 10.8px; }

/* ── Section header ── */
.sec {
  text-align: center; font-weight: 900; font-size: 11.2px;
  letter-spacing: .5px; text-transform: uppercase;
  margin: 6px 0 3px;
}

/* ── Item block ── */
.item { margin: 3px 0 4px; }
.item .name { font-weight: 900; font-size: 12px; }
.item .meta {
  display: flex; justify-content: space-between;
  font-size: 10.8px; color: #000;
  padding-inline-start: 5px;
}

/* ── No data ── */
.nd { text-align: center; color: #000; font-style: italic; font-size: 10.8px; padding: 3px 0; }

/* ── Grand total box ── */
.gtbox { border: 3px solid #000; padding: 6px 8px; margin-top: 8px; }
.gtbox .big { font-size: 18px; font-weight: 900; text-align: center; margin-top: 3px; }

/* ════════════ PRINT — 80mm thermal ════════════ */
@media print {
  /* Force 80mm thermal width, portrait, continuous length */
  @page {
    size: 80mm auto !important;
    margin: 2mm 1mm !important;
  }
  html, body { background: #fff !important; }
  .ctrl, .print-tip { display: none !important; }
  .wrap { padding: 0 !important; justify-content: flex-start !important; }
  .receipt {
    width: 76mm !important;
    max-width: 76mm !important;
    box-shadow: none !important;
    padding: 0 1mm 8mm !important;
    font-size: 11.5px !important;
    font-weight: 900 !important;
    margin: 0 !important;
  }
}

/* ════════════ Print tip bar ════════════ */
.print-tip {
  background: #1a1a2e;
  color: #aaa;
  font-size: 10px;
  padding: 5px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.print-tip b { color: #f0c040; }
.print-tip .step {
  background: #2a2a3e;
  border: 1px solid #444;
  border-radius: 5px;
  padding: 3px 8px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}
.print-tip .step span {
  background: #f0c040;
  color: #111;
  border-radius: 3px;
  padding: 1px 5px;
  font-weight: 800;
  font-size: 9px;
}
</style>
</head>
<body>

<!-- PRINT TIP -->
<div class="print-tip">
  <b>⚡ <?= pr_t('للطباعة الحرارية 80mm:','For 80mm Thermal Print:') ?></b>
  <div class="step"><span>1</span> <?= pr_t('اضغط طباعة','Click Print') ?></div>
  <div class="step"><span>2</span> <?= pr_t('اختر الطابعة الحرارية','Select your thermal printer') ?></div>
  <div class="step"><span>3</span> <?= pr_t('حجم الورق ← مخصص: 80×297mm','Paper: Custom 80×297mm') ?></div>
  <div class="step"><span>4</span> <?= pr_t('الاتجاه: عمودي ↕','Portrait mode ↕') ?></div>
  <div class="step"><span>5</span> <?= pr_t('الهوامش: بدون','Margins: None') ?></div>
</div>

<!-- SCREEN CONTROLS -->
<div class="ctrl">
  <button class="c-print" onclick="doPrint()">🖨 <?= pr_t('طباعة','Print') ?></button>
  <span class="ctrl-sep">|</span>
  <?php foreach (['daily','monthly','yearly'] as $p): ?>
  <a href="?period=<?= $p ?>&from=<?= $from ?>&to=<?= $to ?>"
     class="c-period <?= $period===$p?'on':'' ?>"><?= $period_labels[$p] ?></a>
  <?php endforeach; ?>
  <span class="ctrl-sep">|</span>
  <form method="GET">
    <input type="hidden" name="period" value="<?= $period ?>">
    <span style="color:#666;font-size:10px"><?= pr_t('من','From') ?>:</span>
    <input type="date" name="from" value="<?= $from ?>">
    <span style="color:#666;font-size:10px"><?= pr_t('إلى','To') ?>:</span>
    <input type="date" name="to"   value="<?= $to ?>">
    <button type="submit" class="c-print" style="padding:4px 10px"><?= pr_t('تصفية','Filter') ?></button>
  </form>
  <a href="reports.php" class="c-back">← <?= pr_t('العودة','Back') ?></a>
</div>

<script>
function doPrint() {
  // Brief delay so browser can apply @page rules before dialog opens
  setTimeout(() => window.print(), 100);
}
</script>

<!-- RECEIPT -->
<div class="wrap"><div class="receipt">

  <!-- HEADER -->
  <div class="tc lg"><?= htmlspecialchars($store) ?></div>
  <div class="tc sm dim">Point of Sale</div>
  <span class="sp"></span>
  <div class="tc b"><?= $plabel ?> <?= pr_t('تقرير','Report') ?></div>
  <div class="tc sm">
    <?= $from === $to ? date('d/m/Y', strtotime($from)) : $from.' → '.$to ?>
  </div>
  <div class="tc sm dim"><?= pr_t('طُبع:','Printed:') ?> <?= $printed_at ?></div>

  <hr class="dbl">

  <!-- ══ SECTION 1: CATPOS ══ -->
  <div class="sec">── <?= pr_t('مبيعات الفئات','Category Sales') ?> ──</div>

  <?php if (empty($cat_sales)): ?>
    <div class="nd"><?= pr_t('لا توجد مبيعات','No category sales') ?></div>
  <?php else: ?>
    <?php foreach ($cat_sales as $i => $cs):
      $name  = $is_ar ? $cs['cat_ar'] : ($cs['cat_en'] ?: $cs['cat_ar']);
      $disc  = (float)$cs['item_discount'];
      $net   = (float)$cs['net'];
      $share = $cat_net_total > 0 ? round($net / $cat_net_total * 100, 1) : 0;
    ?>
    <div class="item">
      <div class="name"><?= ($i+1) ?>. <?= htmlspecialchars($name) ?></div>
      <div class="meta">
        <span><?= pr_t('فواتير','Inv') ?>: <?= (int)$cs['sale_rows'] ?></span>
        <span><?= pr_t('النسبة','Share') ?>: <?= $share ?>%</span>
      </div>
      <div class="meta">
        <span>
          <?php if ($disc > 0): ?>
            <?= pr_t('خصم','Disc') ?>: -<?= pr_fc($disc) ?>
          <?php else: ?>
            <?= pr_t('خصم: لا يوجد','Disc: None') ?>
          <?php endif; ?>
        </span>
        <span class="b"><?= pr_fc($net) ?></span>
      </div>
    </div>
    <?php if ($i < count($cat_sales)-1): ?><hr class="dash"><?php endif; ?>
    <?php endforeach; ?>

    <hr class="dash">
    <div class="row b">
      <span class="l"><?= pr_t('إجمالي الفئات','Cat Total') ?></span>
      <span class="r"><?= pr_fc($cat_net_total) ?></span>
    </div>
    <?php if ($cat_discount_total > 0): ?>
    <div class="row sm">
      <span class="l dim"><?= pr_t('إجمالي الخصومات','Discounts') ?></span>
      <span class="r dim">-<?= pr_fc($cat_discount_total) ?></span>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <hr class="dbl">

  <!-- ══ SECTION 2: DETAILED SOLD ══ -->
  <div class="sec">── <?= pr_t('المبيعات التفصيلية','Detailed Sales') ?> ──</div>

  <?php if (empty($sold_items)): ?>
    <div class="nd"><?= pr_t('لا توجد مبيعات','No sales') ?></div>
  <?php else: ?>
    <?php foreach ($sold_items as $i => $r):
      $name = $is_ar ? $r['name_ar'] : ($r['name_en'] ?: $r['name_ar']);
      $disc = (float)$r['total_discount'];
    ?>
    <div class="item">
      <div class="name"><?= ($i+1) ?>. <?= htmlspecialchars($name) ?></div>
      <div class="meta">
        <span>x<?= (int)$r['qty_sold'] ?> @ <?= pr_fc($r['avg_price']) ?></span>
        <span class="b"><?= pr_fc($r['final_total']) ?></span>
      </div>
      <?php if ($disc > 0): ?>
      <div class="meta">
        <span><?= pr_t('خصم','Disc') ?>: -<?= pr_fc($disc) ?></span>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($i < count($sold_items)-1): ?><hr class="dash"><?php endif; ?>
    <?php endforeach; ?>

    <hr class="dash">
    <div class="row b">
      <span class="l"><?= pr_t('إجمالي المبيعات','Sales Total') ?></span>
      <span class="r"><?= pr_fc($sold_grand_total) ?></span>
    </div>
    <?php if ($sold_total_discount > 0): ?>
    <div class="row sm">
      <span class="l dim"><?= pr_t('إجمالي الخصومات','Discounts') ?></span>
      <span class="r dim">-<?= pr_fc($sold_total_discount) ?></span>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <hr class="dbl">

  <!-- ══ SECTION 3: EXPENSES ══ -->
  <div class="sec">── <?= pr_t('المصروفات','Expenses') ?> ──</div>

  <?php if (!$has_expenses): ?>
    <div class="nd"><?= pr_t('جدول المصروفات غير موجود','Expenses table not set up') ?></div>
  <?php elseif (empty($expense_rows)): ?>
    <div class="nd"><?= pr_t('لا توجد مصروفات','No expenses') ?></div>
  <?php else: ?>
    <?php foreach ($expense_rows as $i => $ex): ?>
    <div class="row">
      <span class="l"><?= ($i+1) ?>. <?= htmlspecialchars($ex['description']) ?></span>
      <span class="r">-<?= pr_fc($ex['amount']) ?></span>
    </div>
    <div class="row sm">
      <span class="l dim"><?= date('d/m/Y', strtotime($ex['created_at'])) ?></span>
    </div>
    <?php endforeach; ?>
    <hr class="dash">
    <div class="row b">
      <span class="l"><?= pr_t('إجمالي المصروفات','Total Expenses') ?></span>
      <span class="r">-<?= pr_fc($total_expenses) ?></span>
    </div>
  <?php endif; ?>

  <hr class="dbl">

  <!-- ══ GRAND TOTAL BOX ══ -->
  <div class="gtbox">
    <div class="row sm">
      <span class="l dim"><?= pr_t('مبيعات الفئات','Cat POS') ?></span>
      <span class="r"><?= pr_fc($cat_net_total) ?></span>
    </div>
    <div class="row sm">
      <span class="l dim"><?= pr_t('المبيعات التفصيلية','Detailed Sales') ?></span>
      <span class="r"><?= pr_fc($sold_grand_total) ?></span>
    </div>
    <?php if ($all_disc > 0): ?>
    <div class="row sm">
      <span class="l dim"><?= pr_t('إجمالي الخصومات','Discounts') ?></span>
      <span class="r">-<?= pr_fc($all_disc) ?></span>
    </div>
    <?php endif; ?>
    <div class="row sm">
      <span class="l dim"><?= pr_t('صافي الإيرادات','Net Revenue') ?></span>
      <span class="r b"><?= pr_fc($combined_net) ?></span>
    </div>
    <?php if ($total_expenses > 0): ?>
    <div class="row sm">
      <span class="l dim"><?= pr_t('المصروفات','Expenses') ?></span>
      <span class="r">-<?= pr_fc($total_expenses) ?></span>
    </div>
    <?php endif; ?>
    <hr class="dash">
    <div class="tc sm b"><?= pr_t('صافي الربح','NET PROFIT') ?></div>
    <div class="big"><?= pr_fc($net_profit) ?></div>
  </div>

  <!-- FOOTER -->
  <span class="sp"></span><span class="sp"></span>
  <div class="tc sm dim">* * * <?= htmlspecialchars($store) ?> * * *</div>
  <div class="tc sm dim"><?= $printed_at ?></div>
  <span class="sp"></span><span class="sp"></span>

</div></div><!-- /.receipt /.wrap -->
</body>
</html>
