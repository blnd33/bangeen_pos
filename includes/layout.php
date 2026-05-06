<?php
require_once __DIR__ . '/config.php';
require_login();
$user = current_user();
$sname = store_name();
$stmt = DB::get()->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold AND is_active=1");
$low_stock_count = (int)$stmt->fetchColumn();

$nav_items = [
    ['key'=>'dashboard',     'icon'=>'fa-gauge-high',    'href'=>'dashboard.php',  'roles'=>['admin','manager','cashier']],
    ['key'=>'pos',           'icon'=>'fa-cash-register', 'href'=>'pos.php',        'roles'=>['admin','manager','cashier']],
    ['sep'=>t('products')],
    ['key'=>'products',      'icon'=>'fa-box-open',      'href'=>'products.php',   'roles'=>['admin','manager','cashier']],
    ['key'=>'categories',    'icon'=>'fa-tags',          'href'=>'categories.php', 'roles'=>['admin','manager']],
    ['key'=>'suppliers',     'icon'=>'fa-truck',         'href'=>'suppliers.php',  'roles'=>['admin','manager']],
    ['key'=>'stock',         'icon'=>'fa-warehouse',     'href'=>'stock.php',      'roles'=>['admin','manager','cashier'], 'badge'=>$low_stock_count],
    ['sep'=>t('reports')],
    ['key'=>'sales_history', 'icon'=>'fa-receipt',       'href'=>'sales.php',      'roles'=>['admin','manager','cashier']],
    ['key'=>'reports',       'icon'=>'fa-chart-bar',     'href'=>'reports.php',    'roles'=>['admin','manager']],
    ['key'=>'finance', 'icon'=>'fa-coins', 'href'=>'finance.php', 'roles'=>['admin','manager']],
    ['sep'=>LANG==='ar'?'النظام':'System'],
    ['key'=>'users',         'icon'=>'fa-users',         'href'=>'users.php',      'roles'=>['admin','manager']],
    ['key'=>'settings',      'icon'=>'fa-gear',          'href'=>'settings.php',   'roles'=>['admin']],
    ['key'=>'backup',        'icon'=>'fa-database',      'href'=>'backup.php',     'roles'=>['admin']],
];
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($page_title ?? t('dashboard')) ?> — <?= sanitize($sname) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Raleway:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --brand:      #C4922A;
  --brand-dark: #9e7520;
  --brand-soft: rgba(196,146,42,.12);
  --cream:      #F5F0EB;
  --cream2:     #EDE8E3;
  --sidebar-bg: #12100A;
  --sidebar-bd: #2a2210;
  --bg:         #F0EBE5;
  --surface:    #FDFAF7;
  --surface2:   #F5F0EB;
  --border:     #DDD6CE;
  --text:       #1C1410;
  --text2:      #5C4A3A;
  --muted:      #9C8A7A;
  --success:    #16a34a;
  --warning:    #d97706;
  --danger:     #dc2626;
  --info:       #0ea5e9;
  --sidebar-w:  240px;
  --font-ar:    'Tajawal', sans-serif;
  --font-en:    'Raleway', sans-serif;
  --font:       <?= LANG==='ar' ? "var(--font-ar)" : "var(--font-en)" ?>;
  --radius:     10px;
  --radius-lg:  16px;
}

html,body{height:100%;overflow:hidden}
body{background:var(--bg);color:var(--text);font-family:var(--font);display:flex;direction:<?= DIR ?>}

/* ── SIDEBAR ── */
.sidebar{
  width:var(--sidebar-w);min-width:var(--sidebar-w);
  background:var(--sidebar-bg);
  border-<?= ALIGN_END ?>:1px solid var(--sidebar-bd);
  display:flex;flex-direction:column;height:100vh;
  position:fixed;<?= ALIGN_START ?>:0;top:0;z-index:100;
}

.sidebar-brand{
  padding:1.1rem .9rem;
  display:flex;align-items:center;gap:.7rem;
  border-bottom:1px solid var(--sidebar-bd);
  text-decoration:none;
}

.brand-logo-svg{width:42px;height:42px;flex-shrink:0}

.brand-text .b-name{
  font-family:var(--font-ar);font-weight:800;font-size:.95rem;
  color:#fff;white-space:nowrap;
}
.brand-text .b-sub{
  font-size:.6rem;color:rgba(255,255,255,.4);
  font-family:var(--font-en);letter-spacing:.05em;
}

.sidebar-nav{flex:1;overflow-y:auto;padding:.75rem 0;scrollbar-width:thin;scrollbar-color:var(--sidebar-bd) transparent}

.nav-sep{
  padding:.6rem .9rem .2rem;
  font-size:.62rem;letter-spacing:.09em;text-transform:uppercase;
  color:rgba(255,255,255,.25);
  font-family:var(--font-en);
}

.nav-item{
  display:flex;align-items:center;gap:.65rem;
  padding:.58rem .9rem;margin:.07rem .45rem;
  border-radius:8px;text-decoration:none;
  color:rgba(255,255,255,.55);font-size:.85rem;font-weight:500;
  transition:all .15s;position:relative;white-space:nowrap;
}
.nav-item:hover{background:rgba(255,255,255,.07);color:#fff}
.nav-item.active{background:var(--brand-soft);color:var(--brand);font-weight:700}
.nav-item.active::after{
  content:'';position:absolute;
  <?= ALIGN_START ?>:-0.45rem;top:50%;transform:translateY(-50%);
  width:3px;height:60%;background:var(--brand);border-radius:0 3px 3px 0;
}
<?php if(DIR==='rtl'): ?>
.nav-item.active::after{border-radius:3px 0 0 3px}
<?php endif; ?>
.nav-item i{width:17px;text-align:center;font-size:.85rem;flex-shrink:0}
.nav-badge{
  margin-<?= ALIGN_START ?>:auto;
  background:var(--danger);color:#fff;
  font-size:.65rem;padding:.1rem .35rem;border-radius:99px;min-width:18px;text-align:center;
}

.sidebar-footer{border-top:1px solid var(--sidebar-bd);padding:.9rem}
.user-info{display:flex;align-items:center;gap:.65rem;margin-bottom:.7rem}
.user-avatar{
  width:34px;height:34px;background:var(--brand);border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.95rem;color:#fff;flex-shrink:0;
}
.user-name{font-size:.82rem;font-weight:700;color:#fff}
.user-role{font-size:.65rem;color:rgba(255,255,255,.4);font-family:var(--font-en);text-transform:uppercase}
.btn-logout{
  display:flex;align-items:center;gap:.5rem;width:100%;
  padding:.45rem .7rem;background:rgba(255,255,255,.05);
  border:1px solid var(--sidebar-bd);border-radius:8px;
  color:rgba(255,255,255,.5);font-size:.82rem;font-family:var(--font);
  cursor:pointer;text-decoration:none;transition:all .15s;
}
.btn-logout:hover{background:rgba(220,38,38,.15);border-color:rgba(220,38,38,.4);color:#fca5a5}

/* ── MAIN ── */
.main-wrap{
  margin-<?= ALIGN_START ?>:var(--sidebar-w);flex:1;
  display:flex;flex-direction:column;height:100vh;overflow:hidden;
}

.topbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:0 1.5rem;height:54px;
  display:flex;align-items:center;gap:1rem;flex-shrink:0;
}
.topbar-title{font-size:1.05rem;font-weight:700;flex:1}
.topbar-actions{display:flex;align-items:center;gap:.5rem}
.topbar-time{font-family:var(--font-en);font-size:.8rem;color:var(--muted)}

.lang-btn{
  display:flex;align-items:center;gap:.35rem;
  padding:.32rem .65rem;border-radius:7px;font-size:.78rem;font-weight:700;
  background:var(--surface2);border:1px solid var(--border);
  color:var(--text2);text-decoration:none;transition:all .15s;font-family:var(--font-en);
}
.lang-btn:hover{border-color:var(--brand);color:var(--brand)}

.page-content{flex:1;overflow-y:auto;padding:1.5rem;scrollbar-width:thin;scrollbar-color:var(--border) transparent}

/* ── SHARED COMPONENTS ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem}
.card-title{font-size:1rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}

.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem}
.stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.75rem}
.stat-label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-family:var(--font-en);margin-bottom:.25rem}
.stat-value{font-size:1.65rem;font-weight:800;line-height:1;color:var(--text)}
.stat-sub{font-size:.78rem;color:var(--text2);margin-top:.3rem}

.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
.gap-2{gap:1rem}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.52rem .95rem;border-radius:8px;font-size:.84rem;font-weight:700;font-family:var(--font);cursor:pointer;border:1px solid transparent;transition:all .15s;text-decoration:none;white-space:nowrap}
.btn-primary{background:var(--brand);color:#fff;border-color:var(--brand)}
.btn-primary:hover{background:var(--brand-dark);box-shadow:0 4px 12px rgba(196,146,42,.3)}
.btn-secondary{background:var(--surface2);color:var(--text);border-color:var(--border)}
.btn-secondary:hover{border-color:var(--brand);color:var(--brand)}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:#15803d}
.btn-danger{background:rgba(220,38,38,.1);color:var(--danger);border-color:rgba(220,38,38,.3)}
.btn-danger:hover{background:var(--danger);color:#fff}
.btn-warning{background:rgba(217,119,6,.1);color:var(--warning);border-color:rgba(217,119,6,.3)}
.btn-warning:hover{background:var(--warning);color:#fff}
.btn-sm{padding:.32rem .65rem;font-size:.78rem}
.btn-lg{padding:.75rem 1.5rem;font-size:1rem}
.btn-full{width:100%;justify-content:center}

/* Form */
.form-row{display:grid;gap:1rem;margin-bottom:1rem}
.form-group{display:flex;flex-direction:column;gap:.4rem}
.label,label{font-size:.72rem;font-family:var(--font-en);color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
input,select,textarea{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:.58rem .8rem;color:var(--text);font-size:.9rem;font-family:var(--font);outline:none;transition:border-color .15s,box-shadow .15s;width:100%}
input:focus,select:focus,textarea:focus{border-color:var(--brand);box-shadow:0 0 0 2.5px var(--brand-soft)}
select option{background:var(--surface2)}

/* Table */
.table-wrap{overflow-x:auto;border-radius:10px;border:1px solid var(--border)}
table{width:100%;border-collapse:collapse;font-size:.87rem}
th{background:var(--surface2);padding:.6rem 1rem;text-align:<?= ALIGN_START ?>;font-family:var(--font-en);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:.62rem 1rem;border-bottom:1px solid var(--border);color:var(--text2);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--surface2)}

/* Badges */
.badge{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .5rem;border-radius:99px;font-size:.7rem;font-weight:700;font-family:var(--font-en)}
.badge-success{background:rgba(22,163,74,.12);color:var(--success)}
.badge-danger{background:rgba(220,38,38,.12);color:var(--danger)}
.badge-warning{background:rgba(217,119,6,.12);color:var(--warning)}
.badge-info{background:rgba(14,165,233,.12);color:var(--info)}
.badge-muted{background:var(--surface2);color:var(--muted)}
.badge-brand{background:var(--brand-soft);color:var(--brand)}

/* Alerts */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-danger{background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);color:#b91c1c}
.alert-success{background:rgba(22,163,74,.08);border:1px solid rgba(22,163,74,.25);color:#15803d}
.alert-warning{background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.25);color:#b45309}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(3px);z-index:1000;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.75rem;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;animation:modalIn .22s cubic-bezier(.16,1,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem}
.modal-title{font-size:1.05rem;font-weight:700}
.modal-close{width:30px;height:30px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text2);cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:all .15s}
.modal-close:hover{background:var(--danger);border-color:var(--danger);color:#fff}

/* Toast */
.toast-container{position:fixed;bottom:1.25rem;<?= ALIGN_END ?>:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:.4rem}
.toast{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.65rem 1rem;font-size:.85rem;display:flex;align-items:center;gap:.5rem;min-width:240px;max-width:360px;animation:toastIn .25s cubic-bezier(.16,1,.3,1);box-shadow:0 6px 20px rgba(0,0,0,.15)}
@keyframes toastIn{from{opacity:0;transform:translateX(<?= DIR==='rtl'?'-100%':'100%' ?>)}to{opacity:1;transform:translateX(0)}}
.toast.success{border-color:rgba(22,163,74,.35)}
.toast.error{border-color:rgba(220,38,38,.35)}
.toast.warning{border-color:rgba(217,119,6,.35)}

/* Scrollbar */
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:99px}

/* Utils */
.text-right{text-align:right}.text-center{text-align:center}.text-muted{color:var(--muted)}.text-success{color:var(--success)}.text-danger{color:var(--danger)}.text-warning{color:var(--warning)}.text-brand{color:var(--brand)}.mono{font-family:var(--font-en)!important}.flex{display:flex}.flex-center{display:flex;align-items:center}.gap-1{gap:.5rem}.flex-between{display:flex;align-items:center;justify-content:space-between}.fw-bold{font-weight:700}.mt-1{margin-top:.5rem}.mt-2{margin-top:1rem}.mb-1{margin-bottom:.5rem}.mb-2{margin-bottom:1rem}.w-100{width:100%}
.divider{height:1px;background:var(--border);margin:1rem 0}
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <a href="/bangeen_pos/dashboard.php" class="sidebar-brand">
    <!-- Bangeen Crystal CB Logo SVG -->
    <svg class="brand-logo-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <!-- Outer C arc -->
      <path d="M54,10 A40,40 0 1,0 54,90" fill="none" stroke="#C4922A" stroke-width="11" stroke-linecap="round"/>
      <!-- B vertical bar -->
      <line x1="50" y1="26" x2="50" y2="74" stroke="#C4922A" stroke-width="9" stroke-linecap="round"/>
      <!-- B top bump -->
      <path d="M50,26 Q74,26 74,40 Q74,50 50,50" fill="none" stroke="#C4922A" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
      <!-- B bottom bump -->
      <path d="M50,50 Q77,50 77,64 Q77,74 50,74" fill="none" stroke="#C4922A" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div class="brand-text">
      <div class="b-name"><?= LANG==='ar' ? 'بهنگین کریستال' : 'Bangeen Crystal' ?></div>
      <div class="b-sub"><?= LANG==='ar' ? 'Bangeen Crystal POS' : 'نظام نقطة البيع' ?></div>
    </div>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($nav_items as $item): ?>
      <?php if (isset($item['sep'])): ?>
        <div class="nav-sep"><?= sanitize($item['sep']) ?></div>
      <?php elseif (in_array($user['role'], $item['roles'])): ?>
        <a href="/bangeen_pos/<?= $item['href'] ?>" class="nav-item <?= ($active_nav??'')===$item['key']?'active':'' ?>">
          <i class="fa <?= $item['icon'] ?>"></i>
          <?= t($item['key']) ?>
          <?php if (!empty($item['badge'])): ?>
            <span class="nav-badge"><?= $item['badge'] ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(mb_substr($user['name'],0,1)) ?></div>
      <div>
        <div class="user-name"><?= sanitize($user['name']) ?></div>
        <div class="user-role"><?= $user['role'] ?></div>
      </div>
    </div>
    <a href="/bangeen_pos/logout.php" class="btn-logout">
      <i class="fa fa-right-from-bracket"></i> <?= t('logout') ?>
    </a>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main-wrap">
  <div class="topbar">
    <span class="topbar-title"><?= sanitize($page_title ?? t('dashboard')) ?></span>
    <div class="topbar-actions">
      <span class="topbar-time" id="topbar-clock"></span>
      <a href="<?= lang_switcher_url(LANG==='ar'?'en':'ar') ?>" class="lang-btn">
        <?= LANG==='ar' ? '🇬🇧 EN' : '🇮🇶 عربي' ?>
      </a>
    </div>
  </div>

  <div class="page-content" id="pageContent">
<!-- PAGE CONTENT ↓ -->
