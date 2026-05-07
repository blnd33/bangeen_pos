<?php
require_once __DIR__ . '/includes/config.php';
require_role('admin');
$db = DB::get();

if (isset($_GET['export'])) {
    $table   = $_GET['export'];
    $allowed = ['products','sales','sale_items','stock_log','users','categories','suppliers'];
    if (!in_array($table, $allowed)) { flash('Invalid table','error'); header('Location: backup.php?lang='.LANG); exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$table.'_'.date('Y-m-d').'.csv"');
    echo "\xEF\xBB\xBF";
    $rows = $db->query("SELECT * FROM `$table` ORDER BY id DESC")->fetchAll();
    if ($rows) {
        echo implode(',', array_map(fn($k)=>'"'.$k.'"', array_keys($rows[0])))."\n";
        foreach ($rows as $r) echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',(string)$v).'"', $r))."\n";
    }
    exit;
}

$page_title = t('backup');
$active_nav = 'backup';
require_once __DIR__ . '/includes/layout.php';

$counts = [];
foreach (['products','sales','sale_items','stock_log','users','categories','suppliers'] as $t)
    $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();

$tables_info = [
    'products'   => ['icon'=>'fa-box-open',  'label_ar'=>'المنتجات',      'label_en'=>'Products',   'color'=>'#C9971E'],
    'sales'      => ['icon'=>'fa-receipt',   'label_ar'=>'المبيعات',      'label_en'=>'Sales',      'color'=>'#16a34a'],
    'sale_items' => ['icon'=>'fa-list-ul',   'label_ar'=>'بنود المبيعات', 'label_en'=>'Sale Items', 'color'=>'#0ea5e9'],
    'stock_log'  => ['icon'=>'fa-warehouse', 'label_ar'=>'سجل المخزون',   'label_en'=>'Stock Log',  'color'=>'#8b5cf6'],
    'categories' => ['icon'=>'fa-tags',      'label_ar'=>'الفئات',        'label_en'=>'Categories', 'color'=>'#f59e0b'],
    'suppliers'  => ['icon'=>'fa-truck',     'label_ar'=>'الموردون',      'label_en'=>'Suppliers',  'color'=>'#ec4899'],
    'users'      => ['icon'=>'fa-users',     'label_ar'=>'المستخدمون',    'label_en'=>'Users',      'color'=>'#64748b'],
];
?>
<style>
.bk-header{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.4rem 1.75rem;margin-bottom:1.4rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.bk-header h2{font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:.25rem}
.bk-header p{font-size:.82rem;color:var(--muted)}
.bk-badge{display:flex;align-items:center;gap:.4rem;background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.25);color:#16a34a;border-radius:99px;padding:.32rem .85rem;font-size:.72rem;font-weight:700;white-space:nowrap}

.export-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:.85rem;margin-bottom:1.4rem}
.export-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.2rem;display:flex;flex-direction:column;gap:.85rem;transition:box-shadow .15s,border-color .15s}
.export-card:hover{border-color:var(--brand);box-shadow:0 4px 16px rgba(196,146,42,.1)}
.ec-top{display:flex;align-items:center;gap:.7rem}
.ec-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.ec-name{font-size:.88rem;font-weight:700;color:var(--text)}
.ec-count{font-size:.72rem;color:var(--muted);font-family:var(--font-en)}
.ec-btn{display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.48rem;border-radius:8px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);font-size:.78rem;font-weight:700;text-decoration:none;transition:all .15s;font-family:var(--font)}
.ec-btn:hover{background:var(--brand);border-color:var(--brand);color:#fff}

.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.sec-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.35rem}
.sec-title{display:flex;align-items:center;gap:.55rem;font-size:.92rem;font-weight:800;color:var(--text);margin-bottom:1rem;padding-bottom:.7rem;border-bottom:1px solid var(--border)}

.chip-row{display:flex;flex-wrap:wrap;gap:.38rem;margin-bottom:.9rem}
.chip{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:99px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);font-size:.7rem;font-weight:600;text-decoration:none;transition:all .15s;font-family:var(--font-en)}
.chip:hover{background:var(--brand);border-color:var(--brand);color:#fff}

.info-row{display:flex;align-items:center;justify-content:space-between;padding:.55rem 0;border-bottom:1px solid var(--border);font-size:.83rem}
.info-row:last-child{border-bottom:none}
.info-key{color:var(--muted)}
.info-val{font-family:var(--font-en);font-weight:700;color:var(--text);font-size:.8rem}

.sql-tip{background:rgba(14,165,233,.07);border:1px solid rgba(14,165,233,.2);border-radius:8px;padding:.65rem .85rem;font-size:.77rem;color:var(--info);display:flex;align-items:flex-start;gap:.45rem;line-height:1.5;margin-top:.85rem}
</style>

<div class="bk-header">
  <div>
    <h2><i class="fa fa-database" style="color:var(--brand);margin-<?= ALIGN_END ?>:.4rem"></i><?= LANG==='ar'?'النسخ الاحتياطي والتصدير':'Backup & Export' ?></h2>
    <p><?= LANG==='ar'?'صدّر بياناتك بصيغة CSV متوافقة مع Excel في أي وقت':'Export your data as Excel-compatible CSV files anytime' ?></p>
  </div>
  <div class="bk-badge"><i class="fa fa-circle-check"></i><?= LANG==='ar'?'النظام يعمل':'System OK' ?></div>
</div>

<div class="export-grid">
  <?php foreach ($tables_info as $table => $info): ?>
  <div class="export-card">
    <div class="ec-top">
      <div class="ec-icon" style="background:<?= $info['color'] ?>18;color:<?= $info['color'] ?>">
        <i class="fa <?= $info['icon'] ?>"></i>
      </div>
      <div>
        <div class="ec-name"><?= LANG==='ar' ? $info['label_ar'] : $info['label_en'] ?></div>
        <div class="ec-count"><?= number_format($counts[$table]) ?> <?= LANG==='ar'?'سجل':'records' ?></div>
      </div>
    </div>
    <a href="?export=<?= $table ?>&lang=<?= LANG ?>" class="ec-btn">
      <i class="fa fa-download"></i> <?= LANG==='ar'?'تنزيل CSV':'Download CSV' ?>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="bottom-grid">
  <div class="sec-card">
    <div class="sec-title"><i class="fa fa-layer-group text-brand"></i><?= LANG==='ar'?'تصدير الكل دفعة واحدة':'Export All at Once' ?></div>
    <p style="font-size:.81rem;color:var(--muted);margin-bottom:.8rem"><?= LANG==='ar'?'انقر على أي جدول لتنزيله:':'Click any table to download:' ?></p>
    <div class="chip-row">
      <?php foreach (array_keys($tables_info) as $table): ?>
      <a href="?export=<?= $table ?>&lang=<?= LANG ?>" class="chip"><i class="fa fa-file-csv"></i><?= $table ?></a>
      <?php endforeach; ?>
    </div>
    <div class="sql-tip">
      <i class="fa fa-circle-info" style="margin-top:2px;flex-shrink:0"></i>
      <span><?= LANG==='ar'?'نسخة SQL كاملة: phpMyAdmin → bangeen_pos → تصدير → SQL':'Full SQL: phpMyAdmin → bangeen_pos → Export → SQL format' ?></span>
    </div>
  </div>

  <div class="sec-card">
    <div class="sec-title"><i class="fa fa-server text-brand"></i><?= LANG==='ar'?'معلومات النظام':'System Info' ?></div>
    <div class="info-row"><span class="info-key"><?= LANG==='ar'?'الإصدار':'Version' ?></span><span class="info-val"><?= POS_VERSION ?></span></div>
    <div class="info-row"><span class="info-key">PHP</span><span class="info-val"><?= PHP_VERSION ?></span></div>
    <div class="info-row"><span class="info-key"><?= LANG==='ar'?'قاعدة البيانات':'Database' ?></span><span class="info-val"><?= DB_NAME ?></span></div>
    <div class="info-row"><span class="info-key"><?= LANG==='ar'?'التاريخ':'Date' ?></span><span class="info-val"><?= date('Y-m-d H:i') ?></span></div>
    <div class="info-row"><span class="info-key"><?= LANG==='ar'?'المسار':'Path' ?></span><span class="info-val" style="font-size:.7rem;word-break:break-all;max-width:170px;text-align:<?= ALIGN_END ?>"><?= basename(BASE_PATH) ?></span></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>