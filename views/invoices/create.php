<?php
$error = $error ?? null;
$isEdit = isset($invoice) && is_array($invoice) && !empty($invoice['id']);
$old = is_array($old ?? null) ? $old : [];
$existingItems = [];
foreach (($items ?? []) as $item) {
    $existingItems[] = [
        'id' => (int)($item['product_id'] ?? 0),
        'name' => (string)($item['product_name'] ?? ''),
        'unit' => (string)($item['unit'] ?? 'шт'),
        'price' => (float)($item['unit_price'] ?? 0),
        'qty' => (float)($item['quantity'] ?? 0),
        'discount' => (float)($item['discount'] ?? 0),
        'stock' => (float)($item['current_stock'] ?? 0) + (float)($item['quantity'] ?? 0),
    ];
}
$oldItems = json_decode((string)($old['items_json'] ?? '[]'), true);
if (is_array($oldItems) && !empty($oldItems)) {
    $existingItems = [];
    foreach ($oldItems as $item) {
        $existingItems[] = [
            'id' => (int)($item['product_id'] ?? 0),
            'name' => (string)($item['product_name'] ?? ''),
            'unit' => (string)($item['unit'] ?? 'шт'),
            'price' => (float)($item['unit_price'] ?? 0),
            'qty' => (float)($item['quantity'] ?? 0),
            'discount' => (float)($item['discount'] ?? 0),
            'stock' => (float)($item['stock'] ?? 0),
        ];
    }
}
$initialClientId = (int)($old['client_id'] ?? ($invoice['client_id'] ?? $preClientId ?? 0));
$initialDate = (string)($old['invoice_date'] ?? ($invoice['invoice_date'] ?? date('Y-m-d')));
$initialDiscount = (float)($old['discount'] ?? ($invoice['discount'] ?? 0));
$initialPaid = (float)($old['paid_amount'] ?? ($invoice['paid_amount'] ?? 0));
$initialMethod = (string)($old['payment_method'] ?? 'cash');
$initialNotes = (string)($old['notes'] ?? ($invoice['notes'] ?? ''));
$initialStatus = (string)($old['status'] ?? ($invoice['status'] ?? 'draft'));
$editId = $isEdit ? (int)$invoice['id'] : 0;
$submitLabel = $isEdit ? 'Сохранить накладную' : 'Создать накладную';
?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="invoice-create">
  <div class="wiz-track" id="wizTrack">
    <div class="wiz-item active" data-step="1"><span class="wiz-dot">1</span><span class="wiz-label">Клиент</span></div>
    <div class="wiz-item" data-step="2"><span class="wiz-dot">2</span><span class="wiz-label">Товары</span></div>
    <div class="wiz-item" data-step="3"><span class="wiz-dot">3</span><span class="wiz-label">Оплата</span></div>
    <div class="wiz-item" data-step="4"><span class="wiz-dot">4</span><span class="wiz-label">Готово</span></div>
  </div>

  <!-- STEP 1: Client -->
  <div id="step1" class="wizard-panel">
    <div class="card">
      <div class="section-heading">
        <h3>Клиент</h3>
        <button type="button" class="link-btn" onclick="openClientModal()">+ Новый клиент</button>
      </div>
      <div class="form-group">
        <label class="form-label">Дата накладной</label>
        <input type="date" id="invoiceDate" class="form-control" value="<?= htmlspecialchars($initialDate) ?>">
      </div>
      <div class="search-bar" style="margin-bottom:12px;">
        <span class="search-icon">🔍</span>
        <input type="text" id="clientSearch" placeholder="Поиск клиента по имени или телефону..." autocomplete="off">
      </div>
      <div id="clientResults"></div>
      <div id="selectedClient" class="selected-box" style="display:none;">
        <div>
          <div id="selectedClientName" class="fw-700"></div>
          <div id="selectedClientPhone" class="muted"></div>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="clearClient()">Сбросить</button>
      </div>
    </div>
    <button type="button" class="btn btn-primary btn-full wiz-next" onclick="goStep(2)">Далее →</button>
  </div>

  <!-- STEP 2: Products -->
  <div id="step2" class="wizard-panel" style="display:none;">
    <div class="section-heading">
      <h3>Добавьте товары</h3>
      <button type="button" class="link-btn" id="toggleProductSearch" onclick="toggleProductPicker()">+ Добавить товар</button>
    </div>

    <div id="productPicker" class="card" style="display:none;">
      <div class="search-bar" style="margin-bottom:8px;">
        <span class="search-icon">🔍</span>
        <input type="text" id="productSearch" placeholder="Имя, SKU или штрихкод..." autocomplete="off" inputmode="search">
      </div>
      <div id="productResults"></div>
    </div>

    <div id="cartItems" class="cart-list"></div>
    <div id="cartEmpty" class="empty-state compact-empty">
      <div class="empty-text">Товары пока не добавлены</div>
    </div>

    <div class="card summary-card">
      <div class="card-row"><span id="cartCountLabel">Товаров (0)</span><strong id="cartQtyLabel">0 шт.</strong></div>
      <div class="card-row"><span>Сумма</span><strong id="totalLabel">0.00 с.</strong></div>
    </div>

    <div class="actions-row">
      <button type="button" class="btn btn-secondary" onclick="goStep(1)">Назад</button>
      <button type="button" class="btn btn-primary" onclick="goStep(3)">Далее →</button>
    </div>
  </div>

  <!-- STEP 3: Payment -->
  <div id="step3" class="wizard-panel" style="display:none;">
    <div class="card">
      <label class="form-label">Оплата</label>
      <div class="pay-pills" id="payPills">
        <button type="button" class="pay-pill active" data-mode="cash" onclick="setPayMode('cash')">Наличные</button>
        <button type="button" class="pay-pill" data-mode="card" onclick="setPayMode('card')">Карта</button>
        <button type="button" class="pay-pill" data-mode="bank" onclick="setPayMode('bank')">Банк</button>
        <button type="button" class="pay-pill" data-mode="transfer" onclick="setPayMode('transfer')">Перевод</button>
        <button type="button" class="pay-pill" data-mode="partial" onclick="setPayMode('partial')">Частично</button>
        <button type="button" class="pay-pill" data-mode="debt" onclick="setPayMode('debt')">Долг</button>
      </div>

      <div class="form-group">
        <label class="form-label">Скидка по накладной (сумма)</label>
        <input type="text" id="invoiceDiscount" class="form-control num-dot" inputmode="decimal" autocomplete="off" value="<?= htmlspecialchars(number_format($initialDiscount, 2, '.', '')) ?>" oninput="onDiscountInput()">
      </div>

      <div class="pay-row">
        <span class="muted">Сумма к оплате</span>
        <strong id="payTotalLabel" class="pay-big">0.00 с.</strong>
      </div>

      <div class="form-group" id="receivedGroup">
        <label class="form-label">Получено</label>
        <input type="text" id="paidAmount" class="form-control num-dot" inputmode="decimal" autocomplete="off" value="<?= htmlspecialchars(number_format($initialPaid, 2, '.', '')) ?>" oninput="onPaidInput()">
        <div class="quick-add">
          <button type="button" class="quick-btn" onclick="addQuick(100)">+100</button>
          <button type="button" class="quick-btn" onclick="addQuick(200)">+200</button>
          <button type="button" class="quick-btn" onclick="addQuick(500)">+500</button>
          <button type="button" class="quick-btn" onclick="addQuick(1000)">+1000</button>
        </div>
      </div>

      <div class="pay-change" id="changeBox">
        <span id="payExtraLabel">Сдача</span>
        <strong id="payChangeLabel">0.00 с.</strong>
      </div>

      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Примечание</label>
        <textarea id="notes" class="form-control" rows="2" placeholder="Напишите примечание..."><?= htmlspecialchars($initialNotes) ?></textarea>
      </div>

      <input type="hidden" id="paymentMethod" value="<?= htmlspecialchars($initialMethod) ?>">
    </div>

    <div class="actions-row">
      <button type="button" class="btn btn-secondary" onclick="goStep(2)">Назад</button>
      <button type="button" class="btn btn-primary" id="submitBtn" onclick="finishInvoice()"><?= $isEdit ? 'Сохранить →' : 'Далее →' ?></button>
    </div>
  </div>

  <!-- STEP 4: Done -->
  <div id="step4" class="wizard-panel" style="display:none;">
    <div class="done-card">
      <div class="done-check">✓</div>
      <h2 id="doneTitle">Накладная создана!</h2>
      <div class="muted" id="doneNumber"></div>
      <div class="muted" id="doneDate"></div>
      <a href="#" class="btn btn-primary btn-full" id="doneViewBtn" style="margin-top:20px;">Посмотреть накладную</a>
      <div class="done-actions">
        <a href="#" class="done-tile" id="doneShareBtn">
          <span class="done-tile-icon">📄</span>
          <span>Отправить клиенту</span>
        </a>
        <a href="/invoices/create" class="done-tile">
          <span class="done-tile-icon">➕</span>
          <span>Создать ещё одну</span>
        </a>
      </div>
      <a href="/" class="btn btn-secondary btn-full" style="margin-top:12px;">На главную</a>
    </div>
  </div>
</div>

<div id="clientModal" class="simple-modal" style="display:none;">
  <div class="simple-modal-backdrop" onclick="closeClientModal()"></div>
  <div class="simple-modal-card">
    <div class="section-heading">
      <h3>Новый клиент</h3>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeClientModal()">Закрыть</button>
    </div>
    <div class="form-group">
      <label class="form-label">Имя</label>
      <input type="text" id="newClientName" class="form-control">
    </div>
    <div class="form-group">
      <label class="form-label">Телефон</label>
      <input type="text" id="newClientPhone" class="form-control" inputmode="tel">
    </div>
    <div class="form-group">
      <label class="form-label">Адрес</label>
      <textarea id="newClientAddress" class="form-control" rows="2"></textarea>
    </div>
    <button type="button" class="btn btn-primary btn-full" onclick="createClientInline()">Сохранить клиента</button>
  </div>
</div>

<style>
.invoice-create { width:100%; max-width:100%; overflow-x:hidden; padding-bottom:8px; }
.wiz-track {
  display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:4px;
  margin-bottom:16px; position:relative;
}
.wiz-item { display:flex; flex-direction:column; align-items:center; gap:6px; text-align:center; }
.wiz-dot {
  width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center;
  background:#e5e7eb; color:#6b7280; font-weight:700; font-size:13px;
}
.wiz-label { font-size:11px; color:#9ca3af; font-weight:600; line-height:1.2; }
.wiz-item.active .wiz-dot { background:#16a34a; color:#fff; }
.wiz-item.active .wiz-label { color:#166534; }
.wiz-item.done .wiz-dot { background:#dcfce7; color:#166534; }
.wiz-item.done .wiz-label { color:#4b5563; }
.link-btn { background:none; border:0; color:#16a34a; font-weight:700; font-size:14px; cursor:pointer; padding:0; }
.invoice-create .card { padding:14px; margin-bottom:12px; }
.invoice-create .section-heading { margin-bottom:12px; }
.invoice-create .section-heading h3 { font-size:17px; }
.selected-box {
  display:flex; justify-content:space-between; align-items:center; gap:10px;
  padding:12px; background:#f0fdf4; border-radius:12px; margin-top:8px;
}
.client-result, .product-result {
  display:block; width:100%; text-align:left; border:0; cursor:pointer;
  padding:12px; background:#fff; border-radius:12px; margin-bottom:8px;
  box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.product-result { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
.product-result > div:first-child { min-width:0; flex:1; }
.client-result strong, .product-result strong { word-break:break-word; }
.cart-list { display:flex; flex-direction:column; gap:10px; margin-bottom:12px; }
.cart-row {
  display:grid; grid-template-columns:44px 1fr; gap:10px; align-items:start;
  background:#fff; border-radius:14px; padding:12px; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.cart-ico {
  width:44px; height:44px; border-radius:10px; background:#f3f4f6;
  display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0;
}
.cart-body { min-width:0; }
.cart-name { font-weight:700; font-size:15px; color:#111827; word-break:break-word; }
.cart-unit { font-size:13px; color:#9ca3af; margin-top:2px; }
.cart-controls {
  display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px; flex-wrap:wrap;
}
.cart-price { color:#b45309; font-weight:700; font-size:14px; }
.qty-box { display:flex; align-items:center; gap:6px; }
.qty-btn {
  width:36px; height:36px; border-radius:10px; border:1px solid #e5e7eb; background:#fff;
  font-size:20px; font-weight:700; color:#374151; cursor:pointer; line-height:1;
  display:flex; align-items:center; justify-content:center; -webkit-tap-highlight-color:transparent;
}
.qty-btn:active { background:#f0fdf4; border-color:#22c55e; }
.qty-input {
  width:64px; height:36px; text-align:center; border:1.5px solid #e5e7eb; border-radius:10px;
  font-size:16px; font-weight:700; -moz-appearance:textfield; appearance:textfield;
}
.qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.cart-line { font-weight:800; font-size:15px; white-space:nowrap; }
.cart-remove { border:0; background:none; color:#9ca3af; font-size:12px; cursor:pointer; margin-top:6px; padding:0; }
.summary-card .card-row { font-size:15px; }
.pay-pills { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin-bottom:16px; }
.pay-pill {
  border:1.5px solid #e5e7eb; background:#fff; border-radius:12px; padding:12px 8px;
  font-weight:700; font-size:14px; cursor:pointer; min-height:44px;
}
.pay-pill.active { background:#dcfce7; border-color:#22c55e; color:#166534; }
.pay-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
.pay-big { font-size:20px; }
.quick-add { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:10px; }
.quick-btn {
  border:1px solid #e5e7eb; background:#fff; border-radius:10px; padding:10px 4px;
  font-weight:700; font-size:13px; cursor:pointer; min-height:40px;
}
.pay-change {
  display:flex; justify-content:space-between; align-items:center;
  background:#f3f4f6; border-radius:12px; padding:12px 14px; margin-top:12px; font-weight:600;
}
.wiz-next, .actions-row .btn, .invoice-create .btn-full { min-height:48px; font-size:16px; font-weight:700; }
.actions-row { display:grid; grid-template-columns:1fr 1.4fr; gap:10px; margin-top:12px; }
.num-dot { font-size:16px !important; }
.compact-empty { padding:24px 12px; }
.simple-modal { position:fixed; inset:0; z-index:120; padding:12px; overflow-y:auto; }
.simple-modal-backdrop { position:fixed; inset:0; background:rgba(17,24,39,.48); }
.simple-modal-card {
  position:relative; z-index:1; width:100%; max-width:480px; margin:6vh auto;
  background:#fff; border-radius:16px; padding:14px; box-shadow:0 20px 40px rgba(0,0,0,.2);
}
.done-card { text-align:center; padding:24px 12px 8px; }
.done-check {
  width:88px; height:88px; margin:8px auto 16px; border-radius:50%;
  background:#22c55e; color:#fff; font-size:44px; display:flex; align-items:center; justify-content:center;
  box-shadow:0 0 0 12px rgba(34,197,94,.15), 0 0 0 24px rgba(34,197,94,.08);
}
.done-card h2 { font-size:22px; margin-bottom:8px; }
.done-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; }
.done-tile {
  display:flex; flex-direction:column; align-items:center; gap:8px; text-decoration:none; color:#374151;
  background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px 10px; font-weight:600; font-size:13px;
}
.done-tile-icon { font-size:22px; }
.invoice-create .search-bar input, .invoice-create .form-control { font-size:16px; min-height:44px; }
@media (min-width:768px) {
  .wiz-label { font-size:12px; }
  .wiz-dot { width:32px; height:32px; }
  .pay-pills { grid-template-columns:repeat(3,minmax(0,1fr)); }
}
</style>

<script>
const csrfToken = <?= json_encode(\App\Helpers\Csrf::token()) ?>;
const initialItems = <?= json_encode($existingItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const initialClientId = <?= json_encode($initialClientId) ?>;
const initialPaymentMethod = <?= json_encode($initialMethod) ?>;
const initialStatus = <?= json_encode($initialStatus) ?>;
const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
const editInvoiceId = <?= json_encode($editId) ?>;
const cart = {};
let selectedClientId = initialClientId || null;
let selectedClientData = null;
let currentStep = 1;
let payMode = 'cash';
let pickerOpen = false;

function debounce(fn, ms = 300) {
  let t;
  return function (...args) {
    const ctx = this;
    clearTimeout(t);
    t = setTimeout(() => fn.apply(ctx, args), ms);
  };
}

/** Always use dot as decimal separator (phone-safe). */
function parseNum(v) {
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
  let s = String(v ?? '').trim().replace(/\s/g, '').replace(',', '.');
  if (!s || s === '.' || s === '-') return 0;
  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}
function money(v) {
  const n = Number(v || 0);
  const parts = n.toFixed(2).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return parts.join('.') + ' с.';
}
function html(s) {
  return String(s ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}
function isPieceUnit(unit) {
  const u = String(unit || '').toLowerCase();
  return u === 'шт' || u === 'кор' || u === 'piece' || u === 'box';
}
function unitStep(unit) {
  return isPieceUnit(unit) ? 1 : 0.1;
}
function formatQty(qty, unit) {
  const n = Number(qty || 0);
  if (isPieceUnit(unit)) return String(Math.round(n));
  const fixed = n.toFixed(3).replace(/\.?0+$/, '');
  return fixed.includes('.') ? fixed : fixed + '.0';
}
function lineTotal(item) {
  return Math.max(0, Number(item.qty || 0) * Number(item.price || 0));
}
function invoiceDiscount() { return Math.max(0, parseNum(document.getElementById('invoiceDiscount').value)); }
function invoiceTotal() {
  return Math.max(0, Object.values(cart).reduce((sum, item) => sum + lineTotal(item), 0) - invoiceDiscount());
}
function methodForSave() {
  if (payMode === 'card') return 'card';
  if (payMode === 'bank') return 'bank';
  if (payMode === 'transfer') return 'transfer';
  return 'cash';
}
function cartQtySum() {
  return Object.values(cart).reduce((sum, item) => sum + Number(item.qty || 0), 0);
}
function receivedAmount() {
  return Math.max(0, parseNum(document.getElementById('paidAmount').value));
}
function paidForSave() {
  const total = invoiceTotal();
  if (payMode === 'debt') return 0;
  // Real amount: underpay → debt, overpay → capped to total (сдача отдельно)
  return Math.min(total, Math.max(0, receivedAmount()));
}
function statusForSave() {
  const total = invoiceTotal();
  const paid = paidForSave();
  if (payMode === 'debt' || paid <= 0.001) return 'unpaid';
  if (paid + 0.001 >= total) return 'paid';
  return 'partial';
}

async function apiSearch(url, q) {
  const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
    method: 'GET',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  const raw = await res.text();
  let data = null;
  try { data = JSON.parse(raw); } catch (e) { data = null; }
  if (!res.ok) throw new Error((data && data.error) ? data.error : `Ошибка поиска (${res.status})`);
  return Array.isArray(data) ? data : [];
}

function renderClient() {
  const box = document.getElementById('selectedClient');
  if (!selectedClientData) { box.style.display = 'none'; return; }
  document.getElementById('selectedClientName').textContent = selectedClientData.name;
  document.getElementById('selectedClientPhone').textContent = selectedClientData.phone || 'Без телефона';
  box.style.display = 'flex';
}
function selectClient(client) {
  if (!client || !client.id) return;
  selectedClientId = Number(client.id);
  selectedClientData = client;
  document.getElementById('clientResults').innerHTML = '';
  document.getElementById('clientSearch').value = client.name || '';
  renderClient();
}
function clearClient() {
  selectedClientId = null;
  selectedClientData = null;
  document.getElementById('clientSearch').value = '';
  document.getElementById('clientResults').innerHTML = '';
  renderClient();
}
async function fetchSelectedClient(id) {
  if (!id) return;
  try {
    const data = await apiSearch('/api/clients/search', String(id));
    const client = data.find((item) => Number(item.id) === Number(id));
    if (client) selectClient(client);
  } catch (e) {}
}
async function resolveClientFromQuery(q) {
  q = String(q || '').trim();
  if (!q) return null;
  const data = await apiSearch('/api/clients/search', q);
  if (!data.length) return null;
  const qLower = q.toLowerCase();
  const qDigits = q.replace(/\D/g, '');
  const exact = data.find((c) => {
    if (String(c.name || '').toLowerCase() === qLower) return true;
    if (qDigits.length >= 3) return String(c.phone || '').replace(/\D/g, '').includes(qDigits);
    return false;
  });
  if (exact) return exact;
  if (data.length === 1) return data[0];
  const starts = data.filter((c) => String(c.name || '').toLowerCase().startsWith(qLower));
  if (starts.length === 1) return starts[0];
  return null;
}
function renderClientResults(list) {
  const target = document.getElementById('clientResults');
  if (!list.length) {
    target.innerHTML = '<div class="muted" style="padding:8px;">Ничего не найдено. Создайте нового клиента.</div>';
    return;
  }
  target.innerHTML = list.map((client) => `
    <button type="button" class="client-result" data-client-id="${client.id}">
      <strong>${html(client.name)}</strong><br>
      <small class="muted">${html(client.phone || '—')}</small>
    </button>
  `).join('');
  target.querySelectorAll('[data-client-id]').forEach((node) => {
    node.addEventListener('click', () => {
      const client = list.find((c) => Number(c.id) === Number(node.dataset.clientId));
      if (client) selectClient(client);
    });
  });
}

function toggleProductPicker(force) {
  pickerOpen = typeof force === 'boolean' ? force : !pickerOpen;
  document.getElementById('productPicker').style.display = pickerOpen ? 'block' : 'none';
  if (pickerOpen) {
    const input = document.getElementById('productSearch');
    input.focus();
  }
}
function renderProductResults(list) {
  const target = document.getElementById('productResults');
  if (!list.length) {
    target.innerHTML = '<div class="muted" style="padding:8px;">Ничего не найдено</div>';
    return;
  }
  target.innerHTML = list.map((product) => {
    const stock = Number(product.stock_quantity ?? 0);
    return `
      <button type="button" class="product-result" data-product-id="${product.id}">
        <div>
          <strong>${html(product.name)}</strong><br>
          <small class="muted">${html(product.unit || 'шт')} · Остаток: ${formatQty(stock, product.unit)}</small>
        </div>
        <div class="fw-700">${money(product.sale_price)}</div>
      </button>`;
  }).join('');
  target.querySelectorAll('[data-product-id]').forEach((node) => {
    node.addEventListener('click', () => {
      const product = list.find((p) => Number(p.id) === Number(node.dataset.productId));
      if (product) addToCart(product);
    });
  });
}
async function resolveProductFromQuery(q) {
  q = String(q || '').trim();
  if (!q) return null;
  const data = await apiSearch('/api/products/search', q);
  if (!data.length) return null;
  const qLower = q.toLowerCase();
  const exact = data.find((p) =>
    String(p.sku || '').toLowerCase() === qLower ||
    String(p.barcode || '').toLowerCase() === qLower ||
    String(p.name || '').toLowerCase() === qLower
  );
  if (exact) return exact;
  if (data.length === 1) return data[0];
  return null;
}

function addToCart(product) {
  const id = Number(product.id);
  const unit = product.unit || 'шт';
  const step = unitStep(unit);
  if (!cart[id]) {
    cart[id] = {
      id,
      name: product.name,
      unit,
      price: Number(product.sale_price || product.price || 0),
      qty: step,
      stock: Number(product.stock_quantity || product.stock || 0),
    };
  } else {
    cart[id].qty = Number((Number(cart[id].qty) + step).toFixed(3));
  }
  document.getElementById('productSearch').value = '';
  document.getElementById('productResults').innerHTML = '';
  toggleProductPicker(false);
  renderAll();
}
function removeItem(id) {
  delete cart[id];
  renderAll();
}
function changeQty(id, delta) {
  if (!cart[id]) return;
  const step = unitStep(cart[id].unit) * delta;
  let next = Number((Number(cart[id].qty) + step).toFixed(3));
  if (next <= 0) { removeItem(id); return; }
  if (isPieceUnit(cart[id].unit)) next = Math.round(next);
  cart[id].qty = next;
  renderAll();
}
function setQtyFromInput(id, raw) {
  if (!cart[id]) return;
  let n = parseNum(raw);
  if (n <= 0) { removeItem(id); return; }
  if (isPieceUnit(cart[id].unit)) n = Math.max(1, Math.round(n));
  else n = Number(n.toFixed(3));
  cart[id].qty = n;
  renderAll();
}

function renderItems() {
  const items = Object.values(cart);
  const empty = document.getElementById('cartEmpty');
  empty.style.display = items.length ? 'none' : 'block';
  document.getElementById('cartItems').innerHTML = items.map((item) => {
    const over = Number(item.qty) > Number(item.stock);
    return `
      <div class="cart-row">
        <div class="cart-ico">📦</div>
        <div class="cart-body">
          <div class="cart-name">${html(item.name)}</div>
          <div class="cart-unit">${html(item.unit)}${over ? ' · <span class="text-red">нет остатка</span>' : ''}</div>
          <div class="cart-controls">
            <div class="cart-price">${money(item.price)}</div>
            <div class="qty-box">
              <button type="button" class="qty-btn" onclick="changeQty(${item.id}, -1)" aria-label="minus">−</button>
              <input class="qty-input num-dot" type="text" inputmode="decimal" autocomplete="off"
                     value="${formatQty(item.qty, item.unit)}"
                     onchange="setQtyFromInput(${item.id}, this.value)"
                     onblur="setQtyFromInput(${item.id}, this.value)">
              <button type="button" class="qty-btn" onclick="changeQty(${item.id}, 1)" aria-label="plus">+</button>
            </div>
            <div class="cart-line">${money(lineTotal(item))}</div>
          </div>
          <button type="button" class="cart-remove" onclick="removeItem(${item.id})">Удалить</button>
        </div>
      </div>`;
  }).join('');
}

function renderTotals() {
  const items = Object.values(cart);
  const total = invoiceTotal();
  const qty = cartQtySum();
  const mainUnit = items.length === 1 ? (items[0].unit || 'шт') : 'шт.';
  document.getElementById('cartCountLabel').textContent = `Товаров (${items.length})`;
  document.getElementById('cartQtyLabel').textContent = `${formatQty(qty, items.length === 1 ? items[0].unit : 'шт')} ${mainUnit}`;
  document.getElementById('totalLabel').textContent = money(total);
  document.getElementById('payTotalLabel').textContent = money(total);

  const received = receivedAmount();
  const change = Math.max(0, received - total);
  const remain = Math.max(0, total - received);
  const label = document.getElementById('payExtraLabel');
  const value = document.getElementById('payChangeLabel');
  if (change > 0.001) {
    label.textContent = 'Сдача';
    value.textContent = money(change);
    value.classList.remove('text-red');
  } else if (remain > 0.001 && payMode !== 'debt') {
    label.textContent = 'Остаток долга';
    value.textContent = money(remain);
    value.classList.add('text-red');
  } else {
    label.textContent = 'Сдача';
    value.textContent = money(0);
    value.classList.remove('text-red');
  }
}

function setPayMode(mode) {
  payMode = mode;
  document.querySelectorAll('.pay-pill').forEach((n) => n.classList.toggle('active', n.dataset.mode === mode));
  const total = invoiceTotal();
  const receivedGroup = document.getElementById('receivedGroup');
  const changeBox = document.getElementById('changeBox');
  if (mode === 'debt') {
    document.getElementById('paidAmount').value = '0.00';
    receivedGroup.style.display = 'none';
    changeBox.style.display = 'none';
  } else {
    receivedGroup.style.display = 'block';
    changeBox.style.display = 'flex';
    if (receivedAmount() <= 0 && mode !== 'partial' && mode !== 'debt') {
      document.getElementById('paidAmount').value = total.toFixed(2);
    }
  }
  document.getElementById('paymentMethod').value = methodForSave();
  renderTotals();
}
function onPaidInput() {
  const el = document.getElementById('paidAmount');
  // normalize typed comma to dot without fighting caret too hard
  const raw = el.value;
  if (raw.includes(',')) {
    const pos = el.selectionStart;
    el.value = raw.replace(/,/g, '.');
    try { el.setSelectionRange(pos, pos); } catch (e) {}
  }
  renderTotals();
}
function onDiscountInput() {
  const el = document.getElementById('invoiceDiscount');
  if (el.value.includes(',')) {
    const pos = el.selectionStart;
    el.value = el.value.replace(/,/g, '.');
    try { el.setSelectionRange(pos, pos); } catch (e) {}
  }
  if ((payMode === 'cash' || payMode === 'card' || payMode === 'bank' || payMode === 'transfer') && receivedAmount() > 0) {
    // keep received as-is; totals/change update
  }
  renderTotals();
}
function addQuick(amount) {
  const next = receivedAmount() + Number(amount);
  document.getElementById('paidAmount').value = next.toFixed(2);
  if (payMode === 'debt') setPayMode('partial');
  renderTotals();
}

function renderTrack(step) {
  document.querySelectorAll('.wiz-item').forEach((node) => {
    const n = Number(node.dataset.step);
    node.classList.toggle('active', n === step);
    node.classList.toggle('done', n < step);
  });
}

async function goStep(step) {
  if (step === 2 && !selectedClientId) {
    const q = document.getElementById('clientSearch').value.trim();
    if (q) {
      try {
        const matched = await resolveClientFromQuery(q);
        if (matched) selectClient(matched);
        else {
          const list = await apiSearch('/api/clients/search', q);
          renderClientResults(list);
          alert(list.length ? 'Нажмите клиента из списка.' : 'Клиент не найден. Создайте нового.');
          return;
        }
      } catch (e) {
        alert(e.message || 'Ошибка поиска клиента');
        return;
      }
    }
  }
  if (step === 2 && !selectedClientId) {
    alert('Выберите клиента или создайте нового.');
    return;
  }

  if (step === 3 && !Object.keys(cart).length) {
    const q = document.getElementById('productSearch').value.trim();
    if (q) {
      try {
        const matched = await resolveProductFromQuery(q);
        if (matched) addToCart(matched);
        else {
          toggleProductPicker(true);
          const list = await apiSearch('/api/products/search', q);
          renderProductResults(list);
          alert(list.length ? 'Нажмите товар из списка.' : 'Товар не найден.');
          return;
        }
      } catch (e) {
        alert(e.message || 'Ошибка поиска товара');
        return;
      }
    }
  }
  if (step === 3) {
    if (!Object.keys(cart).length) {
      alert('Добавьте хотя бы один товар.');
      return;
    }
    const over = Object.values(cart).some((item) => Number(item.qty) > Number(item.stock));
    if (over) {
      alert('Количество одного из товаров превышает остаток.');
      return;
    }
    // sync payment defaults from current total
    if (payMode === 'cash' || payMode === 'card') {
      document.getElementById('paidAmount').value = invoiceTotal().toFixed(2);
    }
  }

  if (step === 4) return; // only via finishInvoice

  currentStep = step;
  [1, 2, 3, 4].forEach((index) => {
    document.getElementById(`step${index}`).style.display = index === step ? 'block' : 'none';
  });
  renderTrack(step);
  renderAll();
}

async function finishInvoice() {
  if (!selectedClientId) { alert('Выберите клиента.'); return; }
  if (!Object.keys(cart).length) { alert('Добавьте хотя бы один товар.'); return; }
  const over = Object.values(cart).some((item) => Number(item.qty) > Number(item.stock));
  if (over) { alert('Количество превышает остаток.'); return; }

  const payload = {
    client_id: selectedClientId,
    items: Object.values(cart).map((item) => ({
      product_id: item.id,
      product_name: item.name,
      unit: item.unit,
      quantity: Number(item.qty),
      unit_price: Number(item.price),
      discount: 0,
    })),
    discount: parseNum(document.getElementById('invoiceDiscount').value),
    paid_amount: paidForSave(),
    payment_method: methodForSave(),
    notes: document.getElementById('notes').value.trim(),
    invoice_date: document.getElementById('invoiceDate').value,
    status: statusForSave(),
    idempotency_key: isEditMode ? '' : (window.crypto?.randomUUID ? window.crypto.randomUUID() : String(Date.now())),
  };

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.textContent = 'Сохранение...';

  try {
    const url = isEditMode ? `/api/invoices/${editInvoiceId}` : '/api/invoices';
    const method = isEditMode ? 'PUT' : 'POST';
    const res = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Ошибка сохранения');

    const id = data.id || editInvoiceId;
    const number = data.number || ('#' + id);
    document.getElementById('doneTitle').textContent = isEditMode ? 'Накладная сохранена!' : 'Накладная создана!';
    document.getElementById('doneNumber').textContent = '№ ' + number;
    document.getElementById('doneDate').textContent = 'от ' + new Date().toLocaleString('ru-RU');
    document.getElementById('doneViewBtn').href = '/invoices/' + id;
    document.getElementById('doneShareBtn').href = '/invoices/' + id + '/pdf';

    currentStep = 4;
    [1, 2, 3, 4].forEach((index) => {
      document.getElementById(`step${index}`).style.display = index === 4 ? 'block' : 'none';
    });
    renderTrack(4);
  } catch (e) {
    alert(e.message || 'Ошибка сохранения');
    btn.disabled = false;
    btn.textContent = isEditMode ? 'Сохранить →' : 'Далее →';
  }
}

function openClientModal() { document.getElementById('clientModal').style.display = 'block'; document.getElementById('newClientName').focus(); }
function closeClientModal() { document.getElementById('clientModal').style.display = 'none'; }

async function createClientInline() {
  const payload = {
    name: document.getElementById('newClientName').value.trim(),
    phone: document.getElementById('newClientPhone').value.trim(),
    address: document.getElementById('newClientAddress').value.trim(),
  };
  if (!payload.name) { alert('Введите имя клиента.'); return; }
  const res = await fetch('/api/invoice-clients', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'Ошибка создания клиента'); return; }
  selectClient(data.client);
  document.getElementById('newClientName').value = '';
  document.getElementById('newClientPhone').value = '';
  document.getElementById('newClientAddress').value = '';
  closeClientModal();
}

function renderAll() {
  renderClient();
  renderItems();
  renderTotals();
}

document.getElementById('clientSearch').addEventListener('input', debounce(async function (e) {
  const q = String((e && e.target ? e.target.value : this.value) || '').trim();
  const target = document.getElementById('clientResults');
  if (q.length < 1) { target.innerHTML = ''; return; }
  if (selectedClientData && String(selectedClientData.name || '') !== q) {
    selectedClientId = null; selectedClientData = null; renderClient();
  }
  target.innerHTML = '<div class="muted" style="padding:8px;">Поиск...</div>';
  try { renderClientResults(await apiSearch('/api/clients/search', q)); }
  catch (err) { target.innerHTML = `<div class="muted" style="padding:8px;color:#ef4444;">${html(err.message)}</div>`; }
}, 350));

document.getElementById('productSearch').addEventListener('input', debounce(async function (e) {
  const q = String((e && e.target ? e.target.value : this.value) || '').trim();
  const target = document.getElementById('productResults');
  if (q.length < 1) { target.innerHTML = ''; return; }
  target.innerHTML = '<div class="muted" style="padding:8px;">Поиск...</div>';
  try { renderProductResults(await apiSearch('/api/products/search', q)); }
  catch (err) { target.innerHTML = `<div class="muted" style="padding:8px;color:#ef4444;">${html(err.message)}</div>`; }
}, 350));

// Force comma → dot on all decimal fields
document.addEventListener('input', (e) => {
  if (!e.target.classList.contains('num-dot')) return;
  const el = e.target;
  if (!el.value.includes(',')) return;
  const pos = el.selectionStart;
  el.value = el.value.replace(/,/g, '.');
  try { el.setSelectionRange(pos, pos); } catch (err) {}
});

initialItems.forEach((item) => {
  const unit = item.unit || 'шт';
  let qty = Number(item.qty || 0);
  if (isPieceUnit(unit)) qty = Math.round(qty);
  cart[item.id] = {
    id: Number(item.id),
    name: item.name,
    unit,
    price: Number(item.price || 0),
    qty,
    stock: Number(item.stock || 0),
  };
});

if (initialClientId) fetchSelectedClient(initialClientId);

// map initial payment UI
if (initialStatus === 'unpaid' || Number(<?= json_encode($initialPaid) ?>) <= 0) payMode = 'debt';
else if (initialStatus === 'partial') payMode = 'partial';
else if (initialPaymentMethod === 'card') payMode = 'card';
else payMode = 'cash';
setPayMode(payMode);
renderTrack(1);
renderAll();
</script>
