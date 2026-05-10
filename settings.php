<?php
require_once __DIR__ . '/includes/config.php';
require_role('admin');
$db = DB::get();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $fields = [
        'store_name_ar','store_name_en',
        'store_address_ar','store_address_en',
        'store_phone','currency','currency_symbol',
        'tax_rate','receipt_footer_ar','receipt_footer_en',
        'low_stock_threshold','default_lang',
    ];
    $stmt = $db->prepare("INSERT INTO settings (key_name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?");
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $stmt->execute([$f, $val, $val]);
    }
    $new_pw = trim($_POST['new_password'] ?? '');
    if ($new_pw) {
        $me = current_user();
        $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
           ->execute([password_hash($new_pw, PASSWORD_DEFAULT), $me['id']]);
    }
    flash(LANG==='ar'?'تم حفظ الإعدادات':'Settings saved');
    header('Location: settings.php?lang='.LANG); exit;
}

$rows = $db->query("SELECT key_name,value FROM settings")->fetchAll();
$all  = [];
foreach ($rows as $r) { $all[$r['key_name']] = $r['value']; }

$page_title = t('settings');
$active_nav = 'settings';
require_once __DIR__ . '/includes/layout.php';

function sv($key, $default='') {
    global $all;
    return htmlspecialchars(isset($all[$key]) ? $all[$key] : $default, ENT_QUOTES, 'UTF-8');
}
?>

<form method="POST">
<div style="display:flex;flex-direction:column;gap:1.25rem;max-width:860px">

  <!-- Store Name -->
  <div class="card">
    <div class="card-title">
      <i class="fa fa-store text-brand"></i>
      <?= LANG==='ar'?'معلومات المتجر':'Store Information' ?>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="form-group">
        <label><?= LANG==='ar'?'اسم المتجر (عربي)':'Store Name (Arabic)' ?></label>
        <input type="text" name="store_name_ar" value="<?= sv('store_name_ar','بهنگین کریستال') ?>" dir="rtl">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'اسم المتجر (إنجليزي)':'Store Name (English)' ?></label>
        <input type="text" name="store_name_en" value="<?= sv('store_name_en','Bangeen Crystal') ?>" dir="ltr">
      </div>
    </div>
  </div>

  <!-- Currency & Tax -->
  <div class="card">
    <div class="card-title">
      <i class="fa fa-coins text-brand"></i>
      <?= LANG==='ar'?'العملة والضريبة':'Currency & Tax' ?>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
      <div class="form-group">
        <label><?= LANG==='ar'?'رمز العملة':'Currency Code' ?></label>
        <input type="text" name="currency" value="<?= sv('currency','IQD') ?>" dir="ltr">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'الرمز المعروض':'Display Symbol' ?></label>
        <input type="text" name="currency_symbol" value="<?= sv('currency_symbol','IQD') ?>" dir="ltr">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'نسبة الضريبة %':'Tax Rate %' ?></label>
        <input type="number" name="tax_rate" value="<?= sv('tax_rate','0') ?>" step="0.01" min="0" max="100">
      </div>
    </div>
  </div>

  <!-- Receipt Settings -->
  <div class="card">
    <div class="card-title">
      <i class="fa fa-receipt text-brand"></i>
      <?= LANG==='ar'?'إعدادات الإيصال':'Receipt Settings' ?>
    </div>

    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:.75rem 1rem;margin-bottom:1.2rem;font-size:.82rem;color:var(--muted)">
      <i class="fa fa-circle-info text-brand"></i>
      <?= LANG==='ar'
        ?'شعار بهنگین کریستال يظهر تلقائياً في أعلى كل إيصال. أضف العنوان والهاتف أدناه.'
        :'The Bangeen Crystal logo appears automatically at the top of every receipt. Add address and phone below.' ?>
    </div>

    <!-- Footer text -->
    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="form-group">
        <label><?= LANG==='ar'?'نص التذييل — عربي':'Footer Text — Arabic' ?></label>
        <input type="text" name="receipt_footer_ar"
          value="<?= sv('receipt_footer_ar','شكراً لزيارتكم') ?>"
          dir="rtl" placeholder="شكراً لزيارتكم">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'نص التذييل — إنجليزي':'Footer Text — English' ?></label>
        <input type="text" name="receipt_footer_en"
          value="<?= sv('receipt_footer_en','Thank you for your visit') ?>"
          dir="ltr" placeholder="Thank you for your visit">
      </div>
    </div>

    <!-- Address -->
    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="form-group">
        <label><?= LANG==='ar'?'عنوان المتجر — عربي (يطبع في الإيصال)':'Store Address — Arabic (printed on receipt)' ?></label>
        <input type="text" name="store_address_ar"
          value="<?= sv('store_address_ar') ?>"
          dir="rtl" placeholder="دهوك، إقليم كردستان العراق">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'عنوان المتجر — إنجليزي (يطبع في الإيصال)':'Store Address — English (printed on receipt)' ?></label>
        <input type="text" name="store_address_en"
          value="<?= sv('store_address_en') ?>"
          dir="ltr" placeholder="Duhok, Kurdistan Region, Iraq">
      </div>
    </div>

    <!-- Phone -->
    <div class="form-group" style="max-width:320px">
      <label><?= LANG==='ar'?'رقم الهاتف (يطبع في الإيصال)':'Phone Number (printed on receipt)' ?></label>
      <input type="text" name="store_phone"
        value="<?= sv('store_phone') ?>"
        dir="ltr" placeholder="+964-750-730-8005">
      <small style="color:var(--muted);font-size:.72rem">
        <?= LANG==='ar'?'مثال: +964-750-730-8005':'Example: +964-750-730-8005' ?>
      </small>
    </div>
  </div>

  <!-- System Settings -->
  <div class="card">
    <div class="card-title">
      <i class="fa fa-gear text-brand"></i>
      <?= LANG==='ar'?'إعدادات النظام':'System Settings' ?>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="form-group">
        <label><?= LANG==='ar'?'حد المخزون المنخفض':'Low Stock Threshold' ?></label>
        <input type="number" name="low_stock_threshold" value="<?= sv('low_stock_threshold','10') ?>" min="0">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'اللغة الافتراضية':'Default Language' ?></label>
        <select name="default_lang">
          <option value="ar" <?= sv('default_lang','ar')==='ar'?'selected':'' ?>>عربي (Arabic)</option>
          <option value="en" <?= sv('default_lang','ar')==='en'?'selected':'' ?>>English</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-title">
      <i class="fa fa-lock text-brand"></i>
      <?= LANG==='ar'?'تغيير كلمة المرور':'Change My Password' ?>
    </div>
    <div class="form-group" style="max-width:320px">
      <label><?= LANG==='ar'?'كلمة المرور الجديدة (اتركها فارغة لعدم التغيير)':'New Password (leave blank to keep current)' ?></label>
      <input type="password" name="new_password" dir="ltr" autocomplete="new-password" placeholder="••••••••">
    </div>
  </div>

  <div>
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fa fa-save"></i>
      <?= LANG==='ar'?'حفظ الإعدادات':'Save Settings' ?>
    </button>
  </div>

</div>
</form>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>