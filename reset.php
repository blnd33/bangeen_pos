<?php
require_once __DIR__ . '/includes/config.php';
require_role('admin');
$db = DB::get();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $confirm  = trim($_POST['confirm_text'] ?? '');
    $password = $_POST['admin_password'] ?? '';

    $me = current_user();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
    $stmt->execute([$me['id']]);
    $row = $stmt->fetch();

    if (!password_verify($password, $row['password_hash'])) {
        $error = LANG==='ar' ? 'كلمة المرور غير صحيحة' : 'Incorrect password';

    } elseif ($action === 'reset_sales' && $confirm === 'RESET SALES') {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE sale_items");
        $db->exec("TRUNCATE TABLE sales");
        $db->exec("TRUNCATE TABLE stock_log");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        flash(LANG==='ar' ? 'تم حذف جميع بيانات المبيعات' : 'All sales data cleared');
        header('Location: http://localhost/bangeen_pos/reset.php?lang='.LANG); exit;

    } elseif ($action === 'reset_products' && $confirm === 'RESET PRODUCTS') {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE sale_items");
        $db->exec("TRUNCATE TABLE sales");
        $db->exec("TRUNCATE TABLE stock_log");
        $db->exec("TRUNCATE TABLE products");
        $db->exec("UPDATE counters SET value=0 WHERE name='barcode_seq'");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        flash(LANG==='ar' ? 'تم حذف جميع المنتجات والمبيعات' : 'All products and sales cleared');
        header('Location: http://localhost/bangeen_pos/reset.php?lang='.LANG); exit;

    } elseif ($action === 'reset_all' && $confirm === 'RESET ALL') {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec("TRUNCATE TABLE sale_items");
        $db->exec("TRUNCATE TABLE sales");
        $db->exec("TRUNCATE TABLE stock_log");
        $db->exec("TRUNCATE TABLE products");
        $db->exec("TRUNCATE TABLE categories");
        $db->exec("TRUNCATE TABLE suppliers");
        $db->exec("UPDATE counters SET value=0 WHERE name='barcode_seq'");
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        $db->exec("INSERT INTO categories (name_ar, name_en, color) VALUES
            ('هدايا منزلية','Home Gifts','#C4922A'),
            ('ديكور','Decor','#c0392b'),
            ('إكسسوارات','Accessories','#e67e22'),
            ('عطور','Fragrances','#8e44ad'),
            ('مستلزمات طعام','Kitchen & Dining','#27ae60')");
        flash(LANG==='ar' ? 'تم إعادة تعيين قاعدة البيانات بالكامل' : 'Full database reset complete');
        header('Location: http://localhost/bangeen_pos/reset.php?lang='.LANG); exit;

    } else {
        $error = LANG==='ar' ? 'نص التأكيد غير صحيح' : 'Confirmation text is incorrect';
    }
}

$counts = [
    'sales'     => (int)$db->query("SELECT COUNT(*) FROM sales")->fetchColumn(),
    'products'  => (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'stock_log' => (int)$db->query("SELECT COUNT(*) FROM stock_log")->fetchColumn(),
    'categories'=> (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'suppliers' => (int)$db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
];

$page_title = LANG==='ar' ? 'إعادة تعيين البيانات' : 'Data Reset';
$active_nav = 'backup';
require_once __DIR__ . '/includes/layout.php';
?>

<style>
.reset-card{border:2px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1.25rem;background:var(--surface)}
.reset-card.warning{border-color:rgba(217,119,6,.3)}
.reset-card.danger{border-color:rgba(220,38,38,.3)}
.reset-card.critical{border-color:rgba(220,38,38,.5);background:rgba(220,38,38,.03)}
.reset-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.stat-pill{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:99px;font-size:.75rem;font-weight:700;font-family:var(--font-en);background:var(--surface2);color:var(--text2);border:1px solid var(--border);margin:.15rem}
.confirm-input{font-family:var(--font-en)!important;font-weight:700!important;letter-spacing:.05em!important;text-transform:uppercase!important}
.warning-banner{background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.75rem}
</style>

<div class="warning-banner">
    <span style="font-size:1.4rem;flex-shrink:0">⚠️</span>
    <div>
        <div style="font-weight:700;color:var(--danger);margin-bottom:.25rem">
            <?= LANG==='ar' ? 'تحذير: هذه العملية لا يمكن التراجع عنها!' : 'Warning: These actions cannot be undone!' ?>
        </div>
        <div style="font-size:.85rem;color:var(--text2)">
            <?= LANG==='ar' ? 'تأكد من أخذ نسخة احتياطية أولاً.' : 'Make sure to take a backup first.' ?>
        </div>
        <a href="http://localhost/bangeen_pos/backup.php?lang=<?= LANG ?>" class="btn btn-sm btn-secondary mt-1">
            <i class="fa fa-database"></i> <?= LANG==='ar' ? 'النسخ الاحتياطي' : 'Go to Backup' ?>
        </a>
    </div>
</div>

<!-- Stats -->
<div class="card mb-2">
    <div class="card-title"><i class="fa fa-chart-pie text-brand"></i> <?= LANG==='ar' ? 'البيانات الحالية' : 'Current Data' ?></div>
    <span class="stat-pill"><i class="fa fa-receipt"></i> <?= $counts['sales'] ?> <?= LANG==='ar'?'فاتورة':'invoices' ?></span>
    <span class="stat-pill"><i class="fa fa-box"></i> <?= $counts['products'] ?> <?= LANG==='ar'?'منتج':'products' ?></span>
    <span class="stat-pill"><i class="fa fa-warehouse"></i> <?= $counts['stock_log'] ?> <?= LANG==='ar'?'حركة':'stock moves' ?></span>
    <span class="stat-pill"><i class="fa fa-tags"></i> <?= $counts['categories'] ?> <?= LANG==='ar'?'فئة':'categories' ?></span>
    <span class="stat-pill"><i class="fa fa-truck"></i> <?= $counts['suppliers'] ?> <?= LANG==='ar'?'مورد':'suppliers' ?></span>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fa fa-xmark"></i> <?= sanitize($error) ?></div>
<?php endif; ?>

<!-- Reset Sales Only -->
<div class="reset-card warning">
    <div class="flex-center gap-1 mb-1">
        <div class="reset-icon" style="background:rgba(217,119,6,.12);color:var(--warning)"><i class="fa fa-receipt"></i></div>
        <div>
            <div style="font-weight:800;font-size:1rem"><?= LANG==='ar' ? 'حذف بيانات المبيعات فقط' : 'Clear Sales Data Only' ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= LANG==='ar' ? 'يحذف: الفواتير وسجل المخزون — يبقي: المنتجات والفئات' : 'Deletes: invoices & stock log — Keeps: products & categories' ?></div>
        </div>
    </div>
    <form method="POST" onsubmit="return confirm('<?= LANG==='ar'?'هل أنت متأكد؟':'Are you sure?' ?>')">
        <input type="hidden" name="action" value="reset_sales">
        <div class="form-row" style="grid-template-columns:1fr 1fr;margin-bottom:.75rem">
            <div class="form-group">
                <label><?= LANG==='ar' ? 'اكتب RESET SALES للتأكيد' : 'Type RESET SALES to confirm' ?></label>
                <input type="text" name="confirm_text" class="confirm-input" placeholder="RESET SALES" required>
            </div>
            <div class="form-group">
                <label><?= LANG==='ar' ? 'كلمة مرور المدير' : 'Admin Password' ?></label>
                <input type="password" name="admin_password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-warning"><i class="fa fa-trash"></i> <?= LANG==='ar' ? 'حذف المبيعات' : 'Clear Sales' ?></button>
    </form>
</div>

<!-- Reset Products + Sales -->
<div class="reset-card danger">
    <div class="flex-center gap-1 mb-1">
        <div class="reset-icon" style="background:rgba(220,38,38,.12);color:var(--danger)"><i class="fa fa-box-open"></i></div>
        <div>
            <div style="font-weight:800;font-size:1rem"><?= LANG==='ar' ? 'حذف المنتجات والمبيعات' : 'Clear Products & Sales' ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= LANG==='ar' ? 'يحذف: المنتجات والفواتير — يبقي: الفئات والموردون' : 'Deletes: products & invoices — Keeps: categories & suppliers' ?></div>
        </div>
    </div>
    <form method="POST" onsubmit="return confirm('<?= LANG==='ar'?'هل أنت متأكد تماماً؟':'Are you absolutely sure?' ?>')">
        <input type="hidden" name="action" value="reset_products">
        <div class="form-row" style="grid-template-columns:1fr 1fr;margin-bottom:.75rem">
            <div class="form-group">
                <label><?= LANG==='ar' ? 'اكتب RESET PRODUCTS للتأكيد' : 'Type RESET PRODUCTS to confirm' ?></label>
                <input type="text" name="confirm_text" class="confirm-input" placeholder="RESET PRODUCTS" required>
            </div>
            <div class="form-group">
                <label><?= LANG==='ar' ? 'كلمة مرور المدير' : 'Admin Password' ?></label>
                <input type="password" name="admin_password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> <?= LANG==='ar' ? 'حذف المنتجات والمبيعات' : 'Clear Products & Sales' ?></button>
    </form>
</div>

<!-- Full Reset -->
<div class="reset-card critical">
    <div class="flex-center gap-1 mb-1">
        <div class="reset-icon" style="background:rgba(220,38,38,.15);color:var(--danger)"><i class="fa fa-skull"></i></div>
        <div>
            <div style="font-weight:800;font-size:1rem;color:var(--danger)"><?= LANG==='ar' ? '⚠️ إعادة تعيين كاملة' : '⚠️ Full Reset — Delete Everything' ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= LANG==='ar' ? 'يحذف كل شيء — يبقي: المستخدمون والإعدادات فقط' : 'Deletes everything — Keeps: users & settings only' ?></div>
        </div>
    </div>
    <form method="POST" onsubmit="return confirm('<?= LANG==='ar'?'تحذير أخير! هذا سيحذف كل شيء!':'Final warning! This deletes everything!' ?>')">
        <input type="hidden" name="action" value="reset_all">
        <div class="form-row" style="grid-template-columns:1fr 1fr;margin-bottom:.75rem">
            <div class="form-group">
                <label><?= LANG==='ar' ? 'اكتب RESET ALL للتأكيد' : 'Type RESET ALL to confirm' ?></label>
                <input type="text" name="confirm_text" class="confirm-input" placeholder="RESET ALL" required>
            </div>
            <div class="form-group">
                <label><?= LANG==='ar' ? 'كلمة مرور المدير' : 'Admin Password' ?></label>
                <input type="password" name="admin_password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-danger"><i class="fa fa-skull"></i> <?= LANG==='ar' ? 'إعادة تعيين كل شيء' : 'Reset Everything' ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>