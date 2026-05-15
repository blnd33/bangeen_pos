<?php
// ============================================================
// Bangeen Crystal POS — User Permissions Manager
// ============================================================
require_once __DIR__ . '/includes/config.php';
$me = current_user();

if (!$me || !in_array($me['role'], ['admin','owner'])) {
    header('Location: dashboard.php?lang=' . LANG . '&denied=1');
    exit;
}

$db = DB::get();

// ── Page definitions (key => [icon, ar label, en label]) ───
$ALL_PAGES = [
    'dashboard'     => ['icon'=>'fa-gauge',           'ar'=>'لوحة التحكم',         'en'=>'Dashboard'],
    'pos'           => ['icon'=>'fa-cash-register',   'ar'=>'نقطة البيع',          'en'=>'POS / Sales'],
    'products'      => ['icon'=>'fa-box',             'ar'=>'المنتجات',             'en'=>'Products'],
    'categories'    => ['icon'=>'fa-tags',            'ar'=>'الفئات',              'en'=>'Categories'],
    'suppliers'     => ['icon'=>'fa-truck',           'ar'=>'الموردون',             'en'=>'Suppliers'],
    'stock'         => ['icon'=>'fa-warehouse',       'ar'=>'المخزون',             'en'=>'Stock'],
    'sales_history' => ['icon'=>'fa-clock-rotate-left','ar'=>'سجل المبيعات',       'en'=>'Sales History'],
    'exchange'      => ['icon'=>'fa-right-left',      'ar'=>'المبادلة والاسترداد','en'=>'Exchange & Returns'],
    'reports'       => ['icon'=>'fa-chart-bar',       'ar'=>'التقارير',            'en'=>'Reports'],
    'finance'       => ['icon'=>'fa-coins',           'ar'=>'المالية',             'en'=>'Finance'],
    'users'         => ['icon'=>'fa-users-gear',      'ar'=>'المستخدمون',          'en'=>'Users'],
    'settings'      => ['icon'=>'fa-gear',            'ar'=>'الإعدادات',           'en'=>'Settings'],
    'backup'        => ['icon'=>'fa-floppy-disk',     'ar'=>'النسخ الاحتياطي',    'en'=>'Backup'],
];

// Pages that are always locked to admin/owner only
$LOCKED_PAGES = ['users', 'settings', 'backup'];

// ── Handle AJAX save ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'save_permissions') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        // Validate user exists and is not owner (can't restrict owner)
        $target = $db->prepare("SELECT * FROM users WHERE id=?")->execute([$user_id]) ? $db->prepare("SELECT * FROM users WHERE id=?") : null;
        $stmt   = $db->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$user_id]);
        $target = $stmt->fetch();

        if (!$target) {
            echo json_encode(['success'=>false,'error'=>'User not found']);
            exit;
        }
        if ($target['role'] === 'owner') {
            echo json_encode(['success'=>false,'error'=>'Cannot restrict owner']);
            exit;
        }

        $pages_granted = $_POST['pages'] ?? [];

        // Delete existing permissions for this user
        $db->prepare("DELETE FROM user_permissions WHERE user_id=?")->execute([$user_id]);

        // Re-insert all pages
        $ins = $db->prepare("INSERT INTO user_permissions (user_id, page, granted) VALUES (?,?,?)");
        foreach (array_keys($ALL_PAGES) as $page) {
            $locked  = in_array($page, $LOCKED_PAGES) && !in_array($target['role'], ['admin','owner']);
            $granted = $locked ? 0 : (in_array($page, $pages_granted) ? 1 : 0);
            $ins->execute([$user_id, $page, $granted]);
        }

        // Log
        $who = LANG==='ar' ? ($me['name'] ?: 'admin') : ($me['name'] ?: 'admin');
        flash(LANG==='ar' ? 'تم حفظ الصلاحيات بنجاح' : 'Permissions saved successfully');
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($_POST['action'] === 'get_permissions') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $rows = $db->prepare("SELECT page, granted FROM user_permissions WHERE user_id=?");
        $rows->execute([$user_id]);
        $perms = [];
        foreach ($rows->fetchAll() as $r) {
            $perms[$r['page']] = (bool)$r['granted'];
        }
        echo json_encode(['success'=>true,'permissions'=>$perms]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

// ── Load all users (excluding owner if current user is not owner) ──
$users_query = "SELECT id, full_name_ar, full_name_en, username, role, is_active FROM users";
if ($me['role'] !== 'owner') {
    $users_query .= " WHERE role != 'owner'";
}
$users_query .= " ORDER BY FIELD(role,'owner','admin','manager','cashier'), full_name_ar";
$users = $db->query($users_query)->fetchAll();

// Load all permissions in one query
$all_perms_raw = $db->query("SELECT user_id, page, granted FROM user_permissions")->fetchAll();
$all_perms = [];
foreach ($all_perms_raw as $r) {
    $all_perms[$r['user_id']][$r['page']] = (bool)$r['granted'];
}

$role_colors  = ['owner'=>'badge-danger','admin'=>'badge-danger','manager'=>'badge-warning','cashier'=>'badge-info'];
$role_labels  = ['owner'=>['ar'=>'مالك','en'=>'Owner'],'admin'=>['ar'=>'مدير النظام','en'=>'Admin'],'manager'=>['ar'=>'مدير','en'=>'Manager'],'cashier'=>['ar'=>'كاشير','en'=>'Cashier']];

$page_title = LANG==='ar' ? 'إدارة الصلاحيات' : 'Permissions';
$active_nav = 'users';
require_once __DIR__ . '/includes/layout.php';
?>

<style>
.perm-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
.perm-header h2{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem}
.user-tabs{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.25rem}
.user-tab{padding:.4rem 1rem;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);color:var(--text2);font-size:.82rem;font-weight:700;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:.4rem;white-space:nowrap}
.user-tab:hover{border-color:var(--brand);color:var(--brand)}
.user-tab.active{border-color:var(--brand);background:var(--brand);color:#fff;box-shadow:0 3px 10px rgba(196,146,42,.3)}
.user-tab .avatar{width:24px;height:24px;border-radius:6px;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:900;flex-shrink:0}
.user-tab:not(.active) .avatar{background:var(--brand-soft);color:var(--brand)}
.perm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem}
.perm-card{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-lg);padding:1rem 1.1rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;transition:border-color .15s,box-shadow .15s}
.perm-card:hover{border-color:var(--brand);box-shadow:0 2px 10px rgba(196,146,42,.12)}
.perm-card.locked{opacity:.55;cursor:not-allowed}
.perm-card.granted{border-color:rgba(22,163,74,.4);background:rgba(22,163,74,.04)}
.perm-left{display:flex;align-items:center;gap:.65rem}
.perm-icon{width:36px;height:36px;border-radius:9px;background:var(--brand-soft);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0}
.perm-card.granted .perm-icon{background:rgba(22,163,74,.12);color:var(--success)}
.perm-card.locked .perm-icon{background:rgba(220,38,38,.1);color:var(--danger)}
.perm-name{font-weight:700;font-size:.86rem}
.perm-desc{font-size:.73rem;color:var(--muted);margin-top:.1rem}
/* Toggle switch */
.toggle-wrap{flex-shrink:0}
.toggle{position:relative;display:inline-block;width:44px;height:24px}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:var(--surface2);border-radius:99px;cursor:pointer;transition:.25s;border:1.5px solid var(--border)}
.toggle-slider:before{content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
input:checked+.toggle-slider{background:var(--success);border-color:var(--success)}
input:checked+.toggle-slider:before{transform:translateX(20px)}
input:disabled+.toggle-slider{background:var(--surface2);cursor:not-allowed;opacity:.6}
.perm-actions{display:flex;gap:.5rem;margin-top:1.25rem;align-items:center;flex-wrap:wrap}
.save-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:.85rem 1.1rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.preset-btns{display:flex;gap:.4rem;flex-wrap:wrap}
.preset-btn{padding:.28rem .65rem;border-radius:6px;border:1px solid var(--border);background:var(--surface2);color:var(--text2);font-size:.75rem;font-weight:700;cursor:pointer;transition:all .12s}
.preset-btn:hover{border-color:var(--brand);color:var(--brand)}
.saving-indicator{display:none;align-items:center;gap:.4rem;font-size:.83rem;color:var(--muted)}
.saving-indicator.show{display:flex}
</style>

<div class="perm-header">
  <h2>
    <i class="fa fa-shield-halved text-brand"></i>
    <?= LANG==='ar' ? 'إدارة صلاحيات المستخدمين' : 'User Permissions Manager' ?>
  </h2>
  <a href="users.php?lang=<?= LANG ?>" class="btn btn-secondary btn-sm">
    <i class="fa fa-arrow-<?= LANG==='ar'?'right':'left' ?>"></i>
    <?= LANG==='ar' ? 'العودة للمستخدمين' : 'Back to Users' ?>
  </a>
</div>

<?php if (empty($users)): ?>
<div class="card" style="text-align:center;padding:3rem;color:var(--muted)">
  <i class="fa fa-users" style="font-size:2rem;margin-bottom:.75rem;opacity:.4"></i>
  <p><?= LANG==='ar' ? 'لا يوجد مستخدمون' : 'No users found' ?></p>
</div>
<?php else: ?>

<!-- User tabs -->
<div class="user-tabs" id="userTabs">
  <?php foreach ($users as $i => $u): ?>
  <?php
    $rk  = $u['role'];
    $rlabel = LANG==='ar' ? ($role_labels[$rk]['ar']??$rk) : ($role_labels[$rk]['en']??$rk);
    $fname  = LANG==='ar' ? ($u['full_name_ar']?:$u['username']) : ($u['full_name_en']?:$u['full_name_ar']?:$u['username']);
    $init   = strtoupper(mb_substr($u['full_name_ar']?:$u['username'],0,1));
    $is_owner = $u['role']==='owner';
  ?>
  <button class="user-tab <?= $i===0?'active':'' ?>"
          data-uid="<?= $u['id'] ?>"
          data-role="<?= $u['role'] ?>"
          onclick="selectUser(this)">
    <span class="avatar"><?= $init ?></span>
    <span>
      <?= htmlspecialchars($fname) ?>
      <span class="badge <?= $role_colors[$rk]??'badge-muted' ?>" style="font-size:.65rem;padding:.1rem .4rem;margin-<?= LANG==='ar'?'right':'left' ?>:.2rem"><?= $rlabel ?></span>
    </span>
  </button>
  <?php endforeach; ?>
</div>

<!-- Save bar -->
<div class="save-bar">
  <div>
    <span style="font-size:.85rem;font-weight:700;color:var(--text)">
      <?= LANG==='ar'?'الصلاحيات الحالية لـ':'Permissions for' ?>:
      <span id="currentUserName" style="color:var(--brand)">—</span>
    </span>
    <div class="preset-btns" style="margin-top:.4rem">
      <span style="font-size:.72rem;color:var(--muted);line-height:2"><?= LANG==='ar'?'تطبيق مسبق:':'Preset:' ?></span>
      <button class="preset-btn" onclick="applyPreset('all')"><?= LANG==='ar'?'✓ كل الصفحات':'✓ All Pages' ?></button>
      <button class="preset-btn" onclick="applyPreset('none')"><?= LANG==='ar'?'✗ لا شيء':'✗ None' ?></button>
      <button class="preset-btn" onclick="applyPreset('cashier')"><?= LANG==='ar'?'كاشير فقط':'Cashier Only' ?></button>
      <button class="preset-btn" onclick="applyPreset('manager')"><?= LANG==='ar'?'مدير':'Manager' ?></button>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
    <div class="saving-indicator" id="savingIndicator">
      <i class="fa fa-circle-notch fa-spin"></i> <?= LANG==='ar'?'جاري الحفظ...':'Saving...' ?>
    </div>
    <div id="savedMsg" style="display:none;font-size:.83rem;color:var(--success);font-weight:700">
      <i class="fa fa-check-circle"></i> <?= LANG==='ar'?'تم الحفظ':'Saved' ?>
    </div>
    <button class="btn btn-primary" id="saveBtn" onclick="savePermissions()">
      <i class="fa fa-shield-halved"></i> <?= LANG==='ar'?'حفظ الصلاحيات':'Save Permissions' ?>
    </button>
  </div>
</div>

<!-- Owner notice -->
<div id="ownerNotice" style="display:none" class="card mb-2" style="padding:.75rem 1rem">
  <div style="display:flex;align-items:center;gap:.65rem;color:var(--brand);padding:.5rem 0">
    <i class="fa fa-crown" style="font-size:1.2rem"></i>
    <div>
      <div style="font-weight:700"><?= LANG==='ar'?'حساب المالك':'Owner Account' ?></div>
      <div style="font-size:.8rem;color:var(--muted)"><?= LANG==='ar'?'المالك يملك صلاحية الوصول الكاملة دائماً ولا يمكن تقييده.':'Owner always has full access and cannot be restricted.' ?></div>
    </div>
  </div>
</div>

<!-- Permission cards grid -->
<div class="perm-grid" id="permGrid">
  <?php foreach ($ALL_PAGES as $page_key => $page_info): ?>
  <?php $locked = in_array($page_key, $LOCKED_PAGES); ?>
  <div class="perm-card <?= $locked?'locked':'' ?>" id="pcard-<?= $page_key ?>" data-page="<?= $page_key ?>">
    <div class="perm-left">
      <div class="perm-icon"><i class="fa <?= $page_info['icon'] ?>"></i></div>
      <div>
        <div class="perm-name"><?= LANG==='ar' ? $page_info['ar'] : $page_info['en'] ?></div>
        <div class="perm-desc">
          <?php if ($locked): ?>
            <span style="color:var(--danger)"><i class="fa fa-lock" style="font-size:.65rem"></i> <?= LANG==='ar'?'للمدير والمالك فقط':'Admin & Owner only' ?></span>
          <?php else: ?>
            <?= LANG==='ar' ? $page_info['en'] : $page_info['ar'] ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="toggle-wrap">
      <label class="toggle">
        <input type="checkbox"
               id="perm-<?= $page_key ?>"
               data-page="<?= $page_key ?>"
               <?= $locked?'disabled':'' ?>
               onchange="onPermChange(this)">
        <span class="toggle-slider"></span>
      </label>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
const ALL_PAGES   = <?= json_encode(array_keys($ALL_PAGES)) ?>;
const LOCKED      = <?= json_encode($LOCKED_PAGES) ?>;
const LANG        = '<?= LANG ?>';
const ALL_PERMS   = <?= json_encode($all_perms) ?>;

// Presets
const PRESETS = {
  cashier: ['dashboard','pos'],
  manager: ['dashboard','pos','products','categories','suppliers','stock','sales_history','exchange','reports','finance'],
  all:     ALL_PAGES.filter(p=>!LOCKED.includes(p)),
  none:    []
};

let currentUserId   = null;
let currentUserRole = null;

function selectUser(btn) {
  document.querySelectorAll('.user-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  currentUserId   = btn.dataset.uid;
  currentUserRole = btn.dataset.role;
  const fname = btn.querySelector('span:last-child').childNodes[0].textContent.trim();
  document.getElementById('currentUserName').textContent = fname;

  const isOwner = currentUserRole === 'owner';
  document.getElementById('ownerNotice').style.display = isOwner ? 'block' : 'none';
  document.getElementById('permGrid').style.opacity    = isOwner ? '.55' : '1';
  document.getElementById('saveBtn').disabled          = isOwner;

  // Load permissions for this user
  const perms = ALL_PERMS[currentUserId] || {};
  ALL_PAGES.forEach(page => {
    const cb   = document.getElementById('perm-' + page);
    const card = document.getElementById('pcard-' + page);
    if (!cb) return;

    const isLocked     = LOCKED.includes(page);
    const isAdminRole  = ['admin','owner'].includes(currentUserRole);

    if (isOwner) {
      cb.checked  = true;
      cb.disabled = true;
    } else if (isLocked && !isAdminRole) {
      cb.checked  = false;
      cb.disabled = true;
    } else {
      cb.disabled = false;
      cb.checked  = perms[page] !== undefined ? perms[page] : (isAdminRole);
    }

    updateCard(card, cb.checked, (isLocked && !isAdminRole) || isOwner);
  });

  // Show save hint
  document.getElementById('savedMsg').style.display = 'none';
}

function onPermChange(cb) {
  const card = document.getElementById('pcard-' + cb.dataset.page);
  updateCard(card, cb.checked, false);
}

function updateCard(card, granted, locked) {
  card.classList.toggle('granted', granted);
  card.classList.toggle('locked',  locked);
}

function applyPreset(name) {
  if (!currentUserId) return;
  if (currentUserRole === 'owner') return;
  const grant = PRESETS[name] || [];
  ALL_PAGES.forEach(page => {
    const cb   = document.getElementById('perm-' + page);
    const card = document.getElementById('pcard-' + page);
    if (!cb || cb.disabled) return;
    cb.checked = grant.includes(page);
    updateCard(card, cb.checked, false);
  });
}

function savePermissions() {
  if (!currentUserId) {
    alert(LANG==='ar'?'اختر مستخدماً أولاً':'Please select a user first');
    return;
  }
  if (currentUserRole === 'owner') return;

  const pages = [];
  ALL_PAGES.forEach(page => {
    const cb = document.getElementById('perm-' + page);
    if (cb && cb.checked && !cb.disabled) pages.push(page);
  });

  document.getElementById('savingIndicator').classList.add('show');
  document.getElementById('savedMsg').style.display = 'none';
  document.getElementById('saveBtn').disabled = true;

  const fd = new FormData();
  fd.append('action',  'save_permissions');
  fd.append('user_id', currentUserId);
  pages.forEach(p => fd.append('pages[]', p));

  fetch('permissions.php?lang=<?= LANG ?>', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      document.getElementById('savingIndicator').classList.remove('show');
      document.getElementById('saveBtn').disabled = false;
      if (d.success) {
        // Update local cache
        if (!ALL_PERMS[currentUserId]) ALL_PERMS[currentUserId] = {};
        ALL_PAGES.forEach(p => {
          ALL_PERMS[currentUserId][p] = pages.includes(p);
        });
        document.getElementById('savedMsg').style.display = 'flex';
        setTimeout(() => document.getElementById('savedMsg').style.display = 'none', 3000);
      } else {
        alert(d.error || (LANG==='ar'?'حدث خطأ!':'Error occurred!'));
      }
    })
    .catch(() => {
      document.getElementById('savingIndicator').classList.remove('show');
      document.getElementById('saveBtn').disabled = false;
      alert(LANG==='ar'?'فشل الاتصال بالخادم':'Server connection failed');
    });
}

// Auto-select first user on load
window.addEventListener('DOMContentLoaded', () => {
  const firstTab = document.querySelector('.user-tab');
  if (firstTab) selectUser(firstTab);
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>