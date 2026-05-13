<?php
require_once __DIR__ . '/includes/config.php';
$page_title = t('sales_history');
$active_nav = 'sales_history';
require_once __DIR__ . '/includes/layout.php';
$db = DB::get();

$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to']   ?? date('Y-m-d');
$method = $_GET['method'] ?? '';
$search = trim($_GET['search'] ?? '');

$where  = 'WHERE DATE(s.created_at) BETWEEN ? AND ?';
$params = [$from, $to];
if ($method) { $where .= ' AND s.payment_method=?'; $params[] = $method; }
if ($search) { $where .= ' AND s.invoice_number LIKE ?'; $params[] = "%$search%"; }

$sales = $db->prepare("SELECT s.*, u.full_name_".LANG." as uname FROM sales s LEFT JOIN users u ON s.user_id=u.id $where ORDER BY s.created_at DESC LIMIT 200");
$sales->execute($params);
$sales = $sales->fetchAll();
?>

<div class="card mb-2" style="padding:.9rem">
  <form class="flex gap-1 flex-wrap" method="GET">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="date" name="from" value="<?= $from ?>">
    <input type="date" name="to" value="<?= $to ?>">
    <select name="method" style="width:130px">
      <option value=""><?= LANG==='ar'?'— كل الدفع —':'— All methods —' ?></option>
      <option value="cash" <?= $method==='cash'?'selected':'' ?>><?= t('cash') ?></option>
      <option value="card" <?= $method==='card'?'selected':'' ?>><?= t('card') ?></option>
      <option value="split"<?= $method==='split'?'selected':'' ?>><?= t('split') ?></option>
    </select>
    <input type="text" name="search" placeholder="<?= LANG==='ar'?'رقم الفاتورة...':'Invoice number...' ?>" value="<?= sanitize($search) ?>" style="width:180px">
    <button class="btn btn-primary"><i class="fa fa-search"></i> <?= t('filter') ?></button>
  </form>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th><?= t('invoice') ?></th>
        <th><?= t('date') ?></th>
        <th><?= t('cashier') ?></th>
        <th><?= t('subtotal') ?></th>
        <th><?= t('discount') ?></th>
        <th><?= t('total') ?></th>
        <th><?= t('payment_method') ?></th>
        <th><?= t('status') ?></th>
        <th><?= t('actions') ?></th>
      </tr></thead>
      <tbody>
      <?php if (empty($sales)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem"><?= t('no_results') ?></td></tr>
      <?php endif; ?>
      <?php foreach ($sales as $s): ?>
      <tr>
        <td class="mono fw-bold" style="color:var(--brand)"><?= sanitize($s['invoice_number']) ?></td>
        <td class="mono text-muted" style="font-size:.78rem"><?= date('d/m/y H:i',strtotime($s['created_at'])) ?></td>
        <td><?= sanitize($s['uname']??'—') ?></td>
        <td class="mono"><?= format_currency((float)$s['subtotal']) ?></td>
        <td class="mono text-muted"><?= $s['discount_total']>0 ? format_currency((float)$s['discount_total']) : '—' ?></td>
        <td class="mono fw-bold text-brand"><?= format_currency((float)$s['total']) ?></td>
        <td><span class="badge badge-brand"><?= t($s['payment_method']) ?></span></td>
        <td>
          <?php $sc=['completed'=>'badge-success','void'=>'badge-danger','refunded'=>'badge-warning']; ?>
          <span class="badge <?= $sc[$s['status']]??'badge-muted' ?>"><?= t($s['status']) ?></span>
        </td>
        <td>
          <button class="btn btn-sm btn-secondary" onclick="viewSale(<?= $s['id'] ?>)"><i class="fa fa-eye"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Sale Detail Modal -->
<div class="modal-overlay" id="saleModal">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <span class="modal-title" id="saleModalTitle"><?= LANG==='ar'?'تفاصيل الفاتورة':'Invoice Details' ?></span>
      <button class="modal-close" onclick="closeModal('saleModal')">✕</button>
    </div>
    <div id="saleModalBody">...</div>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}

async function viewSale(id) {
  const r = await fetch('http://localhost/bangeen_pos/api/sale_detail.php?id='+id+'&lang=<?= LANG ?>');
  const d = await r.json();
  if (!d.success) return;
  const s = d.sale; const items = d.items;
  document.getElementById('saleModalTitle').textContent = s.invoice_number;
  let html = `<div class="flex-between mb-1"><span class="text-muted"><?= t('date') ?>:</span><span class="mono">${s.created_at}</span></div>`;
  html += `<div class="flex-between mb-1"><span class="text-muted"><?= t('cashier') ?>:</span><span>${s.uname||'—'}</span></div>`;
  html += `<div class="flex-between mb-1"><span class="text-muted"><?= t('payment_method') ?>:</span><span class="badge badge-brand">${s.payment_method}</span></div>`;
  html += '<div class="divider"></div>';
  html += '<div class="table-wrap"><table><thead><tr><th><?= t("product_name") ?></th><th><?= t("quantity") ?></th><th><?= t("price") ?></th><th><?= t("total") ?></th></tr></thead><tbody>';
  items.forEach(i => { html += `<tr><td>${i.product_name}</td><td class="mono">${i.quantity}</td><td class="mono">${i.unit_price}</td><td class="mono fw-bold text-brand">${i.subtotal}</td></tr>`; });
  html += '</tbody></table></div>';
  html += `<div class="divider"></div>`;
  html += `<div class="flex-between fw-bold" style="font-size:1.05rem"><span><?= t('total') ?>:</span><span class="text-brand">${s.total_fmt}</span></div>`;
  document.getElementById('saleModalBody').innerHTML = html;
  openModal('saleModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>