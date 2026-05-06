<?php
require_once __DIR__ . '/includes/config.php';
require_role('admin');
$db = DB::get();

// Handle exports (before any output)
if (isset($_GET['export'])) {
    $table = $_GET['export'];
    $allowed = ['products','sales','sale_items','stock_log','users','categories','suppliers'];
    if (!in_array($table, $allowed)) { flash('Invalid table','error'); header('Location: backup.php?lang='.LANG); exit; }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$table.'_'.date('Y-m-d').'.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

    $rows = $db->query("SELECT * FROM `$table` ORDER BY id DESC")->fetchAll();
    if ($rows) {
        echo implode(',', array_map(fn($k)=>'"'.$k.'"', array_keys($rows[0])))."\n";
        foreach ($rows as $r) {
            echo implode(',', array_map(fn($v)=>'"'.str_replace('"','""',(string)$v).'"', $r))."\n";
        }
    }
    exit;
}

$page_title = t('backup');
$active_nav = 'backup';
require_once __DIR__ . '/includes/layout.php';

// Stats
$counts = [];
foreach (['products','sales','sale_items','stock_log','users','categories','suppliers'] as $t) {
    $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

$tables_info = [
    'products'   => ['icon'=>'fa-box-open',       'label_ar'=>'المنتجات',        'label_en'=>'Products'],
    'sales'      => ['icon'=>'fa-receipt',         'label_ar'=>'المبيعات',        'label_en'=>'Sales'],
    'sale_items' => ['icon'=>'fa-list',            'label_ar'=>'بنود المبيعات',   'label_en'=>'Sale Items'],
    'stock_log'  => ['icon'=>'fa-warehouse',       'label_ar'=>'سجل المخزون',     'label_en'=>'Stock Log'],
    'users'      => ['icon'=>'fa-users',           'label_ar'=>'المستخدمون',      'label_en'=>'Users'],
    'categories' => ['icon'=>'fa-tags',            'label_ar'=>'الفئات',          'label_en'=>'Categories'],
    'suppliers'  => ['icon'=>'fa-truck',           'label_ar'=>'الموردون',        'label_en'=>'Suppliers'],
];
?>

<div class="mb-2">
  <div class="alert alert-warning">
    <i class="fa fa-triangle-exclamation"></i>
    <?= LANG==='ar'
        ? 'يُنصح بأخذ نسخة احتياطية بانتظام. جميع الملفات بصيغة CSV متوافقة مع Excel.'
        : 'Regular backups are recommended. All files are exported as UTF-8 CSV compatible with Excel.' ?>
  </div>
</div>

<div class="grid-3 gap-2 mb-2">
  <?php foreach ($tables_info as $table => $info): ?>
  <div class="card">
    <div class="flex-between mb-1">
      <div class="flex-center gap-1">
        <div class="stat-icon" style="background:var(--brand-soft);color:var(--brand);width:36px;height:36px;margin:0">
          <i class="fa <?= $info['icon'] ?>"></i>
        </div>
        <div>
          <div class="fw-bold" style="font-size:.9rem"><?= LANG==='ar' ? $info['label_ar'] : $info['label_en'] ?></div>
          <div class="text-muted mono" style="font-size:.75rem"><?= number_format($counts[$table]) ?> <?= LANG==='ar'?'سجل':'records' ?></div>
        </div>
      </div>
    </div>
    <a href="?export=<?= $table ?>&lang=<?= LANG ?>" class="btn btn-secondary btn-full btn-sm">
      <i class="fa fa-download"></i> <?= LANG==='ar'?'تصدير CSV':'Export CSV' ?>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Full backup -->
<div class="card" style="max-width:600px">
  <div class="card-title"><i class="fa fa-database text-brand"></i> <?= LANG==='ar'?'نسخة احتياطية شاملة':'Complete Database Backup' ?></div>
  <p class="text-muted mb-2" style="font-size:.88rem">
    <?= LANG==='ar'
        ? 'قم بتصدير جميع الجداول دفعة واحدة. استخدم phpMyAdmin لنسخة احتياطية كاملة من قاعدة البيانات بصيغة SQL.'
        : 'Export all tables at once. For a full SQL backup, use phpMyAdmin to export the entire database.' ?>
  </p>

  <div class="flex gap-1 flex-wrap">
    <?php foreach (array_keys($tables_info) as $table): ?>
    <a href="?export=<?= $table ?>&lang=<?= LANG ?>" class="btn btn-sm btn-secondary">
      <i class="fa fa-file-csv"></i> <?= $table ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="divider"></div>

  <div class="alert alert-warning" style="font-size:.82rem">
    <i class="fa fa-circle-info"></i>
    <?= LANG==='ar'
        ? 'لنسخة SQL كاملة: افتح phpMyAdmin → اختر قاعدة البيانات bangeen_pos → تصدير → SQL'
        : 'For full SQL backup: Open phpMyAdmin → Select bangeen_pos → Export → SQL format' ?>
  </div>
</div>

<!-- System Info -->
<div class="card mt-2" style="max-width:600px">
  <div class="card-title"><i class="fa fa-circle-info text-brand"></i> <?= LANG==='ar'?'معلومات النظام':'System Info' ?></div>
  <div class="table-wrap">
    <table>
      <tbody>
        <tr><td class="text-muted"><?= LANG==='ar'?'إصدار النظام':'System Version' ?></td><td class="mono fw-bold"><?= POS_VERSION ?></td></tr>
        <tr><td class="text-muted"><?= LANG==='ar'?'إصدار PHP':'PHP Version' ?></td><td class="mono"><?= PHP_VERSION ?></td></tr>
        <tr><td class="text-muted"><?= LANG==='ar'?'قاعدة البيانات':'Database' ?></td><td class="mono"><?= DB_NAME ?></td></tr>
        <tr><td class="text-muted"><?= LANG==='ar'?'تاريخ النسخ':'Backup Date' ?></td><td class="mono"><?= date('Y-m-d H:i:s') ?></td></tr>
        <tr><td class="text-muted"><?= LANG==='ar'?'المسار':'Base Path' ?></td><td class="mono" style="font-size:.75rem;word-break:break-all"><?= BASE_PATH ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>