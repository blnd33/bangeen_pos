<?php
require_once __DIR__ . '/includes/config.php';
$page_title = LANG==='ar' ? 'المبادلة والاسترداد' : 'Exchange & Returns';
$active_nav = 'exchange';
require_once __DIR__ . '/includes/layout.php';
$db = DB::get();

// Handle AJAX actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Cancel a sale (void it and restore stock)
    if ($action === 'cancel_sale') {
        $id = (int)$_POST['sale_id'];
        $sale = $db->prepare("SELECT * FROM sales WHERE id=?")->execute([$id]) ? $db->prepare("SELECT * FROM sales WHERE id=?")->execute([$id]) : null;
        $stmt = $db->prepare("SELECT * FROM sales WHERE id=?");
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        if (!$sale || $sale['status'] === 'void') {
            echo json_encode(['success'=>false,'error'=>'Sale not found or already void']);
            exit;
        }
        // Restore stock
        $items = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?");
        $items->execute([$id]);
        $items = $items->fetchAll();
        foreach ($items as $item) {
            if ($item['product_id']) {
                $db->prepare("UPDATE products SET stock_qty=stock_qty+? WHERE id=?")->execute([$item['quantity'], $item['product_id']]);
            }
        }
        $ok = $db->prepare("UPDATE sales SET status='void' WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>$ok]);
        exit;
    }

    // Process exchange: cancel original sale, create a new one
    if ($action === 'exchange_sale') {
        $orig_id = (int)$_POST['sale_id'];
        $new_cart = json_decode($_POST['new_cart'] ?? '[]', true);
        $payment = $_POST['payment_method'] ?? 'cash';
        $cash = (float)($_POST['cash_tendered'] ?? 0);

        // Get original sale
        $stmtO = $db->prepare("SELECT * FROM sales WHERE id=?");
        $stmtO->execute([$orig_id]);
        $orig = $stmtO->fetch();
        if (!$orig || $orig['status'] === 'void') {
            echo json_encode(['success'=>false,'error'=>'Original sale not found']);
            exit;
        }

        // Restore original stock
        $origItems = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?");
        $origItems->execute([$orig_id]);
        $origItems = $origItems->fetchAll();
        foreach ($origItems as $oi) {
            if ($oi['product_id']) {
                $db->prepare("UPDATE products SET stock_qty=stock_qty+? WHERE id=?")->execute([$oi['quantity'], $oi['product_id']]);
            }
        }
        $db->prepare("UPDATE sales SET status='void' WHERE id=?")->execute([$orig_id]);

        if (!empty($new_cart)) {
            // Create new sale
            $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $new_cart));
            $total = $subtotal;
            $uid = current_user()['id'];
            // Get next invoice
            $db->prepare("UPDATE counters SET value=value+1 WHERE name='barcode_seq'")->execute();
            $seq = $db->query("SELECT value FROM counters WHERE name='barcode_seq'")->fetchColumn();
            $invoice = 'EXC-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $db->prepare("INSERT INTO sales (invoice_number,user_id,subtotal,discount_total,total,payment_method,cash_tendered,change_given,status) VALUES(?,?,?,0,?,?,?,?,?)")
               ->execute([$invoice, $uid, $subtotal, $total, $payment, $cash, max(0, $cash-$total), 'completed']);
            $new_sale_id = $db->lastInsertId();
            foreach ($new_cart as $item) {
                $db->prepare("INSERT INTO sale_items (sale_id,product_id,product_name_ar,product_name_en,unit_price,quantity,discount_pct,subtotal) VALUES(?,?,?,?,?,?,0,?)")
                   ->execute([$new_sale_id, $item['id'], $item['name'], $item['name'], $item['price'], $item['qty'], $item['price']*$item['qty']]);
                $db->prepare("UPDATE products SET stock_qty=stock_qty-? WHERE id=?")->execute([$item['qty'], $item['id']]);
            }
            echo json_encode(['success'=>true,'invoice'=>$invoice]);
        } else {
            echo json_encode(['success'=>true,'invoice'=>'VOID-'.$orig['invoice_number']]);
        }
        exit;
    }
    echo json_encode(['success'=>false,'error'=>'unknown']);
    exit;
}

// Get recent sales for lookup
$recent = $db->prepare("SELECT s.*, u.full_name_".LANG." as uname FROM sales s LEFT JOIN users u ON s.user_id=u.id WHERE s.status='completed' ORDER BY s.created_at DESC LIMIT 50");
$recent->execute();
$recent = $recent->fetchAll();
$currency = get_setting('currency_symbol','IQD');
?>

<style>
.exch-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.sale-search-res{max-height:320px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface)}
.sale-item-row{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid var(--border)}
.sale-item-row:last-child{border-bottom:none}
.exch-badge{display:inline-block;padding:.2rem .5rem;border-radius:5px;font-size:.72rem;font-weight:700}
@media(max-width:800px){.exch-grid{grid-template-columns:1fr}}
</style>

<div class="exch-grid">
  <!-- LEFT: Find Original Sale -->
  <div>
    <div class="card mb-2">
      <div class="card-title"><i class="fa fa-search text-brand"></i> <?= LANG==='ar'?'البحث عن الفاتورة الأصلية':'Find Original Invoice' ?></div>
      <div class="form-group">
        <label><?= LANG==='ar'?'رقم الفاتورة':'Invoice Number' ?></label>
        <input type="text" id="saleSearchInput" placeholder="<?= LANG==='ar'?'اكتب رقم الفاتورة...':'Type invoice number...' ?>" oninput="filterSales(this.value)" autocomplete="off">
      </div>
      <div class="sale-search-res" id="saleList">
        <?php foreach ($recent as $s): ?>
        <div class="sale-item-row" onclick="selectSale(<?= $s['id'] ?>,'<?= addslashes($s['invoice_number']) ?>')" style="cursor:pointer" data-inv="<?= strtolower($s['invoice_number']) ?>">
          <div style="flex:1">
            <div class="fw-bold" style="color:var(--brand)"><?= sanitize($s['invoice_number']) ?></div>
            <div class="text-muted" style="font-size:.75rem"><?= date('d/m/y H:i',strtotime($s['created_at'])) ?> — <?= sanitize($s['uname']??'') ?></div>
          </div>
          <span class="exch-badge" style="background:var(--brand-soft);color:var(--brand)"><?= $currency.' '.number_format((float)$s['total']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Original sale details -->
    <div class="card" id="origSaleCard" style="display:none">
      <div class="card-title"><i class="fa fa-receipt text-brand"></i> <span id="origSaleTitle"><?= LANG==='ar'?'تفاصيل الفاتورة':'Invoice Details' ?></span></div>
      <div id="origSaleBody"></div>
      <div class="flex gap-1 mt-2">
        <button class="btn btn-danger btn-sm" onclick="cancelSale()"><i class="fa fa-ban"></i> <?= LANG==='ar'?'إلغاء الفاتورة فقط':'Cancel Sale Only' ?></button>
        <button class="btn btn-primary btn-sm" onclick="startExchange()"><i class="fa fa-arrows-rotate"></i> <?= LANG==='ar'?'مبادلة بمنتجات جديدة':'Exchange with New Items' ?></button>
      </div>
    </div>
  </div>

  <!-- RIGHT: New Items (for exchange) -->
  <div id="exchangePanel" style="display:none">
    <div class="card">
      <div class="card-title"><i class="fa fa-arrows-rotate text-brand"></i> <?= LANG==='ar'?'المنتجات الجديدة للمبادلة':'New Items for Exchange' ?></div>
      <div class="form-group">
        <label><?= LANG==='ar'?'بحث منتج':'Search Product' ?></label>
        <input type="text" id="exchProductSearch" placeholder="<?= LANG==='ar'?'ابحث عن منتج...':'Search product...' ?>" oninput="searchExchProduct(this.value)">
      </div>
      <div id="exchProductResults" style="max-height:160px;overflow-y:auto;margin-bottom:.75rem"></div>
      <div class="card-title" style="margin-top:.5rem"><i class="fa fa-cart-shopping text-brand"></i> <?= LANG==='ar'?'السلة الجديدة':'New Cart' ?></div>
      <div id="exchCart" style="min-height:80px"></div>
      <div class="divider"></div>
      <div class="flex-between fw-bold" style="font-size:1rem;margin-bottom:.75rem">
        <span><?= LANG==='ar'?'الإجمالي الجديد':'New Total' ?></span>
        <span id="exchTotal" class="text-brand mono"><?= $currency ?> 0</span>
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'طريقة الدفع':'Payment' ?></label>
        <select id="exchPayment"><option value="cash"><?= LANG==='ar'?'نقد':'Cash' ?></option><option value="card"><?= LANG==='ar'?'بطاقة':'Card' ?></option></select>
      </div>
      <button class="btn btn-primary btn-full" onclick="confirmExchange()"><i class="fa fa-check-circle"></i> <?= LANG==='ar'?'تأكيد المبادلة':'Confirm Exchange' ?></button>
    </div>
  </div>
</div>

<!-- Recent Exchange/Void History -->
<div class="card mt-2" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem;font-weight:700"><i class="fa fa-history text-brand"></i> <?= LANG==='ar'?'سجل الإلغاءات والمبادلات':'Cancellation & Exchange History' ?></div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th><?= LANG==='ar'?'الفاتورة':'Invoice' ?></th>
        <th><?= LANG==='ar'?'التاريخ':'Date' ?></th>
        <th><?= LANG==='ar'?'الكاشير':'Cashier' ?></th>
        <th><?= LANG==='ar'?'الإجمالي':'Total' ?></th>
        <th><?= LANG==='ar'?'الحالة':'Status' ?></th>
      </tr></thead>
      <tbody>
      <?php
      $voids = $db->prepare("SELECT s.*, u.full_name_".LANG." as uname FROM sales s LEFT JOIN users u ON s.user_id=u.id WHERE s.status IN ('void','refunded') ORDER BY s.created_at DESC LIMIT 30");
      $voids->execute();
      $voids = $voids->fetchAll();
      if (empty($voids)): ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem"><?= LANG==='ar'?'لا يوجد سجل':'No history' ?></td></tr>
      <?php else: foreach ($voids as $v): ?>
      <tr>
        <td class="mono fw-bold" style="color:var(--brand)"><?= sanitize($v['invoice_number']) ?></td>
        <td class="mono text-muted"><?= date('d/m/y H:i',strtotime($v['created_at'])) ?></td>
        <td><?= sanitize($v['uname']??'—') ?></td>
        <td class="mono"><?= $currency.' '.number_format((float)$v['total']) ?></td>
        <td><span class="badge badge-danger"><?= $v['status'] ?></span></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const CUR = '<?= $currency ?>';
const LANG = '<?= LANG ?>';
let selectedSaleId = null;
let allProducts = [];
let exchCart = [];

// Load all products for exchange
fetch('http://localhost/bangeen_pos/api/products.php?cat=0&lang='+LANG).then(r=>r.json()).then(d=>{ allProducts = d.products||[]; });

function filterSales(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#saleList .sale-item-row').forEach(el => {
    el.style.display = (!q || el.dataset.inv.includes(q)) ? '' : 'none';
  });
}

async function selectSale(id, inv) {
  selectedSaleId = id;
  document.getElementById('origSaleTitle').textContent = inv;
  const r = await fetch('http://localhost/bangeen_pos/api/sale_detail.php?id='+id+'&lang='+LANG);
  const d = await r.json();
  if (!d.success) return;
  const s = d.sale, items = d.items;
  let html = '<div class="table-wrap"><table><thead><tr><th>'+(LANG==='ar'?'المنتج':'Item')+'</th><th>'+(LANG==='ar'?'الكمية':'Qty')+'</th><th>'+(LANG==='ar'?'السعر':'Price')+'</th></tr></thead><tbody>';
  items.forEach(i => { html += `<tr><td>${i.product_name}</td><td class="mono">${i.quantity}</td><td class="mono">${i.unit_price}</td></tr>`; });
  html += '</tbody></table></div>';
  html += `<div class="flex-between fw-bold mt-1"><span>${LANG==='ar'?'الإجمالي':'Total'}</span><span class="text-brand mono">${s.total_fmt}</span></div>`;
  document.getElementById('origSaleBody').innerHTML = html;
  document.getElementById('origSaleCard').style.display = '';
  document.getElementById('exchangePanel').style.display = 'none';
  exchCart = [];
  renderExchCart();
}

function cancelSale() {
  if (!selectedSaleId) return;
  if (!confirm(LANG==='ar'?'هل تريد إلغاء هذه الفاتورة واسترجاع المخزون؟':'Cancel this sale and restore stock?')) return;
  const fd = new FormData();
  fd.append('action','cancel_sale'); fd.append('sale_id', selectedSaleId);
  fetch('exchange.php?lang='+LANG, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) { alert(LANG==='ar'?'تم إلغاء الفاتورة بنجاح':'Sale cancelled successfully'); location.reload(); }
    else alert(d.error||'Error');
  });
}

function startExchange() {
  document.getElementById('exchangePanel').style.display = '';
}

function searchExchProduct(q) {
  q = q.trim().toLowerCase();
  const res = document.getElementById('exchProductResults');
  if (!q) { res.innerHTML = ''; return; }
  const found = allProducts.filter(p => p.name.toLowerCase().includes(q) || (p.barcode_text && p.barcode_text.toLowerCase()===q)).slice(0,8);
  res.innerHTML = found.map(p => `<div class="sale-item-row" style="cursor:pointer" onclick='addExchItem(${JSON.stringify(p).replace(/'/g,"&apos;")})'>
    <div style="flex:1"><div class="fw-bold">${p.name}</div><div class="text-muted" style="font-size:.75rem">${CUR} ${parseFloat(p.price).toLocaleString()}</div></div>
    <span class="text-muted" style="font-size:.75rem">${LANG==='ar'?'مخزون':'Stock'}: ${p.stock_qty}</span>
  </div>`).join('');
}

function addExchItem(p) {
  const ex = exchCart.find(i=>i.id===p.id);
  if (ex) ex.qty++;
  else exchCart.push({id:p.id,name:p.name,price:parseFloat(p.price),qty:1,stock:p.stock_qty});
  renderExchCart();
  document.getElementById('exchProductSearch').value='';
  document.getElementById('exchProductResults').innerHTML='';
}

function renderExchCart() {
  const c = document.getElementById('exchCart');
  if (!exchCart.length) { c.innerHTML = '<p class="text-muted text-center" style="padding:1rem">'+(LANG==='ar'?'السلة فارغة':'Cart is empty')+'</p>'; document.getElementById('exchTotal').textContent=CUR+' 0'; return; }
  c.innerHTML = exchCart.map((it,idx)=>`<div class="sale-item-row">
    <div style="flex:1"><div class="fw-bold">${it.name}</div><div class="text-muted" style="font-size:.75rem">${CUR} ${it.price.toLocaleString()}</div></div>
    <div class="flex gap-1 align-center">
      <button class="qty-btn" onclick="chExchQty(${idx},-1)">−</button>
      <span class="mono fw-bold">${it.qty}</span>
      <button class="qty-btn" onclick="chExchQty(${idx},1)">+</button>
    </div>
    <span class="mono fw-bold text-brand">${CUR} ${(it.price*it.qty).toLocaleString()}</span>
    <button class="ci-remove" onclick="rmExch(${idx})">✕</button>
  </div>`).join('');
  const total = exchCart.reduce((a,i)=>a+i.price*i.qty,0);
  document.getElementById('exchTotal').textContent = CUR+' '+total.toLocaleString();
}
function chExchQty(idx,d){exchCart[idx].qty=Math.max(1,exchCart[idx].qty+d);renderExchCart();}
function rmExch(idx){exchCart.splice(idx,1);renderExchCart();}

function confirmExchange() {
  if (!selectedSaleId) return alert(LANG==='ar'?'اختر فاتورة أولاً':'Select a sale first');
  if (!confirm(LANG==='ar'?'تأكيد عملية المبادلة؟':'Confirm exchange?')) return;
  const fd = new FormData();
  fd.append('action','exchange_sale');
  fd.append('sale_id', selectedSaleId);
  fd.append('new_cart', JSON.stringify(exchCart));
  fd.append('payment_method', document.getElementById('exchPayment').value);
  fetch('exchange.php?lang='+LANG, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if (d.success) { alert((LANG==='ar'?'تمت المبادلة — فاتورة: ':'Exchange done — Invoice: ')+d.invoice); location.reload(); }
    else alert(d.error||'Error');
  });
}
</script>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
