<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card form-card">
  <form method="POST" action="/products/<?= (int)$product['id'] ?>">
    <?= \App\Helpers\Csrf::field() ?>

    <div class="form-group">
      <label class="form-label">Название товара *</label>
      <input type="text" name="name" class="form-control" required
             value="<?= htmlspecialchars($product['name']) ?>" maxlength="200">
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Ед. измерения</label>
        <select name="unit" class="form-control">
          <?php foreach (\App\Models\Product::UNIT_LABELS as $value => $label): ?>
          <option value="<?= htmlspecialchars($value) ?>" <?= $product['unit'] === $value ? 'selected' : '' ?>>
            <?= htmlspecialchars($label) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Остаток</label>
        <input type="number" name="stock_quantity" class="form-control" step="0.001" min="0"
               value="<?= htmlspecialchars(number_format((float)$product['stock_quantity'], 3, '.', '')) ?>">
      </div>
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Цена покупки</label>
        <input type="number" name="purchase_price" class="form-control" step="0.01" min="0"
               value="<?= htmlspecialchars($product['purchase_price'] !== null ? number_format((float)$product['purchase_price'], 2, '.', '') : '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Цена продажи *</label>
        <input type="number" name="sale_price" class="form-control" step="0.01" min="0" required
               value="<?= htmlspecialchars(number_format((float)$product['sale_price'], 2, '.', '')) ?>">
      </div>
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" maxlength="100"
               value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Штрихкод</label>
        <input type="text" name="barcode" class="form-control" maxlength="100"
               value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Статус</label>
      <select name="status" class="form-control">
        <option value="active"   <?= $product['status'] === 'active'   ? 'selected' : '' ?>>Активен</option>
        <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Неактивен</option>
      </select>
    </div>

    <div class="form-actions">
      <a href="/products" class="btn btn-secondary">Отмена</a>
      <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
  </form>
</div>

<style>
.form-card { max-width: 560px; margin: 0 auto; }
.form-grid-2 { display:grid; grid-template-columns:1fr; gap:0; }
.form-actions { display:flex; gap:10px; margin-top:8px; }
.form-actions .btn { flex:1; min-height:48px; }
@media(min-width:480px) {
  .form-grid-2 { grid-template-columns:1fr 1fr; gap:12px; }
}
@media(min-width:768px) {
  .form-actions .btn-primary { flex:2; }
}
</style>
