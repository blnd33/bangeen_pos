<?php
require_once __DIR__ . '/includes/config.php';
$page_title = t('pos');
$active_nav = 'pos';
require_once __DIR__ . '/includes/layout.php';
$cats = DB::get()->query("SELECT * FROM categories ORDER BY name_ar")->fetchAll();
$currency = get_setting('currency_symbol','IQD');
?>

<style>
.pos-wrap{display:grid;grid-template-columns:1fr 380px;gap:1rem;height:calc(100vh - 54px - 3rem)}
.pos-left{display:flex;flex-direction:column;gap:.75rem;overflow:hidden}
.pos-right{display:flex;flex-direction:column;gap:.75rem;overflow:hidden}

.scan-bar{display:flex;gap:.5rem;align-items:center}
.scan-input{flex:1;font-size:1rem;font-family:var(--font-en)!important;font-weight:700;letter-spacing:.04em}

.cat-tabs{display:flex;gap:.4rem;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding-bottom:2px;scroll-behavior:smooth}
.cat-tabs::-webkit-scrollbar{display:none}
.cat-tab{padding:.35rem .9rem;border-radius:99px;border:1px solid var(--border);background:var(--surface2);font-size:.8rem;cursor:pointer;white-space:nowrap;transition:all .15s;font-family:var(--font)}
.cat-tab:hover,.cat-tab.active{background:var(--brand);color:#fff;border-color:var(--brand)}

.cat-scroll-wrap{
  position:relative;
  display:flex;
  align-items:center;
  gap:.3rem;
}
.cat-scroll-wrap::before,.cat-scroll-wrap::after{
  content:'';
  position:absolute;
  top:0;bottom:0;width:40px;
  pointer-events:none;
  z-index:2;
}
.cat-scroll-wrap::before{
  <?= ALIGN_START ?>:28px;
  background:linear-gradient(to <?= ALIGN_END ?>,var(--bg),transparent);
}
.cat-scroll-wrap::after{
  <?= ALIGN_END ?>:28px;
  background:linear-gradient(to <?= ALIGN_START ?>,var(--bg),transparent);
}
.cat-arrow{
  flex-shrink:0;
  width:28px;height:28px;border-radius:8px;
  border:1px solid var(--border);
  background:var(--surface);
  color:var(--text2);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:.75rem;transition:all .15s;
  z-index:3;
}
.cat-arrow:hover{background:var(--brand);color:#fff;border-color:var(--brand)}

.product-grid{flex:1;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.6rem;align-content:start}
.product-tile{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:.85rem .7rem;cursor:pointer;transition:all .15s;text-align:center;display:flex;flex-direction:column;gap:.35rem}
.product-tile:hover{border-color:var(--brand);box-shadow:0 4px 12px rgba(196,146,42,.15);transform:translateY(-1px)}
.product-tile.out-of-stock{opacity:.45;cursor:not-allowed}
.tile-name{font-size:.82rem;font-weight:700;line-height:1.2}
.tile-price{font-size:.9rem;font-weight:800;color:var(--brand)}
.tile-stock{font-size:.7rem;color:var(--muted)}

/* Cart */
.cart-header{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem}
.cart-items{flex:1;overflow-y:auto;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:.75rem}
.cart-item{display:flex;align-items:center;gap:.5rem;padding:.55rem 0;border-bottom:1px solid var(--border)}
.cart-item:last-child{border-bottom:none}
.ci-name{flex:1;font-size:.85rem;font-weight:600;min-width:0}
.ci-name small{display:block;font-size:.72rem;color:var(--muted);font-weight:400}
.ci-qty{display:flex;align-items:center;gap:.3rem}
.qty-btn{width:26px;height:26px;border-radius:6px;border:1px solid var(--border);background:var(--surface2);cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:all .12s}
.qty-btn:hover{background:var(--brand);color:#fff;border-color:var(--brand)}
.qty-input{width:38px;text-align:center;padding:.2rem;border-radius:5px;border:1px solid var(--border);background:var(--surface2);font-size:.85rem;font-family:var(--font-en)}
.ci-price{font-weight:700;font-size:.88rem;color:var(--brand);white-space:nowrap;font-family:var(--font-en)}
.ci-remove{width:24px;height:24px;border:none;background:transparent;color:var(--muted);cursor:pointer;border-radius:5px;display:flex;align-items:center;justify-content:center;transition:all .12s;flex-shrink:0}
.ci-remove:hover{background:rgba(220,38,38,.1);color:var(--danger)}

.cart-totals{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1rem}
.total-row{display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;font-size:.88rem}
.total-row.grand{font-size:1.1rem;font-weight:800;border-top:1px solid var(--border);padding-top:.75rem;margin-top:.35rem;color:var(--brand)}

.payment-btns{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin:1rem 0}
.pay-btn{padding:.55rem;border-radius:9px;border:2px solid var(--border);background:var(--surface2);font-size:.82rem;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;font-family:var(--font)}
.pay-btn.active{border-color:var(--brand);background:var(--brand-soft);color:var(--brand)}

.empty-cart{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--muted);gap:.75rem;padding:2rem}
.empty-icon{font-size:2.5rem;opacity:.3}
</style>

<div class="pos-wrap">
  <!-- LEFT: Products -->
  <div class="pos-left">
    <!-- Scan bar -->
    <div class="card" style="padding:.75rem">
      <div class="scan-bar">
        <i class="fa fa-barcode" style="color:var(--brand);font-size:1.2rem"></i>
        <div style="flex:1;position:relative">
          <input type="text" id="barcodeInput" class="scan-input"
            placeholder="<?= LANG==='ar'?'امسح الباركود أو ابحث عن المنتج...':'Scan barcode or search product...' ?>"
            autofocus autocomplete="off"
            style="padding-<?= ALIGN_END ?>:2.2rem">
          <button id="searchClearBtn" onclick="clearSearch()"
            style="display:none;position:absolute;<?= ALIGN_END ?>:8px;top:50%;transform:translateY(-50%);
                   background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;
                   width:24px;height:24px;border-radius:50%;display:none;align-items:center;justify-content:center;">
            <i class="fa fa-times"></i>
          </button>
        </div>
        <button class="btn btn-primary" onclick="searchProduct()"><i class="fa fa-search"></i></button>
      </div>
    </div>

    <!-- Category tabs with scroll arrows -->
    <div class="cat-scroll-wrap">
      <button class="cat-arrow" id="catPrev" onclick="scrollCats(-1)">
        <i class="fa fa-chevron-<?= DIR==='rtl'?'right':'left' ?>"></i>
      </button>

      <div class="cat-tabs" id="catTabs">
        <span class="cat-tab active" data-catid="0" onclick="filterCat(0,this)"><?= LANG==='ar'?'الكل':'All' ?></span>
        <?php foreach ($cats as $c): ?>
        <span class="cat-tab" data-catid="<?= $c['id'] ?>" onclick="filterCat(<?= $c['id'] ?>,this)"><?= sanitize(LANG==='ar'?$c['name_ar']:($c['name_en']?:$c['name_ar'])) ?></span>
        <?php endforeach; ?>
      </div>

      <button class="cat-arrow" id="catNext" onclick="scrollCats(1)">
        <i class="fa fa-chevron-<?= DIR==='rtl'?'left':'right' ?>"></i>
      </button>
    </div>

    <!-- Products -->
    <div class="product-grid" id="productGrid">
      <div class="text-muted text-center" style="grid-column:1/-1;padding:2rem"><?= LANG==='ar'?'جاري التحميل...':'Loading...' ?></div>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-right">
    <!-- Cart header -->
    <div class="cart-header">
      <div class="flex-between">
        <span style="font-weight:700;font-size:.95rem"><i class="fa fa-cart-shopping text-brand"></i> <?= t('cart') ?></span>
        <button class="btn btn-sm btn-danger" onclick="clearCart()"><i class="fa fa-trash"></i> <?= t('clear_cart') ?></button>
      </div>
      <div class="text-muted" style="font-size:.78rem;margin-top:.25rem" id="cartCount">0 <?= t('items_in_cart') ?></div>
    </div>

    <!-- Cart items -->
    <div class="cart-items" id="cartItems">
      <div class="empty-cart">
        <div class="empty-icon">🛒</div>
        <span><?= t('empty_cart') ?></span>
      </div>
    </div>

    <!-- Totals + checkout -->
    <div class="cart-totals">
      <div class="total-row"><span><?= t('subtotal') ?></span><span id="cartSubtotal" class="mono">—</span></div>
      <div class="total-row">
        <span><?= t('discount') ?> %</span>
        <input type="number" id="discountPct" value="0" min="0" max="100" style="width:65px;text-align:center;padding:.25rem;font-size:.85rem" oninput="renderTotals()">
      </div>
      <div class="total-row"><span><?= t('tax') ?></span><span id="cartTax" class="mono">0</span></div>
      <div class="total-row grand"><span><?= t('total') ?></span><span id="cartTotal" class="mono">—</span></div>

      <div class="payment-btns">
        <div class="pay-btn active" data-method="cash" onclick="setPayment('cash',this)">💵 <?= t('cash') ?></div>
        <div class="pay-btn" data-method="card" onclick="setPayment('card',this)">💳 <?= t('card') ?></div>
        <div class="pay-btn" data-method="split" onclick="setPayment('split',this)">⚡ <?= t('split') ?></div>
      </div>

      <div id="cashSection">
        <div class="form-group mt-1">
          <label><?= t('cash_tendered') ?></label>
          <input type="number" id="cashTendered" step="500" value="0" oninput="calcChange()" style="font-size:1rem;font-weight:700;text-align:center">
        </div>
        <div class="total-row" style="margin-top:.5rem">
          <span><?= t('change') ?></span>
          <span id="changeAmt" class="mono fw-bold text-success">0</span>
        </div>
      </div>

      <button class="btn btn-primary btn-full btn-lg mt-1" onclick="checkout()" style="font-size:1rem">
        <i class="fa fa-check-circle"></i> <?= t('checkout') ?>
      </button>
    </div>
  </div>
</div>

<script>
const LANG = '<?= LANG ?>';
const CUR  = '<?= $currency ?>';
let cart = [];
let allProducts = [];
let currentCat = 0;
let paymentMethod = 'cash';

// Load products
async function loadProducts(catId) {
  const r = await fetch('http://localhost/bangeen_pos/api/products.php?cat='+catId+'&lang='+LANG);
  const data = await r.json();
  allProducts = data.products || [];
  renderGrid(allProducts);
}

function renderGrid(products) {
  const grid = document.getElementById('productGrid');
  if (!products.length) {
    grid.innerHTML = '<div class="text-muted text-center" style="grid-column:1/-1;padding:2rem"><?= t("no_results") ?></div>';
    return;
  }
  grid.innerHTML = products.map(p => `
    <div class="product-tile ${p.stock_qty<=0?'out-of-stock':''}" onclick="${p.stock_qty>0?'addToCart('+JSON.stringify(p).replace(/"/g,'&quot;')+')':''}">
      <div class="tile-name">${p.name}</div>
      <div class="tile-price">${CUR} ${parseFloat(p.price).toLocaleString()}</div>
      <div class="tile-stock">${p.stock_qty} ${p.unit}</div>
    </div>
  `).join('');
}

function scrollCats(dir) {
  const tabs = document.getElementById('catTabs');
  const amount = 220;
  tabs.scrollBy({ left: dir * amount, behavior: 'smooth' });
}

function filterCat(catId, el) {
  currentCat = catId;
  document.querySelectorAll('.cat-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  const filtered = catId===0 ? allProducts : allProducts.filter(p=>p.category_id==catId);
  renderGrid(filtered);
}

// ── SEARCH / BARCODE — filters grid, does NOT auto-add ──────
document.getElementById('barcodeInput').addEventListener('input', function() {
  filterBySearch(this.value);
});

document.getElementById('barcodeInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    filterBySearch(this.value);
  }
  if (e.key === 'Escape') {
    this.value = '';
    filterBySearch('');
  }
});

function filterBySearch(q) {
  q = q.replace(/\r|\n|\t/g, '').trim().toLowerCase();

  // Show clear button
  const clearBtn = document.getElementById('searchClearBtn');
  if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';

  if (!q) {
    // Reset to current category filter
    const activeCat = document.querySelector('.cat-tab.active');
    const catId = activeCat ? parseInt(activeCat.dataset.catid || 0) : 0;
    const filtered = catId === 0 ? allProducts : allProducts.filter(p => p.category_id == catId);
    renderGrid(filtered);
    return;
  }

  // Filter by name (AR or EN) OR barcode
  const results = allProducts.filter(p =>
    (p.name_ar && p.name_ar.toLowerCase().includes(q)) ||
    (p.name_en && p.name_en.toLowerCase().includes(q)) ||
    (p.barcode_text && p.barcode_text.toLowerCase() === q)
  );

  renderGrid(results);

  // If exactly one product found by barcode — highlight it
  if (results.length === 1 && results[0].barcode_text &&
      results[0].barcode_text.toLowerCase() === q) {
    // Scroll grid to top so it's visible
    const grid = document.getElementById('productGrid');
    if (grid) grid.scrollTop = 0;
  }

  if (results.length === 0) {
    const grid = document.getElementById('productGrid');
    if (grid) grid.innerHTML = `<div class="text-muted text-center" style="grid-column:1/-1;padding:2rem">
      <i class="fa fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.5rem"></i>
      ${LANG==='ar' ? 'لا توجد نتائج لـ "'+q+'"' : 'No results for "'+q+'"'}
    </div>`;
  }
}

function clearSearch() {
  const inp = document.getElementById('barcodeInput');
  inp.value = '';
  filterBySearch('');
  inp.focus();
}

// Legacy kept for any direct barcode scanner that fires its own call
async function searchProduct() {
  const q = document.getElementById('barcodeInput').value.trim();
  if (q) filterBySearch(q);
}

// Cart
function addToCart(p) {
  const existing = cart.find(i=>i.id===p.id);
  if (existing) { existing.qty++; }
  else { cart.push({id:p.id,name:p.name,price:parseFloat(p.price),qty:1,unit:p.unit,stock:p.stock_qty}); }
  renderCart();
}

function renderCart() {
  const container = document.getElementById('cartItems');
  const count = cart.reduce((a,i)=>a+i.qty,0);
  document.getElementById('cartCount').textContent = count + ' <?= t("items_in_cart") ?>';

  if (!cart.length) {
    container.innerHTML = '<div class="empty-cart"><div class="empty-icon">🛒</div><span><?= t("empty_cart") ?></span></div>';
    renderTotals(); return;
  }

  container.innerHTML = cart.map((item,idx) => `
    <div class="cart-item">
      <div class="ci-name">
        ${item.name}
        <small>${CUR} ${parseFloat(item.price).toLocaleString()}</small>
      </div>
      <div class="ci-qty">
        <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
        <input class="qty-input" type="number" value="${item.qty}" min="1" max="${item.stock}" onchange="setQty(${idx},this.value)">
        <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
      </div>
      <span class="ci-price">${CUR} ${(item.price*item.qty).toLocaleString()}</span>
      <button class="ci-remove" onclick="removeItem(${idx})">✕</button>
    </div>
  `).join('');
  renderTotals();
}

function changeQty(idx, delta) {
  cart[idx].qty = Math.max(1, Math.min(cart[idx].stock, cart[idx].qty + delta));
  renderCart();
}
function setQty(idx, val) {
  cart[idx].qty = Math.max(1, Math.min(cart[idx].stock, parseInt(val)||1));
  renderCart();
}
function removeItem(idx) { cart.splice(idx,1); renderCart(); }
function clearCart() { cart=[]; renderCart(); }

function renderTotals() {
  const disc = parseFloat(document.getElementById('discountPct').value)||0;
  const sub  = cart.reduce((a,i)=>a+i.price*i.qty,0);
  const discAmt = sub * disc / 100;
  const tax = 0;
  const total = sub - discAmt + tax;
  document.getElementById('cartSubtotal').textContent = CUR+' '+sub.toLocaleString('en',{minimumFractionDigits:0});
  document.getElementById('cartTax').textContent = CUR+' '+tax;
  document.getElementById('cartTotal').textContent = CUR+' '+total.toLocaleString('en',{minimumFractionDigits:0});
  document.getElementById('cashTendered').value = Math.ceil(total/500)*500;
  calcChange();
}

function calcChange() {
  const total = cart.reduce((a,i)=>a+i.price*i.qty,0);
  const disc = parseFloat(document.getElementById('discountPct').value)||0;
  const net = total - (total*disc/100);
  const tendered = parseFloat(document.getElementById('cashTendered').value)||0;
  const change = Math.max(0, tendered - net);
  document.getElementById('changeAmt').textContent = CUR+' '+change.toLocaleString('en',{minimumFractionDigits:0});
}

function setPayment(method, el) {
  paymentMethod = method;
  document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('cashSection').style.display = method==='card' ? 'none' : '';
}

async function checkout() {
  if (!cart.length) { showToast(LANG==='ar'?'السلة فارغة':'Cart is empty','warning'); return; }
  const disc   = parseFloat(document.getElementById('discountPct').value)||0;
  const sub    = cart.reduce((a,i)=>a+i.price*i.qty,0);
  const discAmt= sub*disc/100;
  const total  = sub - discAmt;
  const cash   = parseFloat(document.getElementById('cashTendered').value)||0;

  const payload = { cart, subtotal:sub, discount_total:discAmt, total, payment_method:paymentMethod, cash_tendered:cash, change_given:Math.max(0,cash-total) };
  const r = await fetch('http://localhost/bangeen_pos/api/checkout.php?lang='+LANG, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data = await r.json();
  if (data.success) {
    showToast((LANG==='ar'?'تمت عملية البيع — فاتورة: ':'Sale complete — Invoice: ')+data.invoice,'success',5000);
    printReceipt(data);
    cart=[];
    document.getElementById('discountPct').value=0;
    renderCart();
    loadProducts(currentCat);
  } else {
    showToast(data.error||'Error','error');
  }
}

function printReceipt(data) {
  const lang    = LANG;
  const dir     = lang === 'ar' ? 'rtl' : 'ltr';
  const alignS  = lang === 'ar' ? 'right' : 'left';
  const alignE  = lang === 'ar' ? 'left'  : 'right';
  const cur     = data.currency || CUR;
  const sName   = lang === 'ar' ? (data.store_name_ar || 'بهنگین کریستال') : (data.store_name_en || 'Bangeen Crystal');
  const sAddr   = lang === 'ar' ? (data.store_address_ar || '') : (data.store_address_en || '');
  const footer  = lang === 'ar' ? (data.footer_ar || 'شكراً لزيارتكم') : (data.footer_en || 'Thank you for your visit');

  const fmt = n => {
    const num = parseFloat(n);
    return isNaN(num) ? '0' : num.toLocaleString('en', {minimumFractionDigits:0});
  };

  const rows = data.items.map(i => {
    const price    = parseFloat(i.price) || 0;
    const qty      = parseInt(i.qty) || 1;
    const subtotal = parseFloat(i.subtotal) || (price * qty);
    const name     = lang==='ar' ? (i.name_ar || i.name || '') : (i.name_en || i.name || '');
    return `
    <tr>
      <td style="padding:5px 3px;border-bottom:1px dashed #ddd">${name}</td>
      <td style="padding:5px 3px;border-bottom:1px dashed #ddd;text-align:center">${qty}</td>
      <td style="padding:5px 3px;border-bottom:1px dashed #ddd;text-align:${alignE}">${cur} ${fmt(price)}</td>
      <td style="padding:5px 3px;border-bottom:1px dashed #ddd;text-align:${alignE};font-weight:700">${cur} ${fmt(subtotal)}</td>
    </tr>`;
  }).join('');

  const payLabel   = lang==='ar' ? 'طريقة الدفع'   : 'Payment';
  const cashLabel  = lang==='ar' ? 'المبلغ المستلم': 'Cash Tendered';
  const changeLabel= lang==='ar' ? 'الباقي'         : 'Change';
  const subLabel   = lang==='ar' ? 'المجموع الجزئي': 'Subtotal';
  const discLabel  = lang==='ar' ? 'الخصم'          : 'Discount';
  const taxLabel   = lang==='ar' ? 'الضريبة'        : 'Tax';
  const totalLabel = lang==='ar' ? 'الإجمالي'       : 'TOTAL';
  const itemLabel  = lang==='ar' ? 'المنتج'         : 'Item';
  const qtyLabel   = lang==='ar' ? 'الكمية'         : 'Qty';
  const priceLabel = lang==='ar' ? 'السعر'          : 'Price';
  const totalHLabel= lang==='ar' ? 'المجموع'        : 'Total';
  const invLabel   = lang==='ar' ? 'فاتورة رقم'     : 'Invoice';
  const cashierLabel=lang==='ar' ? 'الكاشير'        : 'Cashier';
  const payMethods = {cash: lang==='ar'?'نقد':'Cash', card: lang==='ar'?'بطاقة':'Card', split: lang==='ar'?'مختلط':'Split'};

  const html = `<!DOCTYPE html>
<html lang="${lang}" dir="${dir}">
<head>
<meta charset="UTF-8">
<title>Receipt - ${data.invoice}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family: 'Tajawal', 'Arial', sans-serif;
    font-size: 13px;
    width: 80mm;
    margin: 0 auto;
    padding: 8px;
    color: #000;
    direction: ${dir};
  }
  .header { text-align:center; margin-bottom:10px; padding-bottom:8px; border-bottom:2px solid #000; }
  .store-name { font-size:18px; font-weight:900; margin-bottom:3px; }
  .store-info { font-size:11px; color:#444; line-height:1.5; }
  .invoice-info { margin:8px 0; padding:6px; background:#f5f0eb; border-radius:4px; font-size:11px; }
  .invoice-info div { display:flex; justify-content:space-between; padding:2px 0; }
  table { width:100%; border-collapse:collapse; margin:8px 0; font-size:12px; }
  thead th {
    background:#222; color:#fff; padding:5px 3px;
    text-align:${alignS}; font-size:11px; font-weight:700;
  }
  thead th:last-child, thead th:nth-child(3) { text-align:${alignE}; }
  tbody tr:nth-child(even) { background:#f9f9f9; }
  .totals { margin-top:8px; border-top:2px solid #000; padding-top:8px; }
  .total-row { display:flex; justify-content:space-between; padding:3px 0; font-size:12px; }
  .total-row.grand {
    font-size:15px; font-weight:900;
    border-top:1px solid #000; border-bottom:1px solid #000;
    margin:5px 0; padding:5px 0;
    background:#C4922A; color:#fff;
    padding-left:6px; padding-right:6px;
  }
  .payment-info { margin-top:8px; padding:6px; border:1px dashed #ccc; border-radius:4px; font-size:11px; }
  .payment-info div { display:flex; justify-content:space-between; padding:2px 0; }
  .footer { text-align:center; margin-top:12px; padding-top:8px; border-top:1px dashed #aaa; font-size:11px; color:#666; line-height:1.6; }
  .logo-text { font-size:22px; font-weight:900; color:#C4922A; }
  @media print {
    body { width:80mm; }
    @page { margin:0; size:80mm auto; }
  }
</style>
</head>
<body>

<div class="header">
  <div class="logo-text">${sName}</div>
  ${sAddr ? `<div class="store-info">${sAddr}</div>` : ''}
  ${data.store_phone ? `<div class="store-info">📞 ${data.store_phone}</div>` : ''}
</div>

<div class="invoice-info">
  <div><span>${invLabel}:</span><span><strong>${data.invoice}</strong></span></div>
  <div><span>${cashierLabel}:</span><span>${data.cashier || ''}</span></div>
  <div><span>${lang==='ar'?'التاريخ':'Date'}:</span><span>${data.date || new Date().toLocaleString()}</span></div>
</div>

<table>
  <thead>
    <tr>
      <th>${itemLabel}</th>
      <th style="text-align:center">${qtyLabel}</th>
      <th style="text-align:${alignE}">${priceLabel}</th>
      <th style="text-align:${alignE}">${totalHLabel}</th>
    </tr>
  </thead>
  <tbody>${rows}</tbody>
</table>

<div class="totals">
  <div class="total-row"><span>${subLabel}:</span><span>${cur} ${fmt(data.subtotal)}</span></div>
  ${data.discount_total>0 ? `<div class="total-row" style="color:#C4922A"><span>${discLabel}:</span><span>- ${cur} ${fmt(data.discount_total)}</span></div>` : ''}
  ${data.tax>0 ? `<div class="total-row"><span>${taxLabel}:</span><span>${cur} ${fmt(data.tax)}</span></div>` : ''}
  <div class="total-row grand"><span>${totalLabel}:</span><span>${cur} ${fmt(data.total)}</span></div>
</div>

<div class="payment-info">
  <div><span>${payLabel}:</span><span>${payMethods[data.payment_method] || data.payment_method}</span></div>
  ${data.payment_method !== 'card' ? `
  <div><span>${cashLabel}:</span><span>${cur} ${fmt(data.cash_tendered)}</span></div>
  <div style="font-weight:700"><span>${changeLabel}:</span><span>${cur} ${fmt(data.change_given)}</span></div>
  ` : ''}
</div>

<div class="footer">
  <p>${footer}</p>
  <p style="margin-top:6px;font-size:10px;border-top:1px dashed #ccc;padding-top:6px">
    Powered & Developed by<br>
    <strong>Coda Agency for ICT Solutions</strong><br>
    +964 750 730 8005
  </p>
</div>

<script>
  window.onload = function() {
    setTimeout(function(){ window.print(); }, 500);
  };
<\/script>
</body>
</html>`;

  const win = window.open('', '_blank', 'width=420,height=650');
  win.document.open();
  win.document.write(html);
  win.document.close();
}

loadProducts(0);
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>