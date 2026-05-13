<?php
require_once __DIR__ . '/includes/config.php';
$db = DB::get();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='stock_adjust') {
        $pid  = (int)$_POST['product_id'];
        $type = $_POST['type']; // 'in' or 'out' or 'adjustment'
        $qty  = (int)$_POST['quantity'];
        $note_ar = trim($_POST['note_ar']??'');
        $note_en = trim($_POST['note_en']??'');
        $user = current_user();
        if ($type==='out') $qty = -abs($qty);
        $db->prepare("UPDATE products SET stock_qty=stock_qty+? WHERE id=?")->execute([$qty,$pid]);
        $log_type = $_POST['type'];
        $db->prepare("INSERT INTO stock_log (product_id,user_id,type,quantity,note_ar,note_en) VALUES (?,?,?,?,?,?)")->execute([$pid,$user['id'],$log_type,abs($qty),$note_ar,$note_en]);
        flash(LANG==='ar'?'تم تعديل المخزون':'Stock updated');
        header('Location: stock.php?lang='.LANG); exit;
    }
}

$page_title = t('stock');
$active_nav = 'stock';
require_once __DIR__ . '/includes/layout.php';

$filter = $_GET['filter'] ?? 'all'; // all, low, out
$where = 'WHERE p.is_active=1';
if ($filter==='low')  $where .= ' AND p.stock_qty <= p.low_stock_threshold AND p.stock_qty > 0';
if ($filter==='out')  $where .= ' AND p.stock_qty <= 0';

$products = $db->query("SELECT p.*, c.name_ar as cat_ar, c.name_en as cat_en FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.stock_qty ASC")->fetchAll();

// Recent log
$log = $db->query("SELECT sl.*, p.name_ar, p.name_en, u.full_name_".LANG." as uname FROM stock_log sl JOIN products p ON sl.product_id=p.id LEFT JOIN users u ON sl.user_id=u.id ORDER BY sl.created_at DESC LIMIT 20")->fetchAll();
?>

<div class="flex-between mb-2">
  <div class="flex gap-1">
    <a href="?filter=all&lang=<?= LANG ?>" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-secondary' ?>"><?= LANG==='ar'?'الكل':'All' ?></a>
    <a href="?filter=low&lang=<?= LANG ?>" class="btn btn-sm <?= $filter==='low'?'btn-primary':'btn-secondary' ?>">⚠️ <?= t('low_stock') ?></a>
    <a href="?filter=out&lang=<?= LANG ?>" class="btn btn-sm <?= $filter==='out'?'btn-danger':'btn-secondary' ?>">❌ <?= LANG==='ar'?'نفذ المخزون':'Out of Stock' ?></a>
  </div>
  <button class="btn btn-primary" onclick="openModal('adjustModal')">
    <i class="fa fa-arrows-up-down"></i> <?= LANG==='ar'?'تعديل المخزون':'Adjust Stock' ?>
  </button>
</div>

<div class="grid-2 gap-2">
  <!-- Stock table -->
  <div class="card" style="padding:0;grid-column:1/-1">
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th><?= t('product_name') ?></th>
          <th><?= t('category') ?></th>
          <th><?= t('stock_level') ?></th>
          <th><?= t('threshold') ?></th>
          <th><?= t('status') ?></th>
          <th><?= t('actions') ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p): ?>
        <?php
          $pct = $p['low_stock_threshold']>0 ? ($p['stock_qty']/$p['low_stock_threshold'])*100 : 100;
          $status_class = $p['stock_qty']<=0 ? 'badge-danger' : ($p['stock_qty']<=$p['low_stock_threshold'] ? 'badge-warning' : 'badge-success');
          $status_label = $p['stock_qty']<=0 ? (LANG==='ar'?'نفذ':'Out') : ($p['stock_qty']<=$p['low_stock_threshold'] ? (LANG==='ar'?'منخفض':'Low') : (LANG==='ar'?'متاح':'OK'));
        ?>
        <tr>
          <td>
            <div class="fw-bold" style="font-size:.88rem"><?= sanitize($p['name_ar']) ?></div>
            <?php if ($p['name_en']): ?><div class="text-muted" style="font-size:.75rem"><?= sanitize($p['name_en']) ?></div><?php endif; ?>
          </td>
          <td class="text-muted"><?= sanitize(LANG==='ar'?($p['cat_ar']??'—'):($p['cat_en']?:($p['cat_ar']??'—'))) ?></td>
          <td>
            <div class="flex-center gap-1">
              <span class="fw-bold mono" style="font-size:1rem"><?= $p['stock_qty'] ?></span>
              <div style="width:80px;height:6px;background:var(--border);border-radius:99px;overflow:hidden">
                <div style="width:<?= min(100,max(0,$pct)) ?>%;height:100%;background:<?= $p['stock_qty']<=0?'var(--danger)':($p['stock_qty']<=$p['low_stock_threshold']?'var(--warning)':'var(--success)') ?>;border-radius:99px"></div>
              </div>
            </div>
          </td>
          <td class="mono text-muted"><?= $p['low_stock_threshold'] ?></td>
          <td><span class="badge <?= $status_class ?>"><?= $status_label ?></span></td>
          <td>
            <button class="btn btn-sm btn-secondary" onclick="quickAdjust(<?= $p['id'] ?>, '<?= addslashes(LANG==="ar"?$p["name_ar"]:($p["name_en"]?:$p["name_ar"])) ?>')">
              <i class="fa fa-pen-to-square"></i> <?= LANG==='ar'?'تعديل':'Adjust' ?>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mt-2">
  <div class="card-title"><i class="fa fa-clock-rotate-left text-brand"></i> <?= LANG==='ar'?'سجل حركة المخزون':'Stock Movement Log' ?></div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th><?= t('date') ?></th>
        <th><?= t('product_name') ?></th>
        <th><?= LANG==='ar'?'النوع':'Type' ?></th>
        <th><?= t('quantity') ?></th>
        <th><?= t('notes') ?></th>
        <th><?= t('cashier') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($log as $l): ?>
      <tr>
        <td class="mono text-muted" style="font-size:.78rem"><?= date('d/m/y H:i',strtotime($l['created_at'])) ?></td>
        <td><?= sanitize(LANG==='ar'?$l['name_ar']:($l['name_en']?:$l['name_ar'])) ?></td>
        <td>
          <?php $tc = ['in'=>'badge-success','out'=>'badge-danger','sale'=>'badge-warning','return'=>'badge-info','adjustment'=>'badge-muted']; ?>
          <?php $tl_ar = ['in'=>'إدخال','out'=>'إخراج','sale'=>'مبيعة','return'=>'إرجاع','adjustment'=>'تعديل']; ?>
          <?php $tl_en = ['in'=>'In','out'=>'Out','sale'=>'Sale','return'=>'Return','adjustment'=>'Adj']; ?>
          <span class="badge <?= $tc[$l['type']]??'badge-muted' ?>"><?= LANG==='ar'?($tl_ar[$l['type']]??$l['type']):($tl_en[$l['type']]??$l['type']) ?></span>
        </td>
        <td class="mono fw-bold"><?= $l['quantity'] ?></td>
        <td class="text-muted" style="font-size:.82rem"><?= sanitize(LANG==='ar'?$l['note_ar']:$l['note_en']) ?></td>
        <td class="text-muted"><?= sanitize($l['uname']??'—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal-overlay" id="adjustModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <span class="modal-title"><?= LANG==='ar'?'تعديل المخزون':'Stock Adjustment' ?></span>
      <button class="modal-close" onclick="closeModal('adjustModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="stock_adjust">
      <div class="form-group mb-1">
        <label><?= t('product_name') ?></label>
        <select name="product_id" id="adj_product" required>
          <option value=""><?= LANG==='ar'?'— اختر منتجاً —':'— Select product —' ?></option>
          <?php foreach (DB::get()->query("SELECT id,name_ar,name_en FROM products WHERE is_active=1 ORDER BY name_ar")->fetchAll() as $pr): ?>
          <option value="<?= $pr['id'] ?>"><?= sanitize(LANG==='ar'?$pr['name_ar']:($pr['name_en']?:$pr['name_ar'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group">
          <label><?= LANG==='ar'?'نوع العملية':'Operation' ?></label>
          <select name="type">
            <option value="in"><?= t('stock_in') ?></option>
            <option value="out"><?= t('stock_out') ?></option>
            <option value="adjustment"><?= LANG==='ar'?'تعديل':'Adjustment' ?></option>
          </select>
        </div>
        <div class="form-group">
          <label><?= t('quantity') ?></label>
          <input type="number" name="quantity" min="1" value="1" required>
        </div>
      </div>
      <div class="form-row mb-1" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label><?= t('notes') ?> (AR)</label><input type="text" name="note_ar" dir="rtl" placeholder="ملاحظة"></div>
        <div class="form-group"><label><?= t('notes') ?> (EN)</label><input type="text" name="note_en" dir="ltr" placeholder="Note"></div>
      </div>
      <div class="flex gap-1" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')"><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function quickAdjust(pid, name) {
  document.getElementById('adj_product').value = pid;
  openModal('adjustModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>