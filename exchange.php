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
    $uid    = current_user()['id'];

    // ── Cancel a sale: void it, restore stock, log the return ──
    if ($action === 'cancel_sale') {
        $id = (int)$_POST['sale_id'];

        $stmt = $db->prepare("SELECT * FROM sales WHERE id=?");
        $stmt->execute([$id]);
        $sale = $stmt->fetch();

        if (!$sale || $sale['status'] === 'void') {
            echo json_encode(['success'=>false,'error'=>'Sale not found or already void']);
            exit;
        }

        // Restore stock and log each item
        $items = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?");
        $items->execute([$id]);
        $items = $items->fetchAll();

        foreach ($items as $item) {
            if ($item['product_id']) {
                $db->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id=?")
                   ->execute([$item['quantity'], $item['product_id']]);

                $db->prepare("INSERT INTO stock_log (product_id, user_id, type, quantity, note_ar, note_en)
                              VALUES (?, ?, 'return', ?, ?, ?)")
                   ->execute([
                       $item['product_id'], $uid, $item['quantity'],
                       'الغاء فاتورة رقم ' . $sale['invoice_number'],
                       'Cancellation of invoice ' . $sale['invoice_number'],
                   ]);
            }
        }

        $ok = $db->prepare("UPDATE sales SET status='void' WHERE id=?")->execute([$id]);
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }

    // ── Exchange: void original, restore stock, create new sale ──
    if ($action === 'exchange_sale') {
        $orig_id  = (int)$_POST['sale_id'];
        $new_cart = json_decode($_POST['new_cart'] ?? '[]', true);
        $payment  = $_POST['payment_method'] ?? 'cash';
        $cash     = (float)($_POST['cash_tendered'] ?? 0);

        $stmtO = $db->prepare("SELECT * FROM sales WHERE id=?");
        $stmtO->execute([$orig_id]);
        $orig = $stmtO->fetch();

        if (!$orig || $orig['status'] === 'void') {
            echo json_encode(['success'=>false,'error'=>'Original sale not found or already void']);
            exit;
        }

        // Step 1: Restore original items + log
        $origItems = $db->prepare("SELECT * FROM sale_items WHERE sale_id=?");
        $origItems->execute([$orig_id]);
        $origItems = $origItems->fetchAll();

        foreach ($origItems as $oi) {
            if ($oi['product_id']) {
                $db->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id=?")
                   ->execute([$oi['quantity'], $oi['product_id']]);

                $db->prepare("INSERT INTO stock_log (product_id, user_id, type, quantity, note_ar, note_en)
                              VALUES (?, ?, 'return', ?, ?, ?)")
                   ->execute([
                       $oi['product_id'], $uid, $oi['quantity'],
                       'مبادلة: ارجاع فاتورة ' . $orig['invoice_number'],
                       'Exchange: return from invoice ' . $orig['invoice_number'],
                   ]);
            }
        }

        // Step 2: Void original
        $db->prepare("UPDATE sales SET status='void' WHERE id=?")->execute([$orig_id]);

        // Step 3: Create new sale if cart not empty
        if (!empty($new_cart)) {
            $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $new_cart));
            $total    = $subtotal;

            $db->prepare("UPDATE counters SET value = value + 1 WHERE name = 'barcode_seq'")->execute();
            $seq     = (int)$db->query("SELECT value FROM counters WHERE name='barcode_seq'")->fetchColumn();
            $invoice = 'EXC-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $notes   = 'Exchange from #' . $orig['invoice_number'];

            $db->prepare("INSERT INTO sales
                            (invoice_number, user_id, subtotal, discount_total, total,
                             payment_method, cash_tendered, change_given, notes, status)
                          VALUES (?,?,?,0,?,?,?,?,?,'completed')")
               ->execute([$invoice, $uid, $subtotal, $total, $payment, $cash, max(0, $cash - $total), $notes]);

            $new_sale_id = (int)$db->lastInsertId();

            foreach ($new_cart as $item) {
                $db->prepare("INSERT INTO sale_items
                                (sale_id, product_id, product_name_ar, product_name_en,
                                 unit_price, quantity, discount_pct, subtotal)
                              VALUES (?,?,?,?,?,?,0,?)")
                   ->execute([
                       $new_sale_id, $item['id'],
                       $item['name'], $item['name'],
                       $item['price'], $item['qty'],
                       $item['price'] * $item['qty'],
                   ]);

                if ($item['id']) {
                    $db->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id=?")
                       ->execute([$item['qty'], $item['id']]);

                    $db->prepare("INSERT INTO stock_log (product_id, user_id, type, quantity, note_ar, note_en)
                                  VALUES (?, ?, 'out', ?, ?, ?)")
                       ->execute([
                           $item['id'], $uid, $item['qty'],
                           'مبادلة: فاتورة جديدة ' . $invoice,
                           'Exchange: new invoice ' . $invoice,
                       ]);
                }
            }

            echo json_encode(['success'=>true, 'invoice'=>$invoice]);
        } else {
            echo json_encode(['success'=>true, 'invoice'=>'VOID-'.$orig['invoice_number']]);
        }
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

// Load recent completed sales for lookup
$recent_stmt = $db->prepare("
    SELECT s.*, u.full_name_".LANG." as uname
    FROM sales s LEFT JOIN users u ON s.user_id = u.id
    WHERE s.status = 'completed'
    ORDER BY s.created_at DESC LIMIT 50
");
$recent_stmt->execute();
$recent = $recent_stmt->fetchAll();

// Void/exchange history
$voids_stmt = $db->prepare("
    SELECT s.*, u.full_name_".LANG." as uname
    FROM sales s LEFT JOIN users u ON s.user_id = u.id
    WHERE s.status IN ('void','refunded')
    ORDER BY s.created_at DESC LIMIT 30
");
$voids_stmt->execute();
$voids = $voids_stmt->fetchAll();

$currency = get_setting('currency_symbol','IQD');
$base_url = BASE_URL;
?>

<style>
.exch-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.sale-search-res{max-height:320px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface)}
.sale-item-row{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid var(--border);cursor:pointer}
.sale-item-row:last-child{border-bottom:none}
.sale-item-row:hover{background:var(--surface2)}
.exch-badge{display:inline-block;padding:.2rem .5rem;border-radius:5px;font-size:.72rem;font-weight:700}
@media(max-width:800px){.exch-grid{grid-template-columns:1fr}}
.pill-void{display:inline-block;padding:.15rem .55rem;border-radius:20px;font-size:.7rem;font-weight:700;background:rgba(220,38,38,.12);color:var(--danger)}
</style>

<div class="exch-grid">

  <!-- LEFT: Find Original Sale -->
  <div>
    <div class="card mb-2">
      <div class="card-title">
        <i class="fa fa-search text-brand"></i>
        <?= LANG==='ar'?'البحث عن الفاتورة الأصلية':'Find Original Invoice' ?>
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'رقم الفاتورة':'Invoice Number' ?></label>
        <input type="text" id="saleSearchInput"
               placeholder="<?= LANG==='ar'?'اكتب رقم الفاتورة...':'Type invoice number...' ?>"
               oninput="filterSales(this.value)" autocomplete="off">
      </div>
      <div class="sale-search-res" id="saleList">
        <?php foreach ($recent as $s): ?>
        <div class="sale-item-row"
             onclick="selectSale(<?= $s['id'] ?>,'<?= addslashes(htmlspecialchars($s['invoice_number'])) ?>')"
             data-inv="<?= strtolower($s['invoice_number']) ?>">
          <div style="flex:1">
            <div class="fw-bold" style="color:var(--brand)"><?= sanitize($s['invoice_number']) ?></div>
            <div class="text-muted" style="font-size:.75rem">
              <?= date('d/m/y H:i',strtotime($s['created_at'])) ?> — <?= sanitize($s['uname']??'') ?>
            </div>
          </div>
          <span class="exch-badge" style="background:var(--brand-soft);color:var(--brand)">
            <?= $currency.' '.number_format((float)$s['total']) ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
          <div style="padding:1.5rem;text-align:center;color:var(--muted)">
            <?= LANG==='ar'?'لا توجد مبيعات مكتملة':'No completed sales' ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Original sale details card -->
    <div class="card" id="origSaleCard" style="display:none">
      <div class="card-title">
        <i class="fa fa-receipt text-brand"></i>
        <span id="origSaleTitle"><?= LANG==='ar'?'تفاصيل الفاتورة':'Invoice Details' ?></span>
      </div>
      <div id="origSaleBody"></div>
      <div id="origSaleError" style="display:none;color:var(--danger);font-size:.85rem;padding:.5rem 0"></div>
      <div class="flex gap-1 mt-2">
        <button class="btn btn-danger btn-sm" onclick="cancelSale()">
          <i class="fa fa-ban"></i>
          <?= LANG==='ar'?'إلغاء واسترجاع المخزون':'Cancel & Restore Stock' ?>
        </button>
        <button class="btn btn-primary btn-sm" onclick="startExchange()">
          <i class="fa fa-arrows-rotate"></i>
          <?= LANG==='ar'?'مبادلة بمنتجات جديدة':'Exchange with New Items' ?>
        </button>
      </div>
    </div>
  </div>

  <!-- RIGHT: New Items for exchange -->
  <div id="exchangePanel" style="display:none">
    <div class="card">
      <div class="card-title">
        <i class="fa fa-arrows-rotate text-brand"></i>
        <?= LANG==='ar'?'المنتجات الجديدة للمبادلة':'New Items for Exchange' ?>
      </div>
      <div class="form-group">
        <label><?= LANG==='ar'?'بحث عن منتج':'Search Product' ?></label>
        <input type="text" id="exchProductSearch"
               placeholder="<?= LANG==='ar'?'ابحث بالاسم أو الباركود...':'Search by name or barcode...' ?>"
               oninput="searchExchProduct(this.value)">
      </div>
      <div id="exchProductResults"
           style="max-height:180px;overflow-y:auto;border:1px solid var(--border);
                  border-radius:var(--radius);margin-bottom:.75rem;display:none">
      </div>

      <div class="card-title" style="margin-top:.5rem">
        <i class="fa fa-cart-shopping text-brand"></i>
        <?= LANG==='ar'?'السلة الجديدة':'New Cart' ?>
      </div>
      <div id="exchCart" style="min-height:80px"></div>

      <div class="divider"></div>
      <div class="flex-between fw-bold" style="font-size:1rem;margin-bottom:.75rem">
        <span><?= LANG==='ar'?'الإجمالي الجديد':'New Total' ?></span>
        <span id="exchTotal" class="text-brand mono"><?= $currency ?> 0</span>
      </div>

      <div class="form-group">
        <label><?= LANG==='ar'?'طريقة الدفع':'Payment Method' ?></label>
        <select id="exchPayment" onchange="toggleCashRow()">
          <option value="cash"><?= LANG==='ar'?'نقد':'Cash' ?></option>
          <option value="card"><?= LANG==='ar'?'بطاقة':'Card' ?></option>
        </select>
      </div>
      <div class="form-group" id="exchCashRow">
        <label><?= LANG==='ar'?'المبلغ المستلم':'Cash Tendered' ?></label>
        <input type="number" id="exchCashIn" placeholder="0" min="0" step="500">
      </div>

      <button class="btn btn-primary btn-full" onclick="confirmExchange()">
        <i class="fa fa-check-circle"></i>
        <?= LANG==='ar'?'تأكيد المبادلة':'Confirm Exchange' ?>
      </button>
      <button class="btn btn-secondary btn-full"
              style="margin-top:.5rem"
              onclick="document.getElementById('exchangePanel').style.display='none'">
        <?= LANG==='ar'?'إلغاء':'Cancel' ?>
      </button>
    </div>
  </div>
</div>

<!-- History table -->
<div class="card mt-2" style="padding:0">
  <div style="padding:1rem 1.25rem .5rem;font-weight:700">
    <i class="fa fa-history text-brand"></i>
    <?= LANG==='ar'?'سجل الإلغاءات والمبادلات':'Cancellation & Exchange History' ?>
  </div>
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
      <?php if (empty($voids)): ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">
          <?= LANG==='ar'?'لا يوجد سجل':'No history yet' ?>
        </td></tr>
      <?php else: foreach ($voids as $v): ?>
      <tr>
        <td class="mono fw-bold" style="color:var(--brand)"><?= sanitize($v['invoice_number']) ?></td>
        <td class="mono text-muted"><?= date('d/m/y H:i',strtotime($v['created_at'])) ?></td>
        <td><?= sanitize($v['uname']??'—') ?></td>
        <td class="mono"><?= $currency.' '.number_format((float)$v['total']) ?></td>
        <td><span class="pill-void"><?= LANG==='ar'?'ملغي':'Void' ?></span></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const CUR      = '<?= $currency ?>';
const LANG     = '<?= LANG ?>';
const BASE_URL = '<?= $base_url ?>';

let selectedSaleId = null;
let allProducts    = [];
let exchCart       = [];

// Load all products once
fetch(BASE_URL + '/api/products.php?cat=0&lang=' + LANG)
  .then(r => r.json())
  .then(d => { allProducts = d.products || []; })
  .catch(() => {});

function filterSales(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#saleList .sale-item-row').forEach(el => {
    el.style.display = (!q || el.dataset.inv.includes(q)) ? '' : 'none';
  });
}

async function selectSale(id, inv) {
  selectedSaleId = id;
  document.getElementById('origSaleTitle').textContent = inv;
  document.getElementById('origSaleError').style.display = 'none';
  document.getElementById('origSaleBody').innerHTML =
    '<p class="text-muted" style="padding:.5rem">' +
    (LANG==='ar' ? 'جاري التحميل...' : 'Loading...') + '</p>';
  document.getElementById('origSaleCard').style.display = '';
  document.getElementById('exchangePanel').style.display = 'none';
  exchCart = [];
  renderExchCart();

  try {
    const r = await fetch(BASE_URL + '/api/sale_detail.php?id=' + id + '&lang=' + LANG);
    const d = await r.json();
    if (!d.success) { showOrigErr(LANG==='ar'?'تعذر تحميل الفاتورة':'Could not load invoice'); return; }

    const s = d.sale, items = d.items;
    let html = '<div class="table-wrap"><table><thead><tr>';
    html += '<th>'+(LANG==='ar'?'المنتج':'Item')+'</th>';
    html += '<th>'+(LANG==='ar'?'الكمية':'Qty')+'</th>';
    html += '<th>'+(LANG==='ar'?'السعر':'Price')+'</th>';
    html += '</tr></thead><tbody>';
    items.forEach(i => {
      html += `<tr><td>${i.product_name}</td><td class="mono">${i.quantity}</td><td class="mono">${i.unit_price}</td></tr>`;
    });
    html += '</tbody></table></div>';
    html += `<div class="flex-between fw-bold mt-1"><span>${LANG==='ar'?'الإجمالي':'Total'}</span><span class="text-brand mono">${s.total_fmt}</span></div>`;
    document.getElementById('origSaleBody').innerHTML = html;
  } catch(e) {
    showOrigErr(LANG==='ar'?'خطأ في الاتصال':'Connection error');
  }
}

function showOrigErr(msg) {
  document.getElementById('origSaleBody').innerHTML = '';
  const el = document.getElementById('origSaleError');
  el.textContent = msg;
  el.style.display = '';
}

function cancelSale() {
  if (!selectedSaleId) return;
  const msg = LANG==='ar'
    ? 'هل تريد إلغاء هذه الفاتورة واسترجاع المخزون؟\nلا يمكن التراجع عن هذه العملية.'
    : 'Cancel this sale and restore stock to inventory?\nThis cannot be undone.';
  if (!confirm(msg)) return;

  const fd = new FormData();
  fd.append('action',  'cancel_sale');
  fd.append('sale_id', selectedSaleId);

  fetch(BASE_URL + '/exchange.php?lang=' + LANG, {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        alert(LANG==='ar'
          ? 'تم إلغاء الفاتورة واسترجاع المخزون بنجاح'
          : 'Sale cancelled and stock restored successfully');
        location.reload();
      } else {
        alert('Error: ' + (d.error || 'Unknown'));
      }
    })
    .catch(() => alert(LANG==='ar'?'خطأ في الاتصال':'Connection error'));
}

function startExchange() {
  document.getElementById('exchangePanel').style.display = '';
  document.getElementById('exchProductSearch').focus();
}

function searchExchProduct(q) {
  q = q.trim().toLowerCase();
  const res = document.getElementById('exchProductResults');
  if (!q) { res.style.display = 'none'; res.innerHTML = ''; return; }

  const found = allProducts.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.barcode_text && p.barcode_text.toLowerCase() === q)
  ).slice(0, 8);

  res.style.display = '';
  if (!found.length) {
    res.innerHTML = `<div style="padding:.75rem;text-align:center;color:var(--muted);font-size:.82rem">${LANG==='ar'?'لا توجد نتائج':'No results'}</div>`;
    return;
  }
  res.innerHTML = found.map(p =>
    `<div class="sale-item-row" onclick='addExchItem(${JSON.stringify(p).replace(/'/g,"&#39;")})'>
      <div style="flex:1">
        <div class="fw-bold" style="font-size:.85rem">${p.name}</div>
        <div class="text-muted" style="font-size:.72rem">${CUR} ${parseFloat(p.price).toLocaleString()}</div>
      </div>
      <span class="text-muted" style="font-size:.72rem">${LANG==='ar'?'مخزون':'Stock'}: ${p.stock_qty}</span>
    </div>`
  ).join('');
}

function addExchItem(p) {
  const ex = exchCart.find(i => i.id === p.id);
  if (ex) ex.qty++;
  else exchCart.push({id:p.id, name:p.name, price:parseFloat(p.price), qty:1, stock:p.stock_qty});
  renderExchCart();
  document.getElementById('exchProductSearch').value = '';
  document.getElementById('exchProductResults').style.display = 'none';
}

function renderExchCart() {
  const c = document.getElementById('exchCart');
  if (!exchCart.length) {
    c.innerHTML = `<p class="text-muted text-center" style="padding:1rem">${LANG==='ar'?'السلة فارغة':'Cart is empty'}</p>`;
    document.getElementById('exchTotal').textContent = CUR + ' 0';
    return;
  }
  c.innerHTML = exchCart.map((it,idx) => `
    <div class="sale-item-row" style="flex-wrap:nowrap">
      <div style="flex:1;min-width:0">
        <div class="fw-bold" style="font-size:.85rem">${it.name}</div>
        <div class="text-muted" style="font-size:.72rem">${CUR} ${it.price.toLocaleString()}</div>
      </div>
      <div class="flex gap-1 align-center">
        <button class="qty-btn" onclick="chExchQty(${idx},-1)">−</button>
        <span class="mono fw-bold" style="min-width:20px;text-align:center">${it.qty}</span>
        <button class="qty-btn" onclick="chExchQty(${idx},1)">+</button>
      </div>
      <span class="mono fw-bold text-brand" style="margin:0 .4rem">${CUR} ${(it.price*it.qty).toLocaleString()}</span>
      <button class="ci-remove" onclick="rmExch(${idx})">✕</button>
    </div>`).join('');
  const total = exchCart.reduce((a,i) => a + i.price * i.qty, 0);
  document.getElementById('exchTotal').textContent = CUR + ' ' + total.toLocaleString();
}

function chExchQty(idx,d){exchCart[idx].qty=Math.max(1,exchCart[idx].qty+d);renderExchCart();}
function rmExch(idx){exchCart.splice(idx,1);renderExchCart();}
function toggleCashRow(){
  document.getElementById('exchCashRow').style.display =
    document.getElementById('exchPayment').value === 'cash' ? '' : 'none';
}

function confirmExchange() {
  if (!selectedSaleId) return alert(LANG==='ar'?'اختر فاتورة أولاً':'Select a sale first');
  const total = exchCart.reduce((a,i) => a+i.price*i.qty, 0);
  const msg = exchCart.length
    ? (LANG==='ar' ? `تأكيد المبادلة؟\nالإجمالي الجديد: ${CUR} ${total.toLocaleString()}` : `Confirm exchange?\nNew total: ${CUR} ${total.toLocaleString()}`)
    : (LANG==='ar' ? 'السلة فارغة — سيتم إلغاء الفاتورة الأصلية فقط. هل تريد المتابعة؟' : 'Cart is empty — original will be voided only. Continue?');
  if (!confirm(msg)) return;

  const fd = new FormData();
  fd.append('action',         'exchange_sale');
  fd.append('sale_id',        selectedSaleId);
  fd.append('new_cart',       JSON.stringify(exchCart));
  fd.append('payment_method', document.getElementById('exchPayment').value);
  fd.append('cash_tendered',  document.getElementById('exchCashIn').value || 0);

  fetch(BASE_URL + '/exchange.php?lang=' + LANG, {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        alert((LANG==='ar'?'تمت العملية — فاتورة رقم: ':'Done — Invoice: ') + d.invoice);
        location.reload();
      } else {
        alert('Error: ' + (d.error || 'Unknown'));
      }
    })
    .catch(() => alert(LANG==='ar'?'خطأ في الاتصال':'Connection error'));
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>