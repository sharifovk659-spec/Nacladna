<?php

use App\Models\Subscription;

$flash = $_SESSION['flash_success'] ?? null;

unset($_SESSION['flash_success']);

$sessionStatus = Subscription::sessionStatusFromRow($subscription);

$startsAt = $subscription['starts_at'] ?? $subscription['trial_start'] ?? null;

$endsAt = $subscription['ends_at'] ?? $subscription['trial_end'] ?? null;

$defaultMonths = $defaultMonths ?? 12;

$tariffs = $tariffs ?? Subscription::tariffs();

?>

<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>



<div class="sub-page">

  <div class="card sub-hero">

    <?php if (!$subscription): ?>

      <div class="sub-icon">⚠️</div>

      <h2>Подписка не найдена</h2>

      <p class="muted">Выберите тариф и отправьте запрос на активацию.</p>

    <?php else: ?>

      <div class="sub-icon"><?= $readOnly ? '🔒' : '✨' ?></div>

      <h2><?= htmlspecialchars($planLabel) ?></h2>

      <p class="sub-status sub-status-<?= htmlspecialchars($sessionStatus) ?>">

        <?= match ($sessionStatus) {

          'trial' => 'Пробный период · ' . (int)$daysLeft . ' дн.',

          'active' => 'Активна · ' . (int)$daysLeft . ' дн.',

          default => 'Истекла · только просмотр',

        } ?>

      </p>

      <?php if ($startsAt && $endsAt): ?>

        <div class="sub-dates">

          <div><span class="muted">Начало</span><strong><?= date('d.m.Y', strtotime((string)$startsAt)) ?></strong></div>

          <div><span class="muted">Окончание</span><strong><?= date('d.m.Y', strtotime((string)$endsAt)) ?></strong></div>

        </div>

      <?php endif; ?>

      <?php if ($daysLeft <= 3 && $daysLeft > 0 && !$readOnly): ?>

        <div class="alert alert-warning sub-warn">Скоро истекает — выберите тариф ниже.</div>

      <?php endif; ?>

    <?php endif; ?>

  </div>



  <div class="card sub-tariffs-card">

    <div class="sub-tariffs-head">

      <h3>Тарифы Nakladna Cloud</h3>

      <p class="muted">Оплата вручную · админ активирует после перевода</p>

    </div>



    <div class="tariff-grid" id="tariffGrid" role="listbox" aria-label="Выбор тарифа">

      <?php foreach ($tariffs as $months => $t): ?>

        <button type="button"

          class="tariff-card<?= (int)$months === (int)$defaultMonths ? ' is-selected' : '' ?>"

          data-months="<?= (int)$months ?>"

          role="option"

          aria-selected="<?= (int)$months === (int)$defaultMonths ? 'true' : 'false' ?>">

          <?php if (!empty($t['badge'])): ?>

            <span class="tariff-badge"><?= htmlspecialchars((string)$t['badge']) ?></span>

          <?php endif; ?>

          <?php if (!empty($t['discount_percent'])): ?>

            <span class="tariff-discount">−<?= (int)$t['discount_percent'] ?>%</span>

          <?php endif; ?>

          <div class="tariff-duration"><?= htmlspecialchars((string)$t['label']) ?></div>

          <div class="tariff-price"><?= htmlspecialchars((string)$t['price_label']) ?></div>

          <?php if (!empty($t['includes_note'])): ?>

            <div class="tariff-includes"><?= htmlspecialchars((string)$t['includes_note']) ?></div>

          <?php endif; ?>

          <ul class="tariff-features">

            <?php foreach ($t['features'] as $feat): ?>

              <li><span class="tariff-check">✓</span><?= htmlspecialchars($feat) ?></li>

            <?php endforeach; ?>

          </ul>

          <?php if (!empty($t['savings_label'])): ?>

            <div class="tariff-savings">Экономия <strong><?= htmlspecialchars((string)$t['savings_label']) ?></strong></div>

          <?php else: ?>

            <div class="tariff-savings tariff-savings-muted">Базовый тариф</div>

          <?php endif; ?>

          <div class="tariff-select-ring" aria-hidden="true"></div>

        </button>

      <?php endforeach; ?>

    </div>



    <form method="POST" action="/subscription/request" class="sub-request-form" id="subRequestForm">

      <?= \App\Helpers\Csrf::field() ?>

      <label class="form-label" for="period_months">Выбранный период</label>

      <select name="period_months" id="period_months" class="form-control" required aria-live="polite">

        <?php foreach ($tariffs as $months => $t): ?>

          <option value="<?= (int)$months ?>" <?= (int)$months === (int)$defaultMonths ? 'selected' : '' ?>>

            <?= htmlspecialchars((string)$t['label']) ?> — <?= htmlspecialchars((string)$t['price_label']) ?>

          </option>

        <?php endforeach; ?>

      </select>



      <div class="sub-selected-summary" id="selectedSummary">

        <?php $sel = $tariffs[$defaultMonths] ?? reset($tariffs); ?>

        <span class="muted">К оплате:</span>

        <strong id="summaryPrice"><?= htmlspecialchars((string)($sel['price_label'] ?? '')) ?></strong>

        <span class="muted" id="summarySaving"><?= !empty($sel['savings_label']) ? ' · экономия ' . $sel['savings_label'] : '' ?></span>

      </div>



      <button type="submit" class="btn btn-primary btn-full sub-submit-btn" <?= $readOnly ? 'disabled' : '' ?>>

        Запросить активацию

      </button>

    </form>

    <?php if ($readOnly): ?>

      <p class="muted sub-hint">Подписка истекла — просмотр только. Запрос недоступен.</p>

    <?php else: ?>

      <p class="muted sub-hint">Нажмите на карточку или выберите в списке — затем отправьте запрос.</p>

    <?php endif; ?>

  </div>



  <?php if (!empty($history)): ?>

  <div class="card">

    <h3 class="sub-section-title">История</h3>

    <div class="sub-history">

      <?php foreach ($history as $row): ?>

        <div class="sub-history-row">

          <div>

            <strong><?= htmlspecialchars(Subscription::actionLabel((string)$row['action'])) ?></strong>

            <?php if (!empty($row['period_months'])): ?>

              <span class="sub-history-tag"><?= (int)$row['period_months'] ?> мес.</span>

            <?php endif; ?>

            <?php if (isset($row['price_som']) && $row['price_som'] !== null && $row['price_som'] !== ''): ?>

              <span class="sub-history-tag sub-history-price"><?= htmlspecialchars(Subscription::formatPriceSom((float)$row['price_som'])) ?></span>

            <?php endif; ?>

            <?php if (!empty($row['notes'])): ?>

              <div class="muted" style="font-size:12px;"><?= htmlspecialchars((string)$row['notes']) ?></div>

            <?php endif; ?>

          </div>

          <div class="sub-history-meta">

            <?= date('d.m.Y H:i', strtotime((string)$row['created_at'])) ?>

          </div>

        </div>

      <?php endforeach; ?>

    </div>

  </div>

  <?php endif; ?>

</div>



<style>

.sub-page { max-width: 920px; margin: 0 auto; padding-bottom: 24px; }

.sub-hero {

  text-align: center; padding: 22px 16px; margin-bottom: 14px;

  background: linear-gradient(165deg, #ecfdf5 0%, #fff 55%, #f0fdf4 100%);

  border: 1px solid rgba(34, 197, 94, 0.15);

}

.sub-icon { font-size: 44px; margin-bottom: 6px; filter: drop-shadow(0 6px 12px rgba(34,197,94,.2)); }

.sub-hero h2 { font-size: 20px; font-weight: 800; margin-top: 4px; }

.sub-status { margin-top: 8px; font-weight: 700; font-size: 14px; }

.sub-status-trial, .sub-status-active { color: var(--green-dark, #16a34a); }

.sub-status-expired { color: var(--red, #ef4444); }

.sub-dates {

  display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px;

  text-align: left; background: rgba(255,255,255,.7); border-radius: 14px; padding: 12px;

}

.sub-dates strong { display: block; font-size: 15px; margin-top: 4px; }

.sub-warn { margin-top: 12px; text-align: left; font-size: 13px; }

.sub-tariffs-card { padding: 16px 14px 18px; overflow: hidden; }

.sub-tariffs-head h3 { font-size: 18px; font-weight: 800; margin: 0 0 4px; }

.sub-tariffs-head p { font-size: 13px; margin: 0 0 14px; }

.tariff-grid {

  display: grid;

  grid-template-columns: repeat(2, minmax(0, 1fr));

  gap: 10px;

  margin-bottom: 16px;

}

.tariff-card {

  position: relative;

  text-align: left;

  border: 2px solid var(--gray-200, #e5e7eb);

  border-radius: 16px;

  padding: 14px 12px 12px;

  background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);

  cursor: pointer;

  transition: border-color .2s, box-shadow .2s, transform .15s;

  box-shadow: 0 2px 8px rgba(0,0,0,.04);

  font: inherit;

  color: inherit;

  width: 100%;

}

.tariff-card:active { transform: scale(0.98); }

.tariff-card.is-selected {

  border-color: #22c55e;

  box-shadow: 0 0 0 1px #22c55e, 0 8px 24px rgba(34, 197, 94, 0.22);

  background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);

}

.tariff-badge {

  position: absolute; top: 10px; right: 10px;

  background: linear-gradient(135deg, #f59e0b, #ea580c);

  color: #fff; font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 999px;

  text-transform: uppercase; letter-spacing: .03em;

}

.tariff-discount {

  position: absolute; top: 10px; left: 10px;

  font-size: 11px; font-weight: 800; color: #16a34a;

  background: #dcfce7; padding: 3px 7px; border-radius: 8px;

}

.tariff-card:has(.tariff-badge) .tariff-discount { top: 36px; }

.tariff-duration { font-weight: 800; font-size: 15px; margin-top: 8px; padding-right: 48px; }

.tariff-price { font-size: 22px; font-weight: 900; color: #16a34a; margin: 6px 0 4px; letter-spacing: -0.02em; }

.tariff-includes { font-size: 11px; color: #6b7280; margin-bottom: 8px; font-weight: 600; }

.tariff-features { list-style: none; margin: 0; padding: 0; font-size: 11px; line-height: 1.35; color: #374151; }

.tariff-features li { display: flex; gap: 6px; margin-bottom: 4px; align-items: flex-start; }

.tariff-check { color: #22c55e; font-weight: 800; flex-shrink: 0; }

.tariff-savings {

  margin-top: 10px; padding-top: 8px; border-top: 1px dashed #e5e7eb;

  font-size: 11px; color: #6b7280;

}

.tariff-savings strong { color: #059669; }

.tariff-savings-muted { color: #9ca3af; }

.tariff-select-ring {

  position: absolute; bottom: 10px; right: 10px; width: 18px; height: 18px;

  border-radius: 50%; border: 2px solid #d1d5db; background: #fff;

}

.tariff-card.is-selected .tariff-select-ring {

  border-color: #22c55e; background: #22c55e;

  box-shadow: inset 0 0 0 3px #fff;

}

.sub-request-form { margin-top: 4px; }

.sub-selected-summary {

  display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px;

  margin: 12px 0; padding: 12px; background: var(--gray-50, #f9fafb); border-radius: 12px; font-size: 14px;

}

.sub-selected-summary strong { font-size: 18px; color: #16a34a; }

.sub-submit-btn {

  margin-top: 4px; padding: 14px; font-size: 16px; font-weight: 800;

  box-shadow: 0 4px 14px rgba(34, 197, 94, 0.35);

}

.sub-hint { font-size: 12px; text-align: center; margin-top: 10px; }

.sub-section-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; }

.sub-history { display: flex; flex-direction: column; gap: 10px; }

.sub-history-row {

  display: flex; justify-content: space-between; gap: 12px; align-items: flex-start;

  padding-bottom: 10px; border-bottom: 1px solid var(--gray-100, #f3f4f6);

}

.sub-history-row:last-child { border-bottom: 0; padding-bottom: 0; }

.sub-history-meta { font-size: 12px; color: var(--gray-500, #6b7280); white-space: nowrap; }

.sub-history-tag {

  display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px;

  border-radius: 999px; background: #f3f4f6; margin-left: 6px; vertical-align: middle;

}

.sub-history-price { background: #dcfce7; color: #166534; }

@media (min-width: 768px) {

  .tariff-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }

  .tariff-card { padding: 16px 14px 14px; }

  .tariff-features { font-size: 12px; }

  .sub-tariffs-card { padding: 20px 18px 22px; }

}

</style>



<script>

(function () {

  const grid = document.getElementById('tariffGrid');

  const select = document.getElementById('period_months');

  const summaryPrice = document.getElementById('summaryPrice');

  const summarySaving = document.getElementById('summarySaving');

  if (!grid || !select) return;



  const tariffs = <?= json_encode(array_values(array_map(function ($t) {

    return [

      'months' => (int)$t['months'],

      'label' => (string)$t['label'],

      'price_label' => (string)$t['price_label'],

      'savings_label' => $t['savings_label'] ?? null,

    ];

  }, $tariffs)), JSON_UNESCAPED_UNICODE) ?>;



  function findTariff(months) {

    return tariffs.find(function (t) { return t.months === months; }) || tariffs[0];

  }



  function setMonths(months) {

    select.value = String(months);

    grid.querySelectorAll('.tariff-card').forEach(function (card) {

      var m = parseInt(card.getAttribute('data-months'), 10);

      var on = m === months;

      card.classList.toggle('is-selected', on);

      card.setAttribute('aria-selected', on ? 'true' : 'false');

    });

    var t = findTariff(months);

    if (t && summaryPrice) {

      summaryPrice.textContent = t.price_label;

      if (summarySaving) {

        summarySaving.textContent = t.savings_label ? ' · экономия ' + t.savings_label : '';

      }

    }

  }



  grid.addEventListener('click', function (e) {

    var card = e.target.closest('.tariff-card');

    if (!card) return;

    setMonths(parseInt(card.getAttribute('data-months'), 10));

  });



  select.addEventListener('change', function () {

    setMonths(parseInt(select.value, 10));

  });



  setMonths(parseInt(select.value, 10) || 12);

})();

</script>


