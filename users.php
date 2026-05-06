<?php
require_once __DIR__ . '/includes/config.php';
require_role('admin','manager');
$db = DB::get();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='save') {
        $id        = (int)($_POST['id']??0);
        $username  = trim($_POST['username']??'');
        $full_ar   = trim($_POST['full_name_ar']??'');
        $full_en   = trim($_POST['full_name_en']??'');
        $role      = $_POST['role']??'cashier';
        $password  = $_POST['password']??'';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$username) { flash(LANG==='ar'?'اسم المستخدم مطلوب':'Username required','error'); header('Location: users.php?lang='.LANG); exit; }

        if ($id) {
            if ($password) {
                $db->prepare("UPDATE users SET username=?,full_name_ar=?,full_name_en=?,role=?,password_hash=?,is_active=? WHERE id=?")
                   ->execute([$username,$full_ar,$full_en,$role,password_hash($password,PASSWORD_DEFAULT),$is_active,$id]);
            } else {
                $db->prepare("UPDATE users SET username=?,full_name_ar=?,full_name_en=?,role=?,is_active=? WHERE id=?")
                   ->execute([$username,$full_ar,$full_en,$role,$is_active,$id]);
            }
            flash(LANG==='ar'?'تم تحديث المستخدم':'User updated');
        } else {
            if (!$password) { flash(LANG==='ar'?'كلمة المرور مطلوبة للمستخدم الجديد':'Password required for new user','error'); header('Location: users.php?lang='.LANG); exit; }
            $db->prepare("INSERT INTO users (username,full_name_ar,full_name_en,role,password_hash,is_active) VALUES (?,?,?,?,?,?)")
               ->execute([$username,$full_ar,$full_en,$role,password_hash($password,PASSWORD_DEFAULT),$is_active]);
            flash(LANG==='ar'?'تم إضافة المستخدم':'User added');
        }
        header('Location: users.php?lang='.LANG); exit;
    }
    if ($action==='delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)(current_user()['id'])) { flash(LANG==='ar'?'لا يمكنك حذف حسابك الخاص':'Cannot delete your own account','error'); }
        else { $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]); flash(LANG==='ar'?'تم حذف المستخدم':'User deleted','warning'); }
        header('Location: users.php?lang='.LANG); exit;
    }
    if ($action==='toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        flash(LANG==='ar'?'تم تغيير الحالة':'Status toggled');
        header('Location: users.php?lang='.LANG); exit;
    }
}

$users = $db->query("SELECT * FROM users ORDER BY role, full_name_ar")->fetchAll();
$me = current_user();

$page_title = t('users');
$active_nav = 'users';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="flex-between mb-2">
  <h2 style="font-size:1rem;font-weight:700"><?= t('users') ?></h2>
  <button class="btn btn-primary" onclick="resetForm();openModal('userModal')">
    <i class="fa fa-user-plus"></i> <?= LANG==='ar'?'مستخدم جديد':'New User' ?>
  </button>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th>
        <th><?= LANG==='ar'?'الاسم الكامل':'Full Name' ?></th>
        <th><?= t('username') ?></th>
        <th><?= t('role') ?></th>
        <th><?= t('status') ?></th>
        <th><?= t('actions') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <?php $role_colors = ['admin'=>'badge-danger','manager'=>'badge-warning','cashier'=>'badge-info']; ?>
      <?php $role_labels_ar = ['admin'=>'مدير النظام','manager'=>'مدير','cashier'=>'كاشير']; ?>
      <?php $role_labels_en = ['admin'=>'Admin','manager'=>'Manager','cashier'=>'Cashier']; ?>
      <tr>
        <td class="mono text-muted" style="font-size:.78rem"><?= $u['id'] ?></td>
        <td>
          <div class="flex-center gap-1">
            <div style="width:32px;height:32px;background:var(--brand-soft);color:var(--brand);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;flex-shrink:0">
              <?= strtoupper(mb_substr($u['full_name_ar']?:$u['username'],0,1)) ?>
            </div>
            <div>
              <div class="fw-bold" style="font-size:.88rem"><?= sanitize($u['full_name_ar']??'') ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= sanitize($u['full_name_en']??'') ?></div>
            </div>
          </div>
        </td>
        <td class="mono" style="color:var(--brand)"><?= sanitize($u['username']) ?></td>
        <td>
          <span class="badge <?= $role_colors[$u['role']]??'badge-muted' ?>">
            <?= LANG==='ar' ? ($role_labels_ar[$u['role']]??$u['role']) : ($role_labels_en[$u['role']]??$u['role']) ?>
          </span>
        </td>
        <td>
          <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-muted' ?>">
            <?= $u['is_active'] ? t('active') : t('inactive') ?>
          </span>
        </td>
        <td>
          <div class="flex gap-1">
            <button class="btn btn-sm btn-secondary" onclick="editUser(<?= htmlspecialchars(json_encode($u),ENT_QUOTES) ?>)">
              <i class="fa fa-pen"></i>
            </button>
            <?php if ($u['id'] != $me['id']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-warning" title="<?= LANG==='ar'?'تفعيل/تعطيل':'Toggle status' ?>">
                <i class="fa fa-power-off"></i>
              </button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
            </form>
            <?php else: ?>
            <span class="badge badge-muted"><?= LANG==='ar'?'أنت':'You' ?></span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- User Modal -->
<div class="modal-overlay" id="userModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title" id="userModalTitle"><?= LANG==='ar'?'مستخدم جديد':'New User' ?></span>
      <button class="modal-close" onclick="closeModal('userModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="u_id" value="0">

      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group">
          <label><?= LANG==='ar'?'الاسم بالعربية':'Name (Arabic)' ?> *</label>
          <input type="text" name="full_name_ar" id="u_name_ar" dir="rtl" placeholder="الاسم الكامل">
        </div>
        <div class="form-group">
          <label><?= LANG==='ar'?'الاسم بالإنجليزية':'Name (English)' ?></label>
          <input type="text" name="full_name_en" id="u_name_en" dir="ltr" placeholder="Full name">
        </div>
      </div>

      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group">
          <label><?= t('username') ?> *</label>
          <input type="text" name="username" id="u_username" dir="ltr" required placeholder="username">
        </div>
        <div class="form-group">
          <label><?= t('role') ?></label>
          <select name="role" id="u_role">
            <option value="cashier"><?= LANG==='ar'?'كاشير':'Cashier' ?></option>
            <option value="manager"><?= LANG==='ar'?'مدير':'Manager' ?></option>
            <?php if ($me['role']==='admin'): ?>
            <option value="admin"><?= LANG==='ar'?'مدير النظام':'Admin' ?></option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group">
          <label><?= t('password') ?> <span id="passHint" class="text-muted" style="font-size:.7rem">(<?= LANG==='ar'?'اتركه فارغاً للإبقاء':'leave blank to keep' ?>)</span></label>
          <input type="password" name="password" id="u_password" dir="ltr" placeholder="••••••••">
        </div>
        <div class="form-group" style="justify-content:center">
          <label>&nbsp;</label>
          <label class="flex-center gap-1" style="text-transform:none;font-size:.88rem;cursor:pointer">
            <input type="checkbox" name="is_active" id="u_active" checked style="width:auto">
            <?= t('active') ?>
          </label>
        </div>
      </div>

      <div class="flex gap-1" style="justify-content:flex-end;margin-top:.75rem">
        <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')"><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function resetForm(){
  document.getElementById('u_id').value=0;
  document.getElementById('u_name_ar').value='';
  document.getElementById('u_name_en').value='';
  document.getElementById('u_username').value='';
  document.getElementById('u_role').value='cashier';
  document.getElementById('u_password').value='';
  document.getElementById('u_active').checked=true;
  document.getElementById('userModalTitle').textContent='<?= LANG==="ar"?"مستخدم جديد":"New User" ?>';
  document.getElementById('passHint').style.display='none';
}
function editUser(u){
  document.getElementById('userModalTitle').textContent='<?= LANG==="ar"?"تعديل مستخدم":"Edit User" ?>';
  document.getElementById('u_id').value=u.id;
  document.getElementById('u_name_ar').value=u.full_name_ar||'';
  document.getElementById('u_name_en').value=u.full_name_en||'';
  document.getElementById('u_username').value=u.username||'';
  document.getElementById('u_role').value=u.role||'cashier';
  document.getElementById('u_password').value='';
  document.getElementById('u_active').checked=u.is_active==1;
  document.getElementById('passHint').style.display='';
  openModal('userModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>