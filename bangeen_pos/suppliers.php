<?php
require_once __DIR__ . '/includes/config.php';
require_role('admin','manager');
$db = DB::get();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='save') {
        $id      = (int)($_POST['id']??0);
        $name_ar = trim($_POST['name_ar']??'');
        $name_en = trim($_POST['name_en']??'');
        $phone   = trim($_POST['phone']??'');
        $email   = trim($_POST['email']??'');
        $address = trim($_POST['address']??'');
        if ($id) {
            $db->prepare("UPDATE suppliers SET name_ar=?,name_en=?,phone=?,email=?,address=? WHERE id=?")->execute([$name_ar,$name_en,$phone,$email,$address,$id]);
        } else {
            $db->prepare("INSERT INTO suppliers (name_ar,name_en,phone,email,address) VALUES (?,?,?,?,?)")->execute([$name_ar,$name_en,$phone,$email,$address]);
        }
        flash(LANG==='ar'?'تم الحفظ':'Saved');
        header('Location: suppliers.php?lang='.LANG); exit;
    }
    if ($action==='delete') {
        $db->prepare("DELETE FROM suppliers WHERE id=?")->execute([(int)$_POST['id']]);
        flash(LANG==='ar'?'تم الحذف':'Deleted','warning');
        header('Location: suppliers.php?lang='.LANG); exit;
    }
}

$suppliers = $db->query("SELECT s.*, COUNT(p.id) as product_count FROM suppliers s LEFT JOIN products p ON p.supplier_id=s.id GROUP BY s.id ORDER BY s.name_ar")->fetchAll();

$page_title = t('suppliers');
$active_nav = 'suppliers';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="flex-between mb-2">
  <h2 style="font-size:1rem;font-weight:700"><?= t('suppliers') ?></h2>
  <button class="btn btn-primary" onclick="resetForm();openModal('supModal')"><i class="fa fa-plus"></i> <?= LANG==='ar'?'مورد جديد':'New Supplier' ?></button>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th><?= t('name_ar') ?></th>
        <th><?= t('name_en') ?></th>
        <th><?= LANG==='ar'?'الهاتف':'Phone' ?></th>
        <th><?= LANG==='ar'?'البريد':'Email' ?></th>
        <th><?= LANG==='ar'?'المنتجات':'Products' ?></th>
        <th><?= t('actions') ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($suppliers)): ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
      <?php endif; ?>
      <?php foreach ($suppliers as $s): ?>
      <tr>
        <td class="fw-bold"><?= sanitize($s['name_ar']) ?></td>
        <td class="text-muted"><?= sanitize($s['name_en']??'—') ?></td>
        <td class="mono"><?= sanitize($s['phone']??'—') ?></td>
        <td class="text-muted"><?= sanitize($s['email']??'—') ?></td>
        <td><span class="badge badge-brand"><?= $s['product_count'] ?></span></td>
        <td>
          <div class="flex gap-1">
            <button class="btn btn-sm btn-secondary" onclick="editSup(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)"><i class="fa fa-pen"></i></button>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="supModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title" id="supModalTitle"><?= LANG==='ar'?'مورد جديد':'New Supplier' ?></span>
      <button class="modal-close" onclick="closeModal('supModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="s_id" value="0">
      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label><?= t('name_ar') ?> *</label><input type="text" name="name_ar" id="s_name_ar" required dir="rtl"></div>
        <div class="form-group"><label><?= t('name_en') ?></label><input type="text" name="name_en" id="s_name_en" dir="ltr"></div>
      </div>
      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label><?= LANG==='ar'?'الهاتف':'Phone' ?></label><input type="text" name="phone" id="s_phone" dir="ltr"></div>
        <div class="form-group"><label><?= LANG==='ar'?'البريد':'Email' ?></label><input type="email" name="email" id="s_email" dir="ltr"></div>
      </div>
      <div class="form-group mb-1"><label><?= LANG==='ar'?'العنوان':'Address' ?></label><textarea name="address" id="s_address" rows="2"></textarea></div>
      <div class="flex gap-1" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('supModal')"><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function resetForm(){
  document.getElementById('s_id').value=0;
  document.getElementById('s_name_ar').value='';
  document.getElementById('s_name_en').value='';
  document.getElementById('s_phone').value='';
  document.getElementById('s_email').value='';
  document.getElementById('s_address').value='';
  document.getElementById('supModalTitle').textContent='<?= LANG==="ar"?"مورد جديد":"New Supplier" ?>';
}
function editSup(s){
  document.getElementById('supModalTitle').textContent='<?= LANG==="ar"?"تعديل مورد":"Edit Supplier" ?>';
  document.getElementById('s_id').value=s.id;
  document.getElementById('s_name_ar').value=s.name_ar||'';
  document.getElementById('s_name_en').value=s.name_en||'';
  document.getElementById('s_phone').value=s.phone||'';
  document.getElementById('s_email').value=s.email||'';
  document.getElementById('s_address').value=s.address||'';
  openModal('supModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>