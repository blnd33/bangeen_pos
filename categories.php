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
        $color   = trim($_POST['color']??'#C4922A');
        if ($id) {
            $db->prepare("UPDATE categories SET name_ar=?,name_en=?,color=? WHERE id=?")->execute([$name_ar,$name_en,$color,$id]);
        } else {
            $db->prepare("INSERT INTO categories (name_ar,name_en,color) VALUES (?,?,?)")->execute([$name_ar,$name_en,$color]);
        }
        flash(LANG==='ar'?'تم الحفظ':'Saved');
        header('Location: http://localhost/bangeen_pos/categories.php?lang='.LANG); exit;
    }
    if ($action==='delete') {
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_POST['id']]);
        flash(LANG==='ar'?'تم الحذف':'Deleted','warning');
        header('Location: http://localhost/bangeen_pos/categories.php?lang='.LANG); exit;
    }
}

$cats = $db->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name_ar")->fetchAll();

$page_title = t('categories');
$active_nav = 'categories';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="flex-between mb-2">
  <h2 style="font-size:1rem;font-weight:700"><?= t('categories') ?></h2>
  <button class="btn btn-primary" onclick="openModal('catModal');resetForm()">
    <i class="fa fa-plus"></i> <?= LANG==='ar'?'فئة جديدة':'New Category' ?>
  </button>
</div>

<div class="grid-3 gap-2">
  <?php foreach ($cats as $c): ?>
  <div class="card" style="border-<?= DIR==='rtl'?'right':'left' ?>:4px solid <?= htmlspecialchars($c['color']) ?>">
    <div class="flex-between">
      <div>
        <div class="fw-bold"><?= sanitize($c['name_ar']) ?></div>
        <div class="text-muted" style="font-size:.82rem"><?= sanitize($c['name_en']??'') ?></div>
      </div>
      <span class="badge badge-brand"><?= $c['product_count'] ?> <?= LANG==='ar'?'منتج':'products' ?></span>
    </div>
    <div class="flex gap-1 mt-1">
      <button class="btn btn-sm btn-secondary" onclick="editCat(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)">
        <i class="fa fa-pen"></i>
      </button>
      <form method="POST" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($cats)): ?>
  <div class="card text-muted text-center" style="grid-column:1/-1;padding:2rem"><?= t('no_results') ?></div>
  <?php endif; ?>
</div>

<div class="modal-overlay" id="catModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title" id="catModalTitle"><?= LANG==='ar'?'فئة جديدة':'New Category' ?></span>
      <button class="modal-close" onclick="closeModal('catModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="c_id" value="0">
      <div class="form-group mb-1"><label><?= t('name_ar') ?> *</label><input type="text" name="name_ar" id="c_name_ar" required dir="rtl"></div>
      <div class="form-group mb-1"><label><?= t('name_en') ?></label><input type="text" name="name_en" id="c_name_en" dir="ltr"></div>
      <div class="form-group mb-1"><label><?= LANG==='ar'?'اللون':'Color' ?></label>
        <input type="color" name="color" id="c_color" value="#C4922A" style="height:42px;cursor:pointer;width:100%">
      </div>
      <div class="flex gap-1" style="justify-content:flex-end;margin-top:.75rem">
        <button type="button" class="btn btn-secondary" onclick="closeModal('catModal')"><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function resetForm(){
  document.getElementById('c_id').value=0;
  document.getElementById('c_name_ar').value='';
  document.getElementById('c_name_en').value='';
  document.getElementById('c_color').value='#C4922A';
  document.getElementById('catModalTitle').textContent='<?= LANG==="ar"?"فئة جديدة":"New Category" ?>';
}
function editCat(c){
  document.getElementById('catModalTitle').textContent='<?= LANG==="ar"?"تعديل فئة":"Edit Category" ?>';
  document.getElementById('c_id').value=c.id;
  document.getElementById('c_name_ar').value=c.name_ar||'';
  document.getElementById('c_name_en').value=c.name_en||'';
  document.getElementById('c_color').value=c.color||'#C4922A';
  openModal('catModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>