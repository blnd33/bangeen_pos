<?php
// ============================================================
// Bangeen Crystal POS — Core Config + i18n
// بهنگین کریستال — الإعدادات الأساسية والترجمة
// ============================================================

if (!ob_get_level()) ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('POS_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('BARCODE_DIR', BASE_PATH . '/barcodes/');
define('BARCODE_URL', 'barcodes/');
define('BACKUP_DIR',  BASE_PATH . '/backups/');

define('DB_HOST',    'localhost');
define('DB_NAME',    'bangeen_pos');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Language ──────────────────────────────────────────────
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar','en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$LANG = $_SESSION['lang'] ?? 'ar';
define('LANG', $LANG);
define('DIR',  $LANG === 'ar' ? 'rtl' : 'ltr');
define('ALIGN_START', $LANG === 'ar' ? 'right' : 'left');
define('ALIGN_END',   $LANG === 'ar' ? 'left'  : 'right');

// ── Translations ──────────────────────────────────────────
$TRANSLATIONS = [
    // Nav
    'dashboard'       => ['ar'=>'لوحة التحكم',       'en'=>'Dashboard'],
    'pos'             => ['ar'=>'نقطة البيع',         'en'=>'POS / Sales'],
    'products'        => ['ar'=>'المنتجات',            'en'=>'Products'],
    'categories'      => ['ar'=>'الفئات',              'en'=>'Categories'],
    'suppliers'       => ['ar'=>'الموردون',            'en'=>'Suppliers'],
    'stock'           => ['ar'=>'المخزون',             'en'=>'Stock'],
    'sales_history'   => ['ar'=>'سجل المبيعات',       'en'=>'Sales History'],
    'exchange'        => ['ar'=>'المبادلة والاسترداد', 'en'=>'Exchange & Returns'],
    'reports'         => ['ar'=>'التقارير',            'en'=>'Reports'],
    'users'           => ['ar'=>'المستخدمون',          'en'=>'Users'],
    'settings'        => ['ar'=>'الإعدادات',           'en'=>'Settings'],
    'backup'          => ['ar'=>'النسخ الاحتياطي',    'en'=>'Backup'],
    'logout'          => ['ar'=>'تسجيل خروج',         'en'=>'Sign Out'],
    // Actions
    'add'             => ['ar'=>'إضافة',               'en'=>'Add'],
    'edit'            => ['ar'=>'تعديل',               'en'=>'Edit'],
    'delete'          => ['ar'=>'حذف',                 'en'=>'Delete'],
    'save'            => ['ar'=>'حفظ',                 'en'=>'Save'],
    'cancel'          => ['ar'=>'إلغاء',               'en'=>'Cancel'],
    'search'          => ['ar'=>'بحث',                 'en'=>'Search'],
    'print'           => ['ar'=>'طباعة',               'en'=>'Print'],
    'export'          => ['ar'=>'تصدير',               'en'=>'Export'],
    'filter'          => ['ar'=>'تصفية',               'en'=>'Filter'],
    // Products
    'product_name'    => ['ar'=>'اسم المنتج',          'en'=>'Product Name'],
    'price'           => ['ar'=>'السعر',               'en'=>'Price'],
    'cost'            => ['ar'=>'التكلفة',             'en'=>'Cost'],
    'stock'           => ['ar'=>'المخزون',             'en'=>'Stock'],
    'category'        => ['ar'=>'الفئة',               'en'=>'Category'],
    'supplier'        => ['ar'=>'المورد',              'en'=>'Supplier'],
    'barcode'         => ['ar'=>'الباركود',            'en'=>'Barcode'],
    'unit'            => ['ar'=>'الوحدة',              'en'=>'Unit'],
    // POS
    'scan_barcode'    => ['ar'=>'امسح الباركود',       'en'=>'Scan Barcode'],
    'cart'            => ['ar'=>'السلة',               'en'=>'Cart'],
    'subtotal'        => ['ar'=>'المجموع الجزئي',      'en'=>'Subtotal'],
    'discount'        => ['ar'=>'الخصم',               'en'=>'Discount'],
    'tax'             => ['ar'=>'الضريبة',             'en'=>'Tax'],
    'total'           => ['ar'=>'الإجمالي',            'en'=>'Total'],
    'checkout'        => ['ar'=>'الدفع',               'en'=>'Checkout'],
    'cash'            => ['ar'=>'نقد',                 'en'=>'Cash'],
    'card'            => ['ar'=>'بطاقة',               'en'=>'Card'],
    'split'           => ['ar'=>'مختلط',               'en'=>'Split'],
    'cash_tendered'   => ['ar'=>'المبلغ المستلم',      'en'=>'Cash Tendered'],
    'change'          => ['ar'=>'الباقي',              'en'=>'Change'],
    'receipt'         => ['ar'=>'الإيصال',             'en'=>'Receipt'],
    'clear_cart'      => ['ar'=>'إفراغ السلة',         'en'=>'Clear Cart'],
    // Stock
    'stock_in'        => ['ar'=>'إدخال مخزون',        'en'=>'Stock In'],
    'stock_out'       => ['ar'=>'إخراج مخزون',        'en'=>'Stock Out'],
    'low_stock'       => ['ar'=>'مخزون منخفض',        'en'=>'Low Stock'],
    // Reports
    'daily'           => ['ar'=>'يومي',                'en'=>'Daily'],
    'monthly'         => ['ar'=>'شهري',                'en'=>'Monthly'],
    'yearly'          => ['ar'=>'سنوي',                'en'=>'Yearly'],
    'date_from'       => ['ar'=>'من تاريخ',            'en'=>'Date From'],
    'date_to'         => ['ar'=>'إلى تاريخ',           'en'=>'Date To'],
    'total_sales'     => ['ar'=>'إجمالي المبيعات',    'en'=>'Total Sales'],
    'invoices'        => ['ar'=>'الفواتير',            'en'=>'Invoices'],
    'avg_ticket'      => ['ar'=>'متوسط الفاتورة',     'en'=>'Avg Ticket'],
    'top_products'    => ['ar'=>'أكثر المنتجات مبيعاً','en'=>'Top Products'],
    // Auth
    'login'           => ['ar'=>'تسجيل الدخول',       'en'=>'Login'],
    'username'        => ['ar'=>'اسم المستخدم',        'en'=>'Username'],
    'password'        => ['ar'=>'كلمة المرور',         'en'=>'Password'],
    'sign_in'         => ['ar'=>'دخول',                'en'=>'Sign In'],
    // Status
    'active'          => ['ar'=>'نشط',                 'en'=>'Active'],
    'inactive'        => ['ar'=>'غير نشط',             'en'=>'Inactive'],
    'completed'       => ['ar'=>'مكتمل',               'en'=>'Completed'],
    'void'            => ['ar'=>'ملغي',                'en'=>'Void'],
    // Misc
    'today_sales'     => ['ar'=>'مبيعات اليوم',       'en'=>"Today's Sales"],
    'total_products'  => ['ar'=>'إجمالي المنتجات',    'en'=>'Total Products'],
    'low_stock_alert' => ['ar'=>'تنبيه مخزون منخفض', 'en'=>'Low Stock Alert'],
    'invoice'         => ['ar'=>'فاتورة',              'en'=>'Invoice'],
    'date'            => ['ar'=>'التاريخ',             'en'=>'Date'],
    'cashier'         => ['ar'=>'أمين الصندوق',        'en'=>'Cashier'],
    'quantity'        => ['ar'=>'الكمية',              'en'=>'Quantity'],
    'actions'         => ['ar'=>'الإجراءات',           'en'=>'Actions'],
    'name_ar'         => ['ar'=>'الاسم بالعربية',      'en'=>'Name (Arabic)'],
    'name_en'         => ['ar'=>'الاسم بالإنجليزية',  'en'=>'Name (English)'],
    'no_results'      => ['ar'=>'لا توجد نتائج',      'en'=>'No results found'],
    'confirm_delete'  => ['ar'=>'هل أنت متأكد من الحذف؟','en'=>'Are you sure you want to delete?'],
    'role'            => ['ar'=>'الدور',               'en'=>'Role'],
    'admin'           => ['ar'=>'مدير النظام',         'en'=>'Admin'],
    'manager_role'    => ['ar'=>'مدير',                'en'=>'Manager'],
    'cashier_role'    => ['ar'=>'كاشير',               'en'=>'Cashier'],
    'payment_method'  => ['ar'=>'طريقة الدفع',        'en'=>'Payment Method'],
    'notes'           => ['ar'=>'ملاحظات',             'en'=>'Notes'],
    'status'          => ['ar'=>'الحالة',              'en'=>'Status'],
    'new_product'     => ['ar'=>'منتج جديد',           'en'=>'New Product'],
    'edit_product'    => ['ar'=>'تعديل منتج',          'en'=>'Edit Product'],
    'generate_barcode'=> ['ar'=>'توليد باركود',        'en'=>'Generate Barcode'],
    'print_label'     => ['ar'=>'طباعة الملصق',       'en'=>'Print Label'],
    'stock_level'     => ['ar'=>'مستوى المخزون',      'en'=>'Stock Level'],
    'threshold'       => ['ar'=>'الحد الأدنى',        'en'=>'Min Threshold'],
    'items_in_cart'   => ['ar'=>'منتجات في السلة',    'en'=>'items in cart'],
    'empty_cart'      => ['ar'=>'السلة فارغة',        'en'=>'Cart is empty'],
    'add_to_cart'     => ['ar'=>'أضف للسلة',          'en'=>'Add to Cart'],
    'remove'          => ['ar'=>'حذف',                 'en'=>'Remove'],
    'sale_complete'   => ['ar'=>'تمت عملية البيع',    'en'=>'Sale Complete'],
    // Finance
    'finance'           => ['ar'=>'المالية',                  'en'=>'Finance'],
    'sales_report'      => ['ar'=>'تقرير المبيعات',          'en'=>'Sales Report'],
    'expenses'          => ['ar'=>'المصروفات',                'en'=>'Expenses'],
    'pl_statement'      => ['ar'=>'الأرباح والخسائر',        'en'=>'P&L Statement'],
    'debts'             => ['ar'=>'الديون',                   'en'=>'Debts'],
    'cash_flow'         => ['ar'=>'التدفق النقدي',           'en'=>'Cash Flow'],
    'add_expense'       => ['ar'=>'إضافة مصروف',             'en'=>'Add Expense'],
    'amount'            => ['ar'=>'المبلغ',                   'en'=>'Amount'],
    'total_expenses'    => ['ar'=>'إجمالي المصروفات',        'en'=>'Total Expenses'],
    'net_profit'        => ['ar'=>'صافي الربح',              'en'=>'Net Profit'],
    'gross_profit'      => ['ar'=>'الربح الإجمالي',          'en'=>'Gross Profit'],
    'revenue'           => ['ar'=>'الإيرادات',               'en'=>'Revenue'],
    'cost_of_goods'     => ['ar'=>'تكلفة البضاعة',           'en'=>'Cost of Goods'],
    'cash_in'           => ['ar'=>'النقد الداخل',            'en'=>'Cash In'],
    'cash_out'          => ['ar'=>'النقد الخارج',            'en'=>'Cash Out'],
    'net_cash'          => ['ar'=>'صافي النقد',              'en'=>'Net Cash'],
    'add_debt'          => ['ar'=>'إضافة دين',               'en'=>'Add Debt'],
    'party_name'        => ['ar'=>'اسم الطرف',               'en'=>'Party Name'],
    'due_date'          => ['ar'=>'تاريخ الاستحقاق',        'en'=>'Due Date'],
    'total_owed_to_us'  => ['ar'=>'إجمالي المستحق لنا',     'en'=>'Total Owed to Us'],
    'total_we_owe'      => ['ar'=>'إجمالي ما علينا',        'en'=>'Total We Owe'],
    'net_debt_position' => ['ar'=>'صافي موقف الدين',        'en'=>'Net Debt Position'],
    'owner'             => ['ar'=>'مالك',                    'en'=>'Owner'],
    // Permissions
    'permissions'       => ['ar'=>'الصلاحيات',              'en'=>'Permissions'],
    'manage_perms'      => ['ar'=>'إدارة الصلاحيات',        'en'=>'Manage Permissions'],
    'perms_saved'       => ['ar'=>'تم حفظ الصلاحيات بنجاح','en'=>'Permissions saved successfully'],
    'access_denied'     => ['ar'=>'ليس لديك صلاحية الوصول لهذه الصفحة','en'=>'You do not have permission to access this page'],
];

function t(string $key): string {
    global $TRANSLATIONS, $LANG;
    return $TRANSLATIONS[$key][$LANG] ?? $TRANSLATIONS[$key]['en'] ?? $key;
}

function name_field(array $row): string {
    return LANG === 'ar'
        ? ($row['name_ar'] ?? $row['name_en'] ?? '')
        : ($row['name_en'] ?: ($row['name_ar'] ?? ''));
}

// ── Database ──────────────────────────────────────────────
class DB {
    private static $instance = null;
    public static function get(): PDO {
        if (!self::$instance) {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }
}

// ── Auth ──────────────────────────────────────────────────
function is_logged_in(): bool { return isset($_SESSION['pos_user_id']); }

function require_login(): void {
    if (!is_logged_in()) {
        if (defined('API_REQUEST')) {
            http_response_code(401);
            die(json_encode(['success'=>false,'error'=>'Unauthorized']));
        }
        header('Location: /bangeen_pos/index.php');
        exit;
    }
}

function require_role(string ...$roles): void {
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles)) {
        header('Location: /bangeen_pos/dashboard.php?lang=' . LANG . '&denied=1');
        exit;
    }
}

function current_user(): array {
    return [
        'id'   => $_SESSION['pos_user_id'] ?? null,
        'name' => $_SESSION['pos_username'] ?? '',
        'role' => $_SESSION['pos_role']     ?? 'cashier',
    ];
}

// ── Permissions ───────────────────────────────────────────

/**
 * All manageable pages in the system.
 * key => ['icon', 'ar' label, 'en' label]
 */
function get_all_pages(): array {
    return [
        'dashboard'     => ['icon'=>'fa-gauge',            'ar'=>'لوحة التحكم',         'en'=>'Dashboard'],
        'pos'           => ['icon'=>'fa-cash-register',    'ar'=>'نقطة البيع',          'en'=>'POS / Sales'],
        'catpos'        => ['icon'=>'fa-tags',              'ar'=>'بيع الفئات',          'en'=>'Category POS'],
        'products'      => ['icon'=>'fa-box',              'ar'=>'المنتجات',             'en'=>'Products'],
        'categories'    => ['icon'=>'fa-tags',             'ar'=>'الفئات',              'en'=>'Categories'],
        'suppliers'     => ['icon'=>'fa-truck',            'ar'=>'الموردون',             'en'=>'Suppliers'],
        'stock'         => ['icon'=>'fa-warehouse',        'ar'=>'المخزون',             'en'=>'Stock'],
        'sales_history' => ['icon'=>'fa-clock-rotate-left','ar'=>'سجل المبيعات',       'en'=>'Sales History'],
        'exchange'      => ['icon'=>'fa-right-left',       'ar'=>'المبادلة والاسترداد','en'=>'Exchange & Returns'],
        'reports'       => ['icon'=>'fa-chart-bar',        'ar'=>'التقارير',            'en'=>'Reports'],
        'finance'       => ['icon'=>'fa-coins',            'ar'=>'المالية',             'en'=>'Finance'],
        'users'         => ['icon'=>'fa-users-gear',       'ar'=>'المستخدمون',          'en'=>'Users'],
        'permissions'   => ['icon'=>'fa-shield-halved',    'ar'=>'الصلاحيات',           'en'=>'Permissions'],
        'settings'      => ['icon'=>'fa-gear',             'ar'=>'الإعدادات',           'en'=>'Settings'],
        'backup'        => ['icon'=>'fa-floppy-disk',      'ar'=>'النسخ الاحتياطي',    'en'=>'Backup'],
    ];
}

/**
 * Pages restricted to admin/owner only — cannot be granted to other roles.
 */
function get_locked_pages(): array {
    return ['users', 'permissions', 'settings', 'backup'];
}

/**
 * Check if current user has permission for a given page key.
 * - owner  → always true
 * - admin  → always true
 * - others → checks user_permissions table
 */
function has_permission(string $page): bool {
    $user = current_user();
    if (!$user || !$user['id']) return false;

    // Owners and admins bypass all permission checks
    if (in_array($user['role'], ['owner', 'admin'])) return true;

    static $perm_cache = [];
    $uid = (int)$user['id'];

    if (!isset($perm_cache[$uid])) {
        try {
            $db   = DB::get();
            $stmt = $db->prepare("SELECT page, granted FROM user_permissions WHERE user_id = ?");
            $stmt->execute([$uid]);
            $perm_cache[$uid] = [];
            foreach ($stmt->fetchAll() as $r) {
                $perm_cache[$uid][$r['page']] = (bool)$r['granted'];
            }
        } catch (Exception $e) {
            // If table doesn't exist yet, fall back to deny
            return false;
        }
    }

    return $perm_cache[$uid][$page] ?? false;
}

/**
 * Redirect to dashboard with denied message if user lacks permission.
 * Use at the top of any page: require_permission('reports');
 */
function require_permission(string $page): void {
    if (!has_permission($page)) {
        flash(
            LANG === 'ar'
                ? 'ليس لديك صلاحية الوصول لهذه الصفحة'
                : 'You do not have permission to access this page',
            'error'
        );
        header('Location: /bangeen_pos/dashboard.php?lang=' . LANG . '&denied=1');
        exit;
    }
}

/**
 * Load all permissions for a specific user as [page => bool].
 * Used by the permissions manager page.
 */
function load_user_permissions(int $user_id): array {
    try {
        $db   = DB::get();
        $stmt = $db->prepare("SELECT page, granted FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $perms = [];
        foreach ($stmt->fetchAll() as $r) {
            $perms[$r['page']] = (bool)$r['granted'];
        }
        return $perms;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Save permissions for a user. Replaces all existing rows.
 * $pages_granted = array of page keys that should be granted.
 */
function save_user_permissions(int $user_id, array $pages_granted, string $role): bool {
    try {
        $db = DB::get();
        $all_pages    = array_keys(get_all_pages());
        $locked_pages = get_locked_pages();

        $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$user_id]);

        $ins = $db->prepare("INSERT INTO user_permissions (user_id, page, granted) VALUES (?, ?, ?)");
        foreach ($all_pages as $page) {
            $is_locked = in_array($page, $locked_pages) && !in_array($role, ['admin','owner']);
            $granted   = $is_locked ? 0 : (in_array($page, $pages_granted) ? 1 : 0);
            $ins->execute([$user_id, $page, $granted]);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── Helpers ───────────────────────────────────────────────
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $s = DB::get()->prepare("SELECT value FROM settings WHERE key_name=?");
        $s->execute([$key]);
        $r = $s->fetch();
        $cache[$key] = $r ? $r['value'] : $default;
    }
    return $cache[$key];
}

function store_name(): string {
    return LANG === 'ar'
        ? get_setting('store_name_ar', 'بهنگین کریستال')
        : get_setting('store_name_en', 'Bangeen Crystal');
}

function format_currency(float $amount): string {
    return get_setting('currency_symbol', 'IQD') . ' ' . number_format($amount, 0, '.', ',');
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

function generate_invoice_number(): string {
    $db = DB::get();
    $db->exec("UPDATE counters SET value = value + 1 WHERE name = 'invoice_seq'");
    $seq = (int)$db->query("SELECT value FROM counters WHERE name = 'invoice_seq'")->fetchColumn();
    return (string)$seq;
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function lang_switcher_url(string $target): string {
    $q = $_GET;
    $q['lang'] = $target;
    return '?' . http_build_query($q);
}
