<?php
// ============================================================
// Bangeen Crystal POS — Core Config + i18n
// بهنگین کریستال — الإعدادات الأساسية والترجمة
// ============================================================

// Buffer all output so header() redirects always work regardless of output order
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

// Translation strings
$TRANSLATIONS = [
    // Nav
    'dashboard'       => ['ar'=>'لوحة التحكم',       'en'=>'Dashboard'],
    'pos'             => ['ar'=>'نقطة البيع',         'en'=>'POS / Sales'],
    'products'        => ['ar'=>'المنتجات',            'en'=>'Products'],
    'categories'      => ['ar'=>'الفئات',              'en'=>'Categories'],
    'suppliers'       => ['ar'=>'الموردون',            'en'=>'Suppliers'],
    'stock'           => ['ar'=>'المخزون',             'en'=>'Stock'],
    'sales_history'   => ['ar'=>'سجل المبيعات',       'en'=>'Sales History'],
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
    'finance' => ['ar'=>'المالية', 'en'=>'Finance'],
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
        if (defined('API_REQUEST')) { http_response_code(401); die(json_encode(['success'=>false,'error'=>'Unauthorized'])); }
        header('Location: /bangeen_pos/index.php'); exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    if (!in_array($_SESSION['pos_role'] ?? '', $roles)) {
        if (defined('API_REQUEST')) { http_response_code(403); die(json_encode(['success'=>false,'error'=>'Forbidden'])); }
        header('Location: /bangeen_pos/dashboard.php?err=forbidden'); exit;
    }
}

function current_user(): array {
    return ['id'=>$_SESSION['pos_user_id']??null,'name'=>$_SESSION['pos_username']??'','role'=>$_SESSION['pos_role']??'cashier'];
}

// ── Helpers ───────────────────────────────────────────────
function get_setting(string $key, string $default=''): string {
    static $cache=[];
    if (!isset($cache[$key])) {
        $s=$_=DB::get()->prepare("SELECT value FROM settings WHERE key_name=?");
        $s->execute([$key]);
        $r=$s->fetch();
        $cache[$key]=$r?$r['value']:$default;
    }
    return $cache[$key];
}

function store_name(): string {
    return LANG==='ar' ? get_setting('store_name_ar','بهنگین کریستال') : get_setting('store_name_en','Bangeen Crystal');
}

function format_currency(float $amount): string {
    return get_setting('currency_symbol','IQD').' '.number_format($amount,0,'.',',');
}

function json_response(array $data, int $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(string $s): string { return htmlspecialchars(trim($s),ENT_QUOTES,'UTF-8'); }

function generate_invoice_number(): string {
    return 'INV-'.date('Ymd').'-'.str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
}

function flash(string $msg, string $type='success'): void {
    $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type];
}

function lang_switcher_url(string $target): string {
    $q = $_GET;
    $q['lang'] = $target;
    return '?'.http_build_query($q);
}