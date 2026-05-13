<?php
require_once __DIR__ . '/includes/config.php';
$page_title = LANG === 'ar' ? 'نقطة بيع الفئات' : 'Category POS';
$active_nav = 'catpos';
require_once __DIR__ . '/includes/layout.php';
$db       = DB::get();
$currency = get_setting('currency_symbol', 'IQD');
$cats     = $db->query("SELECT * FROM categories ORDER BY name_" . LANG . ", name_ar")->fetchAll();
?>
<style>
.cpos-wrap{display:grid;grid-template-columns:1fr 440px;gap:1rem;height:calc(100vh - 54px - 2rem)}
.cpos-left{display:flex;flex-direction:column;gap:.75rem;overflow:hidden}
.cpos-right{display:flex;flex-direction:column;gap:.75rem;overflow:hidden}
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem;overflow-y:auto;padding-right:2px}
.cat-card{border-radius:12px;padding:.8rem .6rem;cursor:pointer;text-align:center;border:2px solid transparent;transition:all .15s;background:var(--surface);box-shadow:0 1px 4px rgba(0,0,0,.06)}
.cat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.cat-card.selected{border-color:var(--brand);box-shadow:0 0 0 3px rgba(196,146,42,.2)}
.cat-dot{width:32px;height:32px;border-radius:50%;margin:0 auto .4rem;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff}
.cat-name{font-size:.78rem;font-weight:600;line-height:1.3}
.selected-cat-bar{background:var(--surface2);border-radius:10px;padding:.5rem .8rem;font-size:.82rem;display:flex;align-items:center;gap:.5rem;min-height:36px;border-left:3px solid transparent}
.selected-cat-bar span{font-weight:700}
.calc-panel{background:var(--surface);border-radius:14px;padding:.85rem;display:flex;flex-direction:column;gap:.5rem}
.calc-display{background:var(--surface2);border-radius:10px;padding:.5rem 1rem;text-align:right;font-family:var(--font-en)}
.calc-expr{font-size:.72rem;color:var(--muted);min-height:16px}
.calc-val{font-size:1.8rem;font-weight:800;color:var(--text);letter-spacing:.02em}
.numpad{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem}
.numpad-btn{padding:.7rem;font-size:1.2rem;font-weight:700;border-radius:10px;border:1px solid var(--border);background:var(--surface2);color:var(--text);cursor:pointer;transition:all .12s;font-family:var(--font-en)}
.numpad-btn:hover{background:var(--brand);color:#fff;border-color:var(--brand)}
.numpad-btn:active{transform:scale(.95)}
.numpad-btn.btn-zero{grid-column:span 2}
.numpad-btn.btn-back{background:rgba(220,38,38,.08);color:var(--danger);border-color:rgba(220,38,38,.2)}
.numpad-btn.btn-back:hover{background:var(--danger);color:#fff}
.numpad-btn.btn-x{grid-column:span 3;background:var(--brand);color:#fff;border-color:var(--brand);font-size:1.3rem;padding:.85rem;letter-spacing:2px}
.numpad-btn.btn-x:hover{filter:brightness(1.1)}
.scan-hint{font-size:.7rem;color:var(--muted);text-align:center}
.cart-wrap{background:var(--surface);border-radius:14px;padding:.85rem;display:flex;flex-direction:column;gap:.5rem;flex:1;overflow:hidden}
.cart-title{font-weight:700;font-size:.85rem;padding-bottom:.4rem;border-bottom:1px solid var(--border)}
.cart-list{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:.4rem}
.cart-empty{text-align:center;color:var(--muted);font-size:.82rem;padding:1.5rem 0}

/* ── Cart Item — compact single row ────────────────────── */
.cart-item{display:flex;align-items:center;gap:.35rem;padding:.35rem .45rem;border-radius:8px;background:var(--surface2);border:1px solid var(--border)}
.cart-item-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.cart-item-name{font-size:.78rem;font-weight:600;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cart-item-detail{font-size:.65rem;color:var(--muted);white-space:nowrap;font-family:var(--font-en);flex-shrink:0}
.cart-disc-input{width:36px;padding:.1rem .15rem;font-size:.7rem;border-radius:5px;border:1px solid var(--border);background:var(--bg);color:var(--text);text-align:center;font-family:var(--font-en);flex-shrink:0}
.cart-disc-input:focus{border-color:var(--brand);outline:none;box-shadow:0 0 0 1.5px rgba(196,146,42,.2)}
.cart-disc-pct{font-size:.63rem;color:var(--muted);flex-shrink:0}
.cart-item-prices{display:flex;flex-direction:column;align-items:flex-end;flex-shrink:0;line-height:1.2}
.cart-item-orig{font-size:.62rem;color:var(--muted);text-decoration:line-through;font-family:var(--font-en);white-space:nowrap}
.cart-item-final{font-size:.82rem;font-weight:700;color:var(--brand);white-space:nowrap;font-family:var(--font-en)}
.cart-item-del{color:var(--danger);background:none;border:none;cursor:pointer;padding:.1rem .28rem;border-radius:5px;font-size:.75rem;flex-shrink:0}
.cart-item-del:hover{background:rgba(220,38,38,.12)}

.totals-box{background:var(--surface);border-radius:14px;padding:.85rem}
.totals-row{display:flex;justify-content:space-between;font-size:.82rem;padding:.15rem 0}
.totals-row.total{font-weight:800;font-size:1.05rem;border-top:1px solid var(--border);margin-top:.3rem;padding-top:.5rem}
.discount-row{display:flex;gap:.4rem;align-items:center;margin:.4rem 0}
.discount-row input{width:65px;text-align:center;padding:.25rem .3rem;border-radius:6px;border:1px solid var(--border);font-size:.82rem;background:var(--bg);color:var(--text)}
.payment-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin:.5rem 0}
.pay-btn{padding:.45rem;border-radius:8px;border:2px solid var(--border);background:var(--surface2);font-size:.72rem;font-weight:600;cursor:pointer;text-align:center;transition:all .15s;color:var(--text)}
.pay-btn.active{border-color:var(--brand);background:rgba(196,146,42,.1);color:var(--brand)}
.cash-row{display:flex;gap:.5rem;align-items:center;margin:.3rem 0}
.cash-row input{flex:1;padding:.35rem .6rem;border-radius:8px;border:1px solid var(--border);font-size:.9rem;font-family:var(--font-en);background:var(--bg);color:var(--text)}
.change-badge{font-size:.8rem;font-weight:700;min-width:80px;text-align:right}
.btn-checkout{width:100%;padding:.85rem;border-radius:10px;border:none;background:var(--brand);color:#fff;font-size:1rem;font-weight:700;cursor:pointer;transition:all .15s}
.btn-checkout:hover{filter:brightness(1.1)}
.btn-checkout:disabled{opacity:.5;cursor:not-allowed}
.btn-clear-all{width:100%;padding:.45rem;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--danger);font-size:.8rem;font-weight:600;cursor:pointer;margin-top:.3rem}
</style>

<div class="cpos-wrap">
  <!-- ── LEFT: categories + numpad ───────────────────── -->
  <div class="cpos-left">
    <div class="selected-cat-bar" id="selCatBar">
      <i class="fa fa-tag" style="color:var(--brand)"></i>
      <span id="selCatName"><?= LANG==='ar'?'اختر فئة':'Select a category' ?></span>
    </div>
    <div class="cat-grid">
      <?php foreach ($cats as $c): ?>
      <div class="cat-card" id="cat-card-<?= $c['id'] ?>"
           onclick="selectCat(<?= $c['id'] ?>,'<?= addslashes(LANG==='ar'?$c['name_ar']:($c['name_en']?:$c['name_ar'])) ?>','<?= addslashes($c['name_ar']) ?>','<?= addslashes($c['name_en']?:$c['name_ar']) ?>','<?= htmlspecialchars($c['color']) ?>')">
        <div class="cat-dot" style="background:<?= htmlspecialchars($c['color']) ?>"><i class="fa fa-tag"></i></div>
        <div class="cat-name"><?= LANG==='ar' ? htmlspecialchars($c['name_ar']) : htmlspecialchars($c['name_en']?:$c['name_ar']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="calc-panel">
      <div class="calc-display">
        <div class="calc-expr" id="calcExpr"></div>
        <div class="calc-val" id="calcVal">0</div>
      </div>
      <div class="numpad">
        <button class="numpad-btn" onclick="padPress('7')">7</button>
        <button class="numpad-btn" onclick="padPress('8')">8</button>
        <button class="numpad-btn" onclick="padPress('9')">9</button>
        <button class="numpad-btn" onclick="padPress('4')">4</button>
        <button class="numpad-btn" onclick="padPress('5')">5</button>
        <button class="numpad-btn" onclick="padPress('6')">6</button>
        <button class="numpad-btn" onclick="padPress('1')">1</button>
        <button class="numpad-btn" onclick="padPress('2')">2</button>
        <button class="numpad-btn" onclick="padPress('3')">3</button>
        <button class="numpad-btn btn-zero" onclick="padPress('0')">0</button>
        <button class="numpad-btn btn-back" onclick="padBack()">&#9003;</button>
        <button class="numpad-btn btn-x" onclick="pressX()">&#10005; &nbsp; X</button>
      </div>
      <div class="scan-hint">
        <i class="fa fa-barcode"></i>
        <?= LANG==='ar' ? 'اكتب رقماً ← امسح باركود الفئة ← اضغط X' : 'Type number → Scan barcode → Press X' ?>
        &nbsp;|&nbsp;
        <a href="catpos_barcodes.php?lang=<?= LANG ?>" target="_blank" style="color:var(--brand)">
          <i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة':'Print Cards' ?>
        </a>
      </div>
    </div>
  </div>

  <!-- ── RIGHT: cart + totals ────────────────────────── -->
  <div class="cpos-right">
    <div class="cart-wrap">
      <div class="cart-title">
        <i class="fa fa-shopping-cart" style="color:var(--brand)"></i>
        <?= LANG==='ar'?'السلة':'Cart' ?>
        <span id="cartCount" style="color:var(--muted);font-weight:400;font-size:.78rem"></span>
      </div>
      <div class="cart-list" id="cartList">
        <div class="cart-empty" id="cartEmpty">
          <i class="fa fa-cart-shopping" style="font-size:1.5rem;opacity:.3"></i><br>
          <?= LANG==='ar'?'السلة فارغة':'Cart is empty' ?>
        </div>
      </div>
    </div>
    <div class="totals-box">
      <div class="discount-row">
        <span style="font-size:.78rem;color:var(--muted)"><?= LANG==='ar'?'خصم إجمالي:':'Overall Disc:' ?></span>
        <input type="number" id="discPct" placeholder="%" min="0" max="100" step="0.1" oninput="setDiscMode('pct')">
        <span style="font-size:.72rem;color:var(--muted)">%</span>
        <input type="number" id="discFixed" placeholder="<?= LANG==='ar'?'مبلغ':'Fixed' ?>" min="0" step="100" oninput="setDiscMode('fixed')">
        <span style="font-size:.72rem;color:var(--muted)"><?= $currency ?></span>
      </div>
      <div class="totals-row"><span><?= LANG==='ar'?'المجموع:':'Subtotal:' ?></span><span id="tSubtotal">0 <?= $currency ?></span></div>
      <div class="totals-row" id="discRow" style="color:var(--danger);display:none"><span><?= LANG==='ar'?'الخصم:':'Discount:' ?></span><span id="tDiscount"></span></div>
      <div class="totals-row total"><span><?= LANG==='ar'?'الإجمالي:':'Total:' ?></span><span id="tTotal">0 <?= $currency ?></span></div>
      <div class="payment-grid">
        <button class="pay-btn active" id="pay-cash" onclick="setPay('cash')"><i class="fa fa-money-bill-wave"></i><br><?= LANG==='ar'?'نقداً':'Cash' ?></button>
        <button class="pay-btn" id="pay-card" onclick="setPay('card')"><i class="fa fa-credit-card"></i><br><?= LANG==='ar'?'بطاقة':'Card' ?></button>
        <button class="pay-btn" id="pay-debt" onclick="setPay('debt')"><i class="fa fa-file-invoice"></i><br><?= LANG==='ar'?'دين':'Debt' ?></button>
      </div>
      <div class="cash-row" id="cashRow">
        <span style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= LANG==='ar'?'مدفوع:':'Cash:' ?></span>
        <input type="number" id="cashTendered" placeholder="0" min="0" step="1000" oninput="updateChange()">
        <span class="change-badge" id="changeBadge"></span>
      </div>
      <button class="btn-checkout" id="checkoutBtn" onclick="checkout()" disabled>
        <i class="fa fa-check-circle"></i> <?= LANG==='ar'?'إتمام البيع':'Checkout' ?>
      </button>
      <button class="btn-clear-all" onclick="clearAll()">
        <i class="fa fa-trash"></i> <?= LANG==='ar'?'مسح الكل':'Clear All' ?>
      </button>
    </div>
  </div>
</div>

<script>
const LANG    = '<?= LANG ?>';
const CUR     = '<?= $currency ?>';
const API_URL = '/bangeen_pos/api/catpos_checkout.php?lang=<?= LANG ?>';

let cart = [], selCat = null, discMode = 'pct', payMethod = 'cash';
let numBuffer = '', scannedVal = 0, _seq = 0;

/* ── Numpad ──────────────────────────────────────────────── */
function padPress(d) { if (numBuffer.length < 9) { numBuffer += d; scannedVal = 0; updateCalcDisplay(); } }
function padBack()   { numBuffer = numBuffer.slice(0,-1); scannedVal = 0; updateCalcDisplay(); }

function updateCalcDisplay() {
  const expr = document.getElementById('calcExpr');
  const val  = document.getElementById('calcVal');
  const num  = parseInt(numBuffer) || 0;
  if (scannedVal && num) {
    expr.textContent = num.toLocaleString() + ' \u00d7 ' + scannedVal.toLocaleString() + ' =';
    val.textContent  = (num * scannedVal).toLocaleString();
    val.style.color  = 'var(--brand)';
  } else if (num) {
    expr.textContent = num.toLocaleString() + ' \u00d7 1,000 =';
    val.textContent  = (num * 1000).toLocaleString();
    val.style.color  = 'var(--brand)';
  } else if (scannedVal) {
    expr.textContent = ''; val.textContent = scannedVal.toLocaleString(); val.style.color = 'var(--text)';
  } else {
    expr.textContent = ''; val.textContent = '0'; val.style.color = 'var(--text)';
  }
}

/* ── Barcode scanner ─────────────────────────────────────── */
let barcodeBuffer = '', barcodeTimer = null;
document.addEventListener('keydown', (e) => {
  if (e.target.tagName === 'INPUT' && ['cashTendered','discPct','discFixed'].includes(e.target.id)) return;
  if (e.target.classList.contains('cart-disc-input')) return;
  if (e.key === 'Enter') { if (barcodeBuffer.length > 2) onBarcodeScanned(barcodeBuffer); barcodeBuffer = ''; clearTimeout(barcodeTimer); return; }
  if (e.key === 'Backspace') { padBack(); return; }
  if (e.key === 'x' || e.key === 'X') { pressX(); return; }
  if (/^\d$/.test(e.key)) {
    barcodeBuffer += e.key;
    clearTimeout(barcodeTimer);
    barcodeTimer = setTimeout(() => { if (barcodeBuffer.length < 4) for (const d of barcodeBuffer) padPress(d); barcodeBuffer = ''; }, 100);
  }
});

function onBarcodeScanned(raw) {
  raw = raw.trim(); if (!raw) return;
  const m = raw.match(/^CATVAL_(\d+)_(\d+)$/);
  if (m) { const c = document.getElementById('cat-card-'+m[1]); if (c) c.click(); scannedVal = parseInt(m[2]); updateCalcDisplay(); return; }
  const n = parseInt(raw.replace(/\D/g,'')); if (!isNaN(n) && n > 0) { scannedVal = n; updateCalcDisplay(); }
}

/* ── Category selection ─────────────────────────────────── */
function selectCat(id, name, nameAr, nameEn, color) {
  if (selCat) document.getElementById('cat-card-'+selCat.id)?.classList.remove('selected');
  selCat = {id, name, nameAr, nameEn, color};
  document.getElementById('cat-card-'+id).classList.add('selected');
  document.getElementById('selCatName').textContent = name;
  document.getElementById('selCatBar').style.borderLeftColor = color;
}

/* ── Add item to cart ───────────────────────────────────── */
function pressX() {
  if (!selCat) { alert(LANG==='ar'?'الرجاء اختيار فئة أولاً':'Please select a category first'); return; }
  const num   = parseInt(numBuffer) || 1;
  const bval  = scannedVal || 1000;
  const amount = num * bval;
  if (amount <= 0) { alert(LANG==='ar'?'الرجاء إدخال رقم':'Please enter a number'); return; }

  const id = 'i' + (++_seq) + '_' + Date.now();
  cart.push({
    id,
    cat_id: selCat.id, cat_ar: selCat.nameAr, cat_en: selCat.nameEn,
    color: selCat.color,
    amount,          // original amount before any item-level discount
    disc_pct: 0,     // item discount %
    total: amount,   // amount after item discount
    detail: num.toLocaleString() + ' \u00d7 ' + bval.toLocaleString()
  });
  numBuffer = ''; scannedVal = 0;
  updateCalcDisplay(); renderCart(); updateTotals();
}

/* ── Per-item discount ─────────────────────────────────── */
function setItemDisc(id, rawPct) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.disc_pct = Math.min(100, Math.max(0, parseFloat(rawPct) || 0));
  item.total    = Math.round(item.amount * (1 - item.disc_pct / 100));

  // Update DOM in-place (avoids losing input focus)
  const origEl  = document.getElementById('orig-'  + id);
  const finalEl = document.getElementById('price-' + id);
  if (origEl)  { origEl.textContent  = item.amount.toLocaleString() + ' ' + CUR; origEl.style.display = item.disc_pct > 0 ? 'block' : 'none'; }
  if (finalEl) { finalEl.textContent = item.total.toLocaleString()  + ' ' + CUR; }
  updateTotals();
}

/* ── Render cart ────────────────────────────────────────── */
function renderCart() {
  const list  = document.getElementById('cartList');
  const empty = document.getElementById('cartEmpty');
  document.getElementById('cartCount').textContent = cart.length ? '('+cart.length+')' : '';
  if (!cart.length) {
    list.innerHTML = ''; list.appendChild(empty); empty.style.display = 'block';
    document.getElementById('checkoutBtn').disabled = true;
    return;
  }
  empty.style.display = 'none';
  const offLabel = LANG==='ar' ? '%' : '%';
  list.innerHTML = cart.map(item => {
    const name    = LANG==='ar' ? item.cat_ar : item.cat_en;
    const hasDisc = item.disc_pct > 0;
    // Single compact row: dot | name | detail | [disc%] | prices | X
    return '<div class="cart-item">' +
      '<div class="cart-item-dot" style="background:' + item.color + '"></div>' +
      '<div class="cart-item-name">' + name + '</div>' +
      '<div class="cart-item-detail">' + item.detail + '</div>' +
      '<input type="number" class="cart-disc-input" id="disc-' + item.id + '" min="0" max="100" step="0.1" placeholder="0" title="' + (LANG==='ar'?'خصم %':'Discount %') + '" value="' + (hasDisc ? item.disc_pct : '') + '" oninput="setItemDisc(\'' + item.id + '\', this.value)">' +
      '<span class="cart-disc-pct">' + offLabel + ' off</span>' +
      '<div class="cart-item-prices">' +
        '<div class="cart-item-orig" id="orig-' + item.id + '" style="display:' + (hasDisc?'block':'none') + '">' + item.amount.toLocaleString() + '</div>' +
        '<div class="cart-item-final" id="price-' + item.id + '">' + item.total.toLocaleString() + ' ' + CUR + '</div>' +
      '</div>' +
      '<button class="cart-item-del" onclick="removeItem(\'' + item.id + '\')"><i class="fa fa-times"></i></button>' +
    '</div>';
  }).join('');
  document.getElementById('checkoutBtn').disabled = false;
}

/* ── Remove item ────────────────────────────────────────── */
function removeItem(id) {
  cart = cart.filter(i => i.id !== id);
  renderCart(); updateTotals();
}

/* ── Financials helper ──────────────────────────────────── */
function getFinancials() {
  const originalSub   = cart.reduce((s, i) => s + i.amount, 0);
  const afterItemDisc = cart.reduce((s, i) => s + i.total,  0);
  const pct   = parseFloat(document.getElementById('discPct').value)   || 0;
  const fixed = parseFloat(document.getElementById('discFixed').value) || 0;
  const overallDisc = discMode === 'fixed' ? Math.min(fixed, afterItemDisc) : afterItemDisc * pct / 100;
  const finalTotal   = Math.max(0, afterItemDisc - overallDisc);
  const totalDiscount = (originalSub - afterItemDisc) + overallDisc;
  return { originalSub, afterItemDisc, overallDisc, finalTotal, totalDiscount };
}

/* ── Totals display ─────────────────────────────────────── */
function setDiscMode(m) { discMode = m; if (m==='pct') document.getElementById('discFixed').value=''; else document.getElementById('discPct').value=''; updateTotals(); }

function updateTotals() {
  const { originalSub, finalTotal, totalDiscount } = getFinancials();
  document.getElementById('tSubtotal').textContent = originalSub.toLocaleString() + ' ' + CUR;
  document.getElementById('tTotal').textContent    = finalTotal.toLocaleString()  + ' ' + CUR;
  const dr = document.getElementById('discRow');
  if (totalDiscount > 0) { dr.style.display='flex'; document.getElementById('tDiscount').textContent = '-' + Math.round(totalDiscount).toLocaleString() + ' ' + CUR; }
  else dr.style.display = 'none';
  updateChange();
}

/* ── Payment ────────────────────────────────────────────── */
function setPay(m) {
  payMethod = m; ['cash','card','debt'].forEach(x => document.getElementById('pay-'+x).classList.toggle('active', x===m));
  document.getElementById('cashRow').style.display = m==='cash' ? 'flex' : 'none'; updateChange();
}

function updateChange() {
  if (payMethod!=='cash') { document.getElementById('changeBadge').textContent=''; return; }
  const { finalTotal } = getFinancials();
  const cash = parseFloat(document.getElementById('cashTendered').value)||0;
  const ch = cash - finalTotal; const badge = document.getElementById('changeBadge');
  if (cash > 0) { badge.textContent = (ch>=0?'\u21a9 ':'\u21af ')+Math.abs(ch).toLocaleString()+' '+CUR; badge.style.color = ch>=0?'var(--success)':'var(--danger)'; }
  else badge.textContent = '';
}

/* ── Checkout ───────────────────────────────────────────── */
async function checkout() {
  if (!cart.length) return;
  const { originalSub, afterItemDisc, overallDisc, finalTotal } = getFinancials();
  const cash = parseFloat(document.getElementById('cashTendered').value)||0;
  if (payMethod==='cash' && cash < finalTotal) { alert(LANG==='ar'?'المبلغ المدفوع أقل من الإجمالي':'Cash tendered is less than total'); return; }
  const btn = document.getElementById('checkoutBtn');
  btn.disabled = true; btn.textContent = LANG==='ar'?'جارٍ الحفظ...':'Saving...';
  try {
    const r = await fetch(API_URL, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        cart: cart.map(i => ({ cat_id:i.cat_id, cat_ar:i.cat_ar, cat_en:i.cat_en, amount:i.amount, disc_pct:i.disc_pct, total:i.total })),
        subtotal:        originalSub,
        item_discount:   originalSub - afterItemDisc,
        overall_discount: overallDisc,
        discount_total:  (originalSub - afterItemDisc) + overallDisc,
        total:           finalTotal,
        payment_method:  payMethod,
        cash_tendered:   cash,
        change_given:    Math.max(0, cash - finalTotal)
      })
    });
    const d = await r.json();
    if (d.success) {
      // Build readable receipt per category
      let msg = (LANG==='ar' ? '\u2705 \u062a\u0645 \u0627\u0644\u0628\u064a\u0639!' : '\u2705 Sale done!') + '\n';
      msg += (LANG==='ar' ? '\u0641\u0627\u062a\u0648\u0631\u0629: ' : 'Invoice: ') + d.invoice + '\n';
      msg += '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n';
      d.items.forEach(it => {
        msg += it.name + ': ' + it.net.toLocaleString() + ' ' + CUR;
        if (it.disc_pct > 0) msg += '  (-' + it.disc_pct + '%)';
        msg += '\n';
      });
      msg += '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n';
      msg += (LANG==='ar' ? '\u0627\u0644\u0625\u062c\u0645\u0627\u0644\u064a: ' : 'Total: ') + finalTotal.toLocaleString() + ' ' + CUR;
      alert(msg);
      clearAll();
    } else {
      alert((LANG==='ar'?'\u062e\u0637\u0623: ':'Error: ') + (d.error||'Unknown'));
    }
  } catch(e) { alert(LANG==='ar'?'\u0641\u0634\u0644 \u0627\u0644\u0627\u062a\u0635\u0627\u0644':'Connection failed'); }
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check-circle"></i> ' + (LANG==='ar'?'\u0625\u062a\u0645\u0627\u0645 \u0627\u0644\u0628\u064a\u0639':'Checkout');
}

/* ── Clear all ──────────────────────────────────────────── */
function clearAll() {
  cart = []; numBuffer = ''; scannedVal = 0;
  document.getElementById('discPct').value = '';
  document.getElementById('discFixed').value = '';
  document.getElementById('cashTendered').value = '';
  updateCalcDisplay(); renderCart(); updateTotals();
  if (selCat) { document.getElementById('cat-card-'+selCat.id)?.classList.remove('selected'); selCat=null; document.getElementById('selCatName').textContent=LANG==='ar'?'\u0627\u062e\u062a\u0631 \u0641\u0626\u0629':'Select a category'; document.getElementById('selCatBar').style.borderLeftColor='transparent'; }
}

renderCart(); updateTotals(); setPay('cash');
</script>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>