<?php
require_once __DIR__ . '/includes/config.php';
$db = DB::get();

// Handle form BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name_ar = trim($_POST['name_ar'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $price   = (float)($_POST['price'] ?? 0);
        $cost    = (float)($_POST['cost'] ?? 0);
        $stock   = (int)($_POST['stock_qty'] ?? 0);
        $thresh  = (int)($_POST['low_stock_threshold'] ?? 10);
        $cat     = (int)($_POST['category_id'] ?? 0) ?: null;
        $sup     = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $unit_ar = trim($_POST['unit_ar'] ?? 'قطعة');
        $unit_en = trim($_POST['unit_en'] ?? 'pcs');

        if (!$name_ar) {
            flash(LANG==='ar'?'الاسم العربي مطلوب':'Arabic name is required','error');
        } else {
            if ($id) {
                $db->prepare("UPDATE products SET name_ar=?,name_en=?,price=?,cost=?,stock_qty=?,low_stock_threshold=?,category_id=?,supplier_id=?,unit_ar=?,unit_en=?,updated_at=NOW() WHERE id=?")
                   ->execute([$name_ar,$name_en,$price,$cost,$stock,$thresh,$cat,$sup,$unit_ar,$unit_en,$id]);
                flash(LANG==='ar'?'تم تحديث المنتج':'Product updated');
            } else {
                $db->prepare("INSERT INTO products (name_ar,name_en,price,cost,stock_qty,low_stock_threshold,category_id,supplier_id,unit_ar,unit_en) VALUES (?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$name_ar,$name_en,$price,$cost,$stock,$thresh,$cat,$sup,$unit_ar,$unit_en]);
                $id = (int)$db->lastInsertId();

                // Generate barcode text only — no PHP image needed
                $db->exec("UPDATE counters SET value=value+1 WHERE name='barcode_seq'");
                $seq = (int)$db->query("SELECT value FROM counters WHERE name='barcode_seq'")->fetchColumn();
                $barcode_text = 'IQ' . str_pad($seq, 6, '0', STR_PAD_LEFT);
                $db->prepare("UPDATE products SET barcode_text=? WHERE id=?")->execute([$barcode_text, $id]);

                flash(LANG==='ar'?'تم إضافة المنتج':'Product added');
            }
        }
        header('Location: http://localhost/bangeen_pos/products.php?lang='.LANG); exit;
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_POST['id']]);
        flash(LANG==='ar'?'تم حذف المنتج':'Product deleted','warning');
        header('Location: http://localhost/bangeen_pos/products.php?lang='.LANG); exit;
    }

    if ($action === 'regen_barcode') {
        $id = (int)$_POST['id'];
        $db->exec("UPDATE counters SET value=value+1 WHERE name='barcode_seq'");
        $seq = (int)$db->query("SELECT value FROM counters WHERE name='barcode_seq'")->fetchColumn();
        $barcode_text = 'IQ' . str_pad($seq, 6, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE products SET barcode_text=?, barcode_image=NULL WHERE id=?")->execute([$barcode_text, $id]);
        flash(LANG==='ar'?'تم توليد الباركود':'Barcode generated');
        header('Location: http://localhost/bangeen_pos/products.php?lang='.LANG); exit;
    }
}

$search  = trim($_GET['search'] ?? '');
$where   = 'WHERE p.is_active=1';
$params  = [];
if ($search) {
    $where  .= " AND (p.name_ar LIKE ? OR p.name_en LIKE ? OR p.barcode_text LIKE ?)";
    $params  = ["%$search%", "%$search%", "%$search%"];
}

$products = $db->prepare("SELECT p.*, c.name_ar as cat_ar, c.name_en as cat_en FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN suppliers s ON p.supplier_id=s.id $where ORDER BY p.id DESC");
$products->execute($params);
$products = $products->fetchAll();

$cats = $db->query("SELECT * FROM categories ORDER BY name_ar")->fetchAll();
$sups = $db->query("SELECT * FROM suppliers ORDER BY name_ar")->fetchAll();

$page_title = t('products');
$active_nav = 'products';
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* Screen preview */
.barcode-sticker {
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 6px 8px;
    width: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}
.sticker-name {
    font-size: 11px;
    font-weight: 700;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    color: #222;
}
.sticker-barcode svg { width: 100%; height: 50px; }
.sticker-number {
    font-family: 'Courier New', monospace;
    font-size: 10px;
    letter-spacing: 1px;
    color: #333;
    text-align: center;
}

/* PRINT STYLES */
@media print {
    * { margin: 0 !important; padding: 0 !important; box-sizing: border-box !important; }

    body * { visibility: hidden !important; }
    #printArea, #printArea * { visibility: visible !important; }

    #printArea {
        position: fixed;
        top: 0; left: 0;
        width: 50mm;
    }

    #printGrid {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 0;
        margin: 0;
    }

    .barcode-sticker {
        width: 50mm !important;
        height: 30mm !important;
        padding: 2mm 2mm 1.5mm 2mm !important;
        border: none !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: space-between !important;
        page-break-after: always;
        page-break-inside: avoid;
        overflow: hidden;
        background: #fff !important;
    }

    .sticker-name {
        font-size: 9pt !important;
        font-weight: 900 !important;
        text-align: center !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        width: 100% !important;
        line-height: 1.2 !important;
        color: #000 !important;
        font-family: Arial Black, Arial, sans-serif !important;
    }

    .sticker-barcode {
        width: 46mm !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 1;
    }

    .sticker-barcode svg {
        width: 46mm !important;
        height: 16mm !important;
    }

    .sticker-number {
        font-family: 'Courier New', monospace !important;
        font-size: 8pt !important;
        font-weight: 700 !important;
        letter-spacing: 2px !important;
        color: #000 !important;
        text-align: center !important;
        line-height: 1 !important;
        width: 100% !important;
    }

    .no-print { display: none !important; }

    @page {
        size: 50mm 30mm;
        margin: 0mm;
    }
}
</style>

<!-- No-print controls -->
<div class="no-print">
<div class="flex-between mb-2">
  <form class="flex gap-1" method="GET" style="flex:1;max-width:360px">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="text" name="search" value="<?= sanitize($search) ?>"
           placeholder="<?= LANG==='ar'?'ابحث باسم المنتج أو الباركود...':'Search product or barcode...' ?>">
    <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
  </form>
  <div class="flex gap-1">
    <button class="btn btn-secondary" onclick="openPrintModal()">
      <i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة ملصقات':'Print Labels' ?>
    </button>
    <button class="btn btn-primary" onclick="openModal('productModal')">
      <i class="fa fa-plus"></i> <?= t('new_product') ?>
    </button>
  </div>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th>
        <th><?= t('product_name') ?></th>
        <th><?= t('barcode') ?></th>
        <th><?= t('price') ?></th>
        <th><?= t('stock') ?></th>
        <th><?= t('category') ?></th>
        <th><?= t('actions') ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($products)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
      <tr>
        <td class="mono text-muted" style="font-size:.78rem"><?= $p['id'] ?></td>
        <td>
          <div class="fw-bold" style="font-size:.9rem"><?= sanitize($p['name_ar']) ?></div>
          <?php if ($p['name_en']): ?>
          <div class="text-muted" style="font-size:.78rem"><?= sanitize($p['name_en']) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($p['barcode_text']): ?>
            <!-- JsBarcode inline SVG -->
            <svg id="bc_<?= $p['id'] ?>" style="height:36px;width:120px"></svg>
            <div class="mono" style="font-size:.62rem;color:var(--muted)"><?= sanitize($p['barcode_text']) ?></div>
          <?php else: ?>
            <span class="badge badge-muted"><?= LANG==='ar'?'لا باركود':'No barcode' ?></span>
          <?php endif; ?>
        </td>
        <td class="fw-bold text-brand"><?= format_currency((float)$p['price']) ?></td>
        <td>
          <span class="badge <?= $p['stock_qty'] <= $p['low_stock_threshold'] ? 'badge-danger' : 'badge-success' ?>">
            <?= $p['stock_qty'] ?>
          </span>
        </td>
        <td class="text-muted"><?= sanitize(LANG==='ar'?($p['cat_ar']??'—'):($p['cat_en']?:($p['cat_ar']??'—'))) ?></td>
        <td>
          <div class="flex gap-1">
            <button class="btn btn-sm btn-secondary" onclick="editProduct(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)">
              <i class="fa fa-pen"></i>
            </button>
            <button class="btn btn-sm btn-secondary" title="<?= t('print_label') ?>"
                    onclick="printSingleLabel('<?= addslashes($p['barcode_text']??'') ?>','<?= addslashes(LANG==='ar'?$p['name_ar']:($p['name_en']?:$p['name_ar'])) ?>')">
              <i class="fa fa-print"></i>
            </button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="regen_barcode">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-warning" title="<?= t('generate_barcode') ?>">
                <i class="fa fa-barcode"></i>
              </button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- /.no-print -->

<!-- Product Modal -->
<div class="modal-overlay no-print" id="productModal">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle"><?= t('new_product') ?></span>
      <button class="modal-close" onclick="closeModal('productModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="field_id" value="0">
      <div class="form-row" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label><?= t('name_ar') ?> *</label><input type="text" name="name_ar" id="field_name_ar" required dir="rtl" placeholder="اسم المنتج بالعربية"></div>
        <div class="form-group"><label><?= t('name_en') ?></label><input type="text" name="name_en" id="field_name_en" dir="ltr" placeholder="Product name in English"></div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
        <div class="form-group"><label><?= t('price') ?> *</label><input type="number" step="0.01" name="price" id="field_price" required placeholder="0.00"></div>
        <div class="form-group"><label><?= t('cost') ?></label><input type="number" step="0.01" name="cost" id="field_cost" placeholder="0.00"></div>
        <div class="form-group"><label><?= t('stock') ?></label><input type="number" name="stock_qty" id="field_stock" value="0" min="0"></div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label><?= t('category') ?></label>
          <select name="category_id" id="field_cat">
            <option value=""><?= LANG==='ar'?'— اختر فئة —':'— Select category —' ?></option>
            <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize(LANG==='ar'?$c['name_ar']:($c['name_en']?:$c['name_ar'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label><?= t('supplier') ?></label>
          <select name="supplier_id" id="field_sup">
            <option value=""><?= LANG==='ar'?'— اختر مورد —':'— Select supplier —' ?></option>
            <?php foreach ($sups as $s): ?>
            <option value="<?= $s['id'] ?>"><?= sanitize(LANG==='ar'?$s['name_ar']:($s['name_en']?:$s['name_ar'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
        <div class="form-group"><label><?= t('unit') ?> (AR)</label><input type="text" name="unit_ar" id="field_unit_ar" value="قطعة" dir="rtl"></div>
        <div class="form-group"><label><?= t('unit') ?> (EN)</label><input type="text" name="unit_en" id="field_unit_en" value="pcs" dir="ltr"></div>
        <div class="form-group"><label><?= t('threshold') ?></label><input type="number" name="low_stock_threshold" id="field_thresh" value="10" min="0"></div>
      </div>
      <div class="flex gap-1" style="justify-content:flex-end;margin-top:.5rem">
        <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')"><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Print Labels Modal -->
<div class="modal-overlay no-print" id="printModal">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <span class="modal-title"><i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة ملصقات الباركود':'Print Barcode Labels' ?></span>
      <button class="modal-close" onclick="closeModal('printModal')">✕</button>
    </div>

    <!-- Settings -->
    <div class="form-row mb-1" style="grid-template-columns:1fr 1fr 1fr">
      <div class="form-group">
        <label><?= LANG==='ar'?'عدد النسخ':'Copies per product' ?></label>
        <input type="number" id="copiesPerProduct" value="1" min="1" max="50" style="text-align:center">
      </div>
      <div class="form-group" style="display:none">
        <input type="hidden" id="showPrice" value="no">
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'فلترة الفئة':'Filter category' ?></label>
        <select id="catFilter" onchange="filterModalProducts()">
          <option value="all"><?= LANG==='ar'?'الكل':'All' ?></option>
          <?php foreach ($cats as $c): ?>
          <option value="<?= $c['id'] ?>"><?= sanitize(LANG==='ar'?$c['name_ar']:($c['name_en']?:$c['name_ar'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Search -->
    <div class="form-group mb-1">
      <input type="text" id="modalSearch" placeholder="<?= LANG==='ar'?'بحث عن منتج...':'Search product...' ?>" oninput="filterModalProducts()">
    </div>

    <!-- Select all -->
    <div class="flex gap-1 mb-1">
      <button class="btn btn-sm btn-secondary" onclick="selectAllModal(true)">
        <i class="fa fa-check-double"></i> <?= LANG==='ar'?'تحديد الكل':'Select All' ?>
      </button>
      <button class="btn btn-sm btn-danger" onclick="selectAllModal(false)">
        <i class="fa fa-xmark"></i> <?= LANG==='ar'?'إلغاء الكل':'Deselect All' ?>
      </button>
      <span class="text-muted" style="font-size:.82rem;margin-<?= ALIGN_START ?>:auto;align-self:center">
        <span id="selectedCountModal">0</span> <?= LANG==='ar'?'محدد':'selected' ?>
      </span>
    </div>

    <!-- Products list -->
    <div style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:.5rem" id="modalProductsList">
      <?php foreach ($products as $p): ?>
      <?php if ($p['barcode_text']): ?>
      <label class="flex-center gap-1 modal-product-item" data-cat="<?= $p['category_id'] ?>"
             style="padding:.4rem .5rem;border-radius:6px;cursor:pointer;transition:background .12s"
             onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
        <input type="checkbox" class="modal-cb" style="width:16px;height:16px;accent-color:var(--brand);cursor:pointer"
               data-barcode="<?= sanitize($p['barcode_text']) ?>"
               data-name="<?= sanitize(LANG==='ar'?$p['name_ar']:($p['name_en']?:$p['name_ar'])) ?>"
               data-price="<?= $p['price'] ?>"
               onchange="updateModalCount()">
        <div style="flex:1;min-width:0">
          <div class="fw-bold" style="font-size:.85rem"><?= sanitize($p['name_ar']) ?><?= $p['name_en']?' / '.sanitize($p['name_en']):'' ?></div>
          <div class="mono text-muted" style="font-size:.72rem"><?= sanitize($p['barcode_text']) ?> — <?= format_currency((float)$p['price']) ?></div>
        </div>
      </label>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="flex gap-1 mt-2" style="justify-content:flex-end">
      <button class="btn btn-secondary" onclick="closeModal('printModal')"><?= t('cancel') ?></button>
      <button class="btn btn-primary" onclick="doPrint()">
        <i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة':'Print' ?>
      </button>
    </div>
  </div>
</div>

<!-- Hidden Print Area -->
<div id="printArea" style="display:none">
  <div id="printGrid"></div>
</div>

<!-- JsBarcode from CDN -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script>
// ── Render barcodes in table ──────────────────────────────
<?php foreach ($products as $p): ?>
<?php if ($p['barcode_text']): ?>
try {
  JsBarcode('#bc_<?= $p['id'] ?>', '<?= addslashes($p['barcode_text']) ?>', {
    format: 'CODE128',
    width: 2,
    height: 36,
    displayValue: false,
    margin: 2,
    background: 'transparent'
  });
} catch(e) {}
<?php endif; ?>
<?php endforeach; ?>

// ── Modal helpers ─────────────────────────────────────────
function openModal(id){ document.getElementById(id).classList.add('open') }
function closeModal(id){ document.getElementById(id).classList.remove('open') }

function editProduct(p){
  document.getElementById('modalTitle').textContent = '<?= t("edit_product") ?>';
  document.getElementById('field_id').value        = p.id;
  document.getElementById('field_name_ar').value   = p.name_ar || '';
  document.getElementById('field_name_en').value   = p.name_en || '';
  document.getElementById('field_price').value     = p.price   || '';
  document.getElementById('field_cost').value      = p.cost    || '';
  document.getElementById('field_stock').value     = p.stock_qty || 0;
  document.getElementById('field_cat').value       = p.category_id || '';
  document.getElementById('field_sup').value       = p.supplier_id || '';
  document.getElementById('field_unit_ar').value   = p.unit_ar || 'قطعة';
  document.getElementById('field_unit_en').value   = p.unit_en || 'pcs';
  document.getElementById('field_thresh').value    = p.low_stock_threshold || 10;
  openModal('productModal');
}

// ── Print labels modal ────────────────────────────────────
function openPrintModal(){
  updateModalCount();
  openModal('printModal');
}

function updateModalCount(){
  const n = document.querySelectorAll('.modal-cb:checked').length;
  document.getElementById('selectedCountModal').textContent = n;
}

function selectAllModal(state){
  document.querySelectorAll('.modal-product-item').forEach(item => {
    if (item.style.display !== 'none') {
      item.querySelector('.modal-cb').checked = state;
    }
  });
  updateModalCount();
}

function filterModalProducts(){
  const cat    = document.getElementById('catFilter').value;
  const search = document.getElementById('modalSearch').value.toLowerCase();
  document.querySelectorAll('.modal-product-item').forEach(item => {
    const matchCat  = cat === 'all' || item.dataset.cat === cat;
    const matchText = item.textContent.toLowerCase().includes(search);
    item.style.display = (matchCat && matchText) ? '' : 'none';
  });
}

// ── Print single label from table ────────────────────────
function printSingleLabel(barcode, name){
  if (!barcode) { alert('<?= LANG==="ar"?"لا يوجد باركود":"No barcode" ?>'); return; }
  executePrint([{ barcode, name, price: '' }], 1, false);
}

// ── Execute print ─────────────────────────────────────────
function doPrint(){
  const selected = [];
  document.querySelectorAll('.modal-cb:checked').forEach(cb => {
    selected.push({ barcode: cb.dataset.barcode, name: cb.dataset.name, price: cb.dataset.price });
  });
  if (!selected.length) {
    alert('<?= LANG==="ar"?"اختر منتجاً على الأقل":"Please select at least one product" ?>');
    return;
  }
  const copies    = parseInt(document.getElementById('copiesPerProduct').value) || 1;
  const showPrice = document.getElementById('showPrice').value === 'yes';
  closeModal('printModal');
  setTimeout(() => executePrint(selected, copies, showPrice), 100);
}

function executePrint(products, copies, showPrice){
  const grid = document.getElementById('printGrid');
  let html = '';

  products.forEach(p => {
    for (let i = 0; i < copies; i++) {
      html += `
        <div class="barcode-sticker">
          <div class="sticker-name">${p.name}</div>
          <div class="sticker-barcode">
            <svg class="print-bc" data-val="${p.barcode}"></svg>
          </div>
          <div class="sticker-number">${p.barcode}</div>
        </div>`;
    }
  });

  grid.innerHTML = html;

  // Generate barcodes for print — fitted for 50x30mm label
  document.querySelectorAll('.print-bc').forEach(svg => {
    try {
      JsBarcode(svg, svg.dataset.val, {
        format:       'CODE128',
        width:        1.5,
        height:       38,
        displayValue: false,
        margin:       0,
        background:   '#ffffff',
        lineColor:    '#000000'
      });
    } catch(e) { console.error(e); }
  });

  // Show print area and print
  const area = document.getElementById('printArea');
  area.style.display = 'block';
  setTimeout(() => {
    window.print();
    setTimeout(() => { area.style.display = 'none'; }, 500);
  }, 300);
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>