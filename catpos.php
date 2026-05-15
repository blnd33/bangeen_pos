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
.cpos-wrap{display:grid;grid-template-columns:420px 1fr;gap:1rem;height:calc(100vh - 54px - 2rem);direction:ltr}
.cpos-left{display:flex;flex-direction:column;gap:.75rem;overflow-y:auto}
.cpos-right{display:flex;flex-direction:column;gap:.75rem;overflow:hidden}

/* Cart */
.cart-wrap{background:var(--surface);border-radius:14px;padding:.85rem;display:flex;flex-direction:column;gap:.5rem;flex:1;overflow:hidden}
.cart-title{font-weight:700;font-size:.85rem;padding-bottom:.4rem;border-bottom:1px solid var(--border);direction:<?= DIR ?>}
.cart-list{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:.4rem;min-height:80px}
.cart-empty{text-align:center;color:var(--muted);font-size:.82rem;padding:1.5rem 0;direction:<?= DIR ?>}

.cart-item{background:var(--surface2);border-radius:10px;padding:.5rem .7rem;direction:<?= DIR ?>}
.cart-item-row1{display:flex;align-items:center;gap:.5rem}
.cart-item-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.cart-item-name{flex:1;font-size:.82rem;font-weight:700}
.cart-item-price{font-size:.85rem;font-weight:800;color:var(--brand);white-space:nowrap;font-family:var(--font-en)}
.cart-item-del{color:var(--danger);background:none;border:none;cursor:pointer;padding:.2rem .4rem;border-radius:5px;font-size:.85rem;flex-shrink:0}
.cart-item-del:hover{background:rgba(220,38,38,.1)}
.cart-item-row2{display:flex;align-items:center;gap:.4rem;margin-top:.3rem;font-size:.75rem}
.cart-item-row2 label{color:var(--muted)}
.cart-item-row2 input{width:90px;padding:.2rem .35rem;border-radius:6px;border:1px solid var(--border);font-size:.78rem;background:var(--bg);color:var(--text);font-family:var(--font-en);text-align:center}
.cart-item-row2 .disc-apply{padding:.2rem .5rem;border-radius:6px;border:1px solid var(--brand);background:var(--brand-soft);color:var(--brand);cursor:pointer;font-size:.75rem;font-weight:700}
.cart-item-row2 .disc-apply:hover{background:var(--brand);color:#fff}
.cart-item-row2 .disc-result{color:var(--danger);font-family:var(--font-en);font-weight:700}

/* Totals */
.totals-box{background:var(--surface);border-radius:14px;padding:.85rem;direction:<?= DIR ?>}
.totals-row{display:flex;justify-content:space-between;font-size:.82rem;padding:.15rem 0}
.totals-row.grand{font-weight:800;font-size:1.1rem;border-top:1px solid var(--border);margin-top:.35rem;padding-top:.5rem;color:var(--brand)}
.disc-global{display:flex;gap:.4rem;align-items:center;margin:.4rem 0;font-size:.78rem;color:var(--muted)}
.disc-global input{width:80px;padding:.25rem .35rem;border-radius:6px;border:1px solid var(--border);font-size:.82rem;background:var(--bg);color:var(--text);font-family:var(--font-en);text-align:center}
.pay-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin:.5rem 0}
.pay-btn{padding:.45rem;border-radius:8px;border:2px solid var(--border);background:var(--surface2);font-size:.75rem;font-weight:700;cursor:pointer;text-align:center;transition:all .15s;color:var(--text)}
.pay-btn.active{border-color:var(--brand);background:rgba(196,146,42,.12);color:var(--brand)}
.cash-row{display:flex;gap:.5rem;align-items:center;margin:.3rem 0}
.cash-row label{font-size:.78rem;color:var(--muted);white-space:nowrap}
.cash-row input{flex:1;padding:.35rem .6rem;border-radius:8px;border:1px solid var(--border);font-size:.9rem;font-family:var(--font-en);background:var(--bg);color:var(--text)}
.change-badge{font-size:.82rem;font-weight:800;white-space:nowrap;font-family:var(--font-en)}
.btn-checkout{width:100%;padding:.9rem;border-radius:10px;border:none;background:var(--brand);color:#fff;font-size:1rem;font-weight:800;cursor:pointer;margin-top:.4rem}
.btn-checkout:hover{filter:brightness(1.1)}
.btn-checkout:disabled{opacity:.45;cursor:not-allowed}
.btn-clear-all{width:100%;padding:.45rem;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--danger);font-size:.8rem;font-weight:700;cursor:pointer;margin-top:.3rem}

/* Right: categories + numpad */
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:.5rem;overflow-y:auto;padding-bottom:2px}
.cat-card{border-radius:12px;padding:.75rem .5rem;cursor:pointer;text-align:center;border:2px solid transparent;transition:all .15s;background:var(--surface);box-shadow:0 1px 4px rgba(0,0,0,.06);user-select:none}
.cat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.cat-card.selected{border-color:var(--brand);box-shadow:0 0 0 3px rgba(196,146,42,.2)}
.cat-dot{width:30px;height:30px;border-radius:50%;margin:0 auto .35rem;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff}
.cat-name{font-size:.75rem;font-weight:700;line-height:1.3;direction:<?= DIR ?>}
.sel-bar{background:var(--surface2);border-radius:10px;padding:.4rem .8rem;font-size:.8rem;display:flex;align-items:center;gap:.5rem;border-left:3px solid transparent;direction:<?= DIR ?>}
.sel-bar span{font-weight:700}

/* Numpad */
.numpad-wrap{background:var(--surface);border-radius:14px;padding:.8rem;display:flex;flex-direction:column;gap:.5rem}
.calc-disp{background:var(--surface2);border-radius:10px;padding:.4rem 1rem;text-align:right;font-family:var(--font-en)}
.calc-expr{font-size:.7rem;color:var(--muted);min-height:15px}
.calc-val{font-size:1.7rem;font-weight:800;color:var(--text)}
.numpad{display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem}
.np-btn{padding:.65rem;font-size:1.15rem;font-weight:700;border-radius:10px;border:1px solid var(--border);background:var(--surface2);color:var(--text);cursor:pointer;transition:all .12s;font-family:var(--font-en);user-select:none}
.np-btn:hover{background:var(--brand);color:#fff;border-color:var(--brand)}
.np-btn:active{transform:scale(.94)}
.np-btn.span2{grid-column:span 2}
.np-btn.back{background:rgba(220,38,38,.07);color:var(--danger);border-color:rgba(220,38,38,.2)}
.np-btn.back:hover{background:var(--danger);color:#fff}
.np-btn.xbtn{grid-column:span 3;background:var(--brand);color:#fff;border-color:var(--brand);font-size:1.2rem;padding:.8rem;font-weight:900;letter-spacing:1px}
.np-btn.xbtn:hover{filter:brightness(1.1)}
.scan-hint{font-size:.7rem;color:var(--muted);text-align:center;direction:<?= DIR ?>}
</style>

<div class="cpos-wrap">

  <!-- LEFT: Cart + Totals -->
  <div class="cpos-left">
    <div class="cart-wrap">
      <div class="cart-title">
        <i class="fa fa-shopping-cart" style="color:var(--brand)"></i>
        <?= LANG==='ar'?'السلة':'Cart' ?>
        <span id="cartCount" style="color:var(--muted);font-weight:400;font-size:.75rem"></span>
      </div>
      <div class="cart-list" id="cartList">
        <div class="cart-empty" id="cartEmpty">
          <i class="fa fa-cart-shopping" style="font-size:1.5rem;opacity:.25"></i><br>
          <?= LANG==='ar'?'السلة فارغة':'Cart is empty' ?>
        </div>
      </div>
    </div>

    <div class="totals-box">
      <!-- Global discount -->
      <div class="disc-global">
        <label><?= LANG==='ar'?'خصم إجمالي:':'Global Disc:' ?></label>
        <input type="number" id="discFixed" placeholder="0" min="0" step="500"
               oninput="updateTotals()" title="<?= LANG==='ar'?'خصم بالمبلغ':'Fixed amount discount' ?>">
        <span><?= $currency ?></span>
        <input type="number" id="discPct" placeholder="%" min="0" max="100" step="1"
               oninput="updateTotals()" title="<?= LANG==='ar'?'خصم بالنسبة':'Percent discount' ?>">
        <span>%</span>
      </div>

      <div class="totals-row"><span><?= LANG==='ar'?'المجموع:':'Subtotal:' ?></span><span id="tSub" style="font-family:var(--font-en)">0 <?= $currency ?></span></div>
      <div class="totals-row" id="discRow" style="display:none;color:var(--danger)"><span><?= LANG==='ar'?'الخصم:':'Discount:' ?></span><span id="tDisc" style="font-family:var(--font-en)"></span></div>
      <div class="totals-row grand"><span><?= LANG==='ar'?'الإجمالي:':'Total:' ?></span><span id="tTotal" style="font-family:var(--font-en)">0 <?= $currency ?></span></div>

      <!-- Payment -->
      <div class="pay-grid">
        <button class="pay-btn active" id="pay-cash" onclick="setPay('cash')">
          <i class="fa fa-money-bill-wave"></i><br><?= LANG==='ar'?'نقداً':'Cash' ?>
        </button>
        <button class="pay-btn" id="pay-card" onclick="setPay('card')">
          <i class="fa fa-credit-card"></i><br><?= LANG==='ar'?'بطاقة':'Card' ?>
        </button>
        <button class="pay-btn" id="pay-debt" onclick="setPay('debt')">
          <i class="fa fa-file-invoice"></i><br><?= LANG==='ar'?'دين':'Debt' ?>
        </button>
      </div>

      <div class="cash-row" id="cashRow">
        <label><?= LANG==='ar'?'مدفوع:':'Cash:' ?></label>
        <input type="number" id="cashIn" placeholder="0" min="0" step="1000" oninput="updateChange()">
        <span class="change-badge" id="changeBadge"></span>
      </div>

      <button class="btn-checkout" id="checkoutBtn" onclick="doCheckout()" disabled>
        <i class="fa fa-check-circle"></i> <?= LANG==='ar'?'إتمام البيع':'Checkout' ?>
      </button>
      <button class="btn-clear-all" onclick="clearAll()">
        <i class="fa fa-trash"></i> <?= LANG==='ar'?'مسح الكل':'Clear All' ?>
      </button>
    </div>
  </div>

  <!-- RIGHT: Categories + Numpad -->
  <div class="cpos-right">
    <!-- Selected category bar -->
    <div class="sel-bar" id="selBar">
      <i class="fa fa-tag" style="color:var(--brand)"></i>
      <span id="selName"><?= LANG==='ar'?'اختر فئة':'Select a category' ?></span>
    </div>

    <!-- Category grid -->
    <div class="cat-grid">
      <?php foreach ($cats as $c):
        $dname = LANG==='ar' ? $c['name_ar'] : ($c['name_en']?:$c['name_ar']);
      ?>
      <div class="cat-card"
           id="cat-<?= $c['id'] ?>"
           data-id="<?= $c['id'] ?>"
           data-name="<?= htmlspecialchars($dname, ENT_QUOTES, 'UTF-8') ?>"
           data-ar="<?= htmlspecialchars($c['name_ar'], ENT_QUOTES, 'UTF-8') ?>"
           data-en="<?= htmlspecialchars($c['name_en']?:$c['name_ar'], ENT_QUOTES, 'UTF-8') ?>"
           data-color="<?= htmlspecialchars($c['color'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="cat-dot" style="background:<?= htmlspecialchars($c['color']) ?>">
          <i class="fa fa-tag"></i>
        </div>
        <div class="cat-name"><?= htmlspecialchars($dname) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Numpad -->
    <div class="numpad-wrap">
      <div class="calc-disp">
        <div class="calc-expr" id="calcExpr"></div>
        <div class="calc-val" id="calcVal">0</div>
      </div>
      <div class="numpad">
        <button class="np-btn" onclick="np('7')">7</button>
        <button class="np-btn" onclick="np('8')">8</button>
        <button class="np-btn" onclick="np('9')">9</button>
        <button class="np-btn" onclick="np('4')">4</button>
        <button class="np-btn" onclick="np('5')">5</button>
        <button class="np-btn" onclick="np('6')">6</button>
        <button class="np-btn" onclick="np('1')">1</button>
        <button class="np-btn" onclick="np('2')">2</button>
        <button class="np-btn" onclick="np('3')">3</button>
        <button class="np-btn span2" onclick="np('0')">0</button>
        <button class="np-btn back" onclick="npBack()">&#9003;</button>
        <button class="np-btn xbtn" onclick="pressX()">PRESS &nbsp; X</button>
      </div>
      <div class="scan-hint">
        <i class="fa fa-barcode"></i>
        <?= LANG==='ar'?'رقم ← امسح باركود الفئة ← اضغط X':'Number → Scan barcode → Press X' ?>
        &nbsp;|&nbsp;
        <a href="catpos_barcodes.php?lang=<?= LANG ?>" target="_blank" style="color:var(--brand)">
          <i class="fa fa-print"></i> <?= LANG==='ar'?'طباعة':'Print Cards' ?>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
const LANG    = '<?= LANG ?>';
const CUR     = '<?= $currency ?>';
const API     = '/bangeen_pos/api/catpos_checkout.php?lang=<?= LANG ?>';

// ── State ─────────────────────────────────────────────────
let cart      = [];   // {cat_id, cat_ar, cat_en, color, gross, discount, net, detail}
let selCat    = null;
let numBuf    = '';
let scanVal   = 0;
let payMethod = 'cash';

// ── Category cards — attach via JS (avoids onclick Arabic issues) ──
document.querySelectorAll('.cat-card').forEach(el => {
  el.addEventListener('click', () => {
    const id    = parseInt(el.dataset.id);
    const name  = el.dataset.name;
    const ar    = el.dataset.ar;
    const en    = el.dataset.en;
    const color = el.dataset.color;
    if (selCat) document.getElementById('cat-'+selCat.id)?.classList.remove('selected');
    selCat = {id, name, ar, en, color};
    el.classList.add('selected');
    document.getElementById('selName').textContent = name;
    const bar = document.getElementById('selBar');
    bar.style.borderLeftColor = color;
  });
});

// ── Numpad ────────────────────────────────────────────────
function np(d) {
  if (numBuf.length >= 9) return;
  numBuf += d;
  scanVal = 0;
  refreshCalc();
}
function npBack() {
  numBuf = numBuf.slice(0,-1);
  scanVal = 0;
  refreshCalc();
}
function refreshCalc() {
  const expr = document.getElementById('calcExpr');
  const val  = document.getElementById('calcVal');
  const num  = parseInt(numBuf) || 0;
  if (scanVal && num) {
    expr.textContent = num.toLocaleString() + ' × ' + scanVal.toLocaleString() + ' =';
    val.textContent  = (num * scanVal).toLocaleString();
    val.style.color  = 'var(--brand)';
  } else if (num) {
    expr.textContent = '';
    val.textContent  = num.toLocaleString();
    val.style.color  = 'var(--text)';
  } else if (scanVal) {
    expr.textContent = '';
    val.textContent  = scanVal.toLocaleString();
    val.style.color  = 'var(--text)';
  } else {
    expr.textContent = '';
    val.textContent  = '0';
    val.style.color  = 'var(--text)';
  }
}

// ── Keyboard / barcode ────────────────────────────────────
let scanBuf = '', scanTimer = null;
document.addEventListener('keydown', e => {
  const tag = e.target.tagName;
  const tid = e.target.id || '';
  if (tag === 'INPUT' && !['cashIn','discFixed','discPct'].includes(tid)) return;
  if (tag === 'BUTTON') return;

  if (e.key === 'Enter') {
    if (scanBuf.length > 2) handleScan(scanBuf);
    scanBuf = ''; clearTimeout(scanTimer); return;
  }
  if (e.key === 'Backspace' && tag !== 'INPUT') { npBack(); return; }
  if ((e.key === 'x' || e.key === 'X') && tag !== 'INPUT') { pressX(); return; }
  if (/^\d$/.test(e.key) && tag !== 'INPUT') {
    scanBuf += e.key;
    clearTimeout(scanTimer);
    scanTimer = setTimeout(() => {
      if (scanBuf.length <= 3) { for (const d of scanBuf) np(d); }
      else handleScan(scanBuf);
      scanBuf = '';
    }, 80);
  }
});

function handleScan(raw) {
  raw = raw.trim();
  const m = raw.match(/^CATVAL_(\d+)_(\d+)$/);
  if (m) {
    const card = document.getElementById('cat-'+m[1]);
    if (card) card.click();
    scanVal = parseInt(m[2]);
    refreshCalc();
    return;
  }
  const n = parseInt(raw.replace(/\D/g,''));
  if (!isNaN(n) && n > 0) { scanVal = n; refreshCalc(); }
}

// ── Press X — add to cart ─────────────────────────────────
function pressX() {
  if (!selCat) {
    alert(LANG==='ar'?'الرجاء اختيار فئة أولاً':'Please select a category first');
    return;
  }
  const num   = parseInt(numBuf) || 1;
  const bval  = scanVal || 1000;
  const gross = num * bval;
  if (gross <= 0) {
    alert(LANG==='ar'?'الرجاء إدخال رقم':'Please enter a number');
    return;
  }
  const ex = cart.find(i => i.cat_id === selCat.id);
  if (ex) {
    ex.gross += gross;
    ex.net    = ex.gross - (ex.discount || 0);
    ex.detail = (LANG==='ar'?'مضاف ':'Added ') + gross.toLocaleString() + ' ' + CUR;
  } else {
    cart.push({
      cat_id: selCat.id, cat_ar: selCat.ar, cat_en: selCat.en,
      color: selCat.color, gross, discount: 0, net: gross,
      detail: num.toLocaleString() + ' × ' + bval.toLocaleString() + ' = ' + (num*bval).toLocaleString() + ' ' + CUR
    });
  }
  numBuf = ''; scanVal = 0;
  refreshCalc();
  renderCart();
  updateTotals();
}

// ── Cart rendering ────────────────────────────────────────
function renderCart() {
  const list  = document.getElementById('cartList');
  const empty = document.getElementById('cartEmpty');
  document.getElementById('cartCount').textContent = cart.length ? '('+cart.length+')' : '';

  if (!cart.length) {
    list.innerHTML = `<div class="cart-empty"><i class="fa fa-cart-shopping" style="font-size:1.5rem;opacity:.25"></i><br>${LANG==='ar'?'السلة فارغة':'Cart is empty'}</div>`;
    document.getElementById('checkoutBtn').disabled = true;
    return;
  }
  list.innerHTML = cart.map((item, i) => {
    const name = LANG==='ar' ? item.cat_ar : item.cat_en;
    const net  = item.net.toLocaleString();
    const discShow = item.discount > 0
      ? `<span class="disc-result">-${item.discount.toLocaleString()} ${CUR} → ${net} ${CUR}</span>`
      : '';
    return `
    <div class="cart-item">
      <div class="cart-item-row1">
        <div class="cart-item-dot" style="background:${item.color}"></div>
        <div class="cart-item-name">${name}</div>
        <div class="cart-item-price">${item.gross.toLocaleString()} ${CUR}</div>
        <button class="cart-item-del" onclick="removeItem(${i})"><i class="fa fa-times"></i></button>
      </div>
      <div class="cart-item-row2">
        <label>${LANG==='ar'?'خصم:':'Disc:'}</label>
        <input type="number" id="di-${i}" min="0" max="${item.gross}"
               value="${item.discount||''}" placeholder="0"
               onkeydown="if(event.key==='Enter'){applyDisc(${i});event.preventDefault();}">
        <span>${CUR}</span>
        <button class="disc-apply" onclick="applyDisc(${i})">${LANG==='ar'?'تطبيق':'Apply'}</button>
        ${discShow}
      </div>
    </div>`;
  }).join('');
  document.getElementById('checkoutBtn').disabled = false;
}

function removeItem(i) {
  cart.splice(i, 1);
  renderCart();
  updateTotals();
}

function applyDisc(i) {
  const inp = document.getElementById('di-'+i);
  const d   = parseFloat(inp.value) || 0;
  cart[i].discount = Math.min(d, cart[i].gross);
  cart[i].net      = cart[i].gross - cart[i].discount;
  renderCart();
  updateTotals();
}

// ── Totals ────────────────────────────────────────────────
function calcTotals() {
  const gross    = cart.reduce((s,i) => s + i.gross, 0);
  const itemDisc = cart.reduce((s,i) => s + (i.discount||0), 0);
  const netAfterItems = gross - itemDisc;
  const fixedDisc = parseFloat(document.getElementById('discFixed').value) || 0;
  const pctDisc   = parseFloat(document.getElementById('discPct').value) || 0;
  const extraDisc = Math.min(fixedDisc + netAfterItems * pctDisc / 100, netAfterItems);
  const totalDisc = itemDisc + extraDisc;
  const total     = Math.max(0, gross - totalDisc);
  return {gross, itemDisc, extraDisc, totalDisc, total};
}

function updateTotals() {
  const {gross, totalDisc, total} = calcTotals();
  document.getElementById('tSub').textContent   = gross.toLocaleString() + ' ' + CUR;
  document.getElementById('tTotal').textContent = total.toLocaleString() + ' ' + CUR;
  const dr = document.getElementById('discRow');
  if (totalDisc > 0) {
    dr.style.display = 'flex';
    document.getElementById('tDisc').textContent = '-' + totalDisc.toLocaleString() + ' ' + CUR;
  } else {
    dr.style.display = 'none';
  }
  updateChange();
}

// ── Payment ───────────────────────────────────────────────
function setPay(m) {
  payMethod = m;
  ['cash','card','debt'].forEach(x =>
    document.getElementById('pay-'+x).classList.toggle('active', x === m)
  );
  document.getElementById('cashRow').style.display = m === 'cash' ? 'flex' : 'none';
  updateChange();
}

function updateChange() {
  const badge = document.getElementById('changeBadge');
  if (payMethod !== 'cash') { badge.textContent = ''; return; }
  const {total} = calcTotals();
  const cash  = parseFloat(document.getElementById('cashIn').value) || 0;
  const change = cash - total;
  if (cash > 0 && total > 0) {
    badge.textContent = (change >= 0 ? '↩ ' : '↯ ') + Math.abs(change).toLocaleString() + ' ' + CUR;
    badge.style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
  } else {
    badge.textContent = '';
  }
}

// ── Checkout ──────────────────────────────────────────────
async function doCheckout() {
  if (!cart.length) return;
  const {gross, totalDisc, total} = calcTotals();
  const cash = parseFloat(document.getElementById('cashIn').value) || 0;
  // No cash validation — cashier handles payment manually
  const btn = document.getElementById('checkoutBtn');
  btn.disabled = true;
  btn.textContent = LANG==='ar' ? 'جارٍ الحفظ...' : 'Saving...';
  try {
    const payload = {
      cart,
      subtotal:       gross,
      discount_total: totalDisc,
      total:          total,
      payment_method: payMethod,
      cash_tendered:  cash,
      change_given:   Math.max(0, cash - total)
    };
    const r = await fetch(API, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); }
    catch(e) { alert('Server error: ' + text.substring(0,300)); btn.disabled=false; btn.innerHTML='<i class="fa fa-check-circle"></i> '+(LANG==='ar'?'إتمام البيع':'Checkout'); return; }
    if (d.success) {
      alert((LANG==='ar'?'✅ تم البيع!\nفاتورة: ':'✅ Sale done!\nInvoice: ')
        + d.invoice + '\n'
        + (LANG==='ar'?'الإجمالي: ':'Total: ')
        + total.toLocaleString() + ' ' + CUR);
      clearAll();
    } else {
      alert((LANG==='ar'?'خطأ: ':'Error: ') + (d.error||'Unknown error') + '\n\nCart: ' + JSON.stringify(cart.map(i=>({id:i.cat_id,gross:i.gross,net:i.net}))));
    }
  } catch(e) {
    alert(LANG==='ar'?'فشل الاتصال بالخادم':'Server connection failed');
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check-circle"></i> ' + (LANG==='ar'?'إتمام البيع':'Checkout');
}

// ── Clear ─────────────────────────────────────────────────
function clearAll() {
  cart = []; numBuf = ''; scanVal = 0; payMethod = 'cash';
  document.getElementById('discFixed').value = '';
  document.getElementById('discPct').value   = '';
  document.getElementById('cashIn').value    = '';
  refreshCalc();
  renderCart();
  updateTotals();
  setPay('cash');
  document.getElementById('cashIn').value = '';
  document.getElementById('changeBadge').textContent = '';
  if (selCat) {
    document.getElementById('cat-'+selCat.id)?.classList.remove('selected');
    selCat = null;
    document.getElementById('selName').textContent = LANG==='ar'?'اختر فئة':'Select a category';
    document.getElementById('selBar').style.borderLeftColor = 'transparent';
  }
}

// Init
renderCart();
updateTotals();
setPay('cash');
</script>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>