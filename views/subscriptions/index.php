<?php
use App\Models\Subscription;
$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$sessionStatus = Subscription::sessionStatusFromRow($subscription);
$startsAt = $subscription['starts_at'] ?? $subscription['trial_start'] ?? null;
$endsAt = $subscription['ends_at'] ?? $subscription['trial_end'] ?? null;
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="sub-page">
  <div class="card sub-hero">
    <?php if (!$subscription): ?>
      <div class="sub-icon">⚠️</div>
      <h2>Подписка не найдена</h2>
      <p class="muted">Обратитесь в поддержку или отправьте запрос на активацию.</p>
    <?php else: ?>
      <div class="sub-icon"><?= $readOnly ? '🔒' : '✅' ?></div>
      <h2><?= htmlspecialchars($planLabel) ?></h2>
      <p class="sub-status sub-status-<?= htmlspecialchars($sessionStatus) ?>">
        <?= match ($sessionStatus) {
          'trial' => 'Пробный период · ' . (int)$daysLeft . ' дн. осталось',
          'active' => 'Активна · ' . (int)$daysLeft . ' дн. осталось',
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
        <div class="alert alert-warning sub-warn">Подписка скоро истекает. Отправьте запрос на продление.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 class="sub-section-title">Тарифы (оплата вручную)</h3>
    <div class="sub-plans">
      <?php foreach ($periodOptions as $opt): ?>
        <div class="sub-plan-card">
          <div class="sub-plan-label"><?= htmlspecialchars($opt['label']) ?></div>
          <div class="sub-plan-price"><?= htmlspecialchars($opt['price']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <form method="POST" action="/subscription/request" class="sub-request-form">
      <?= \App\Helpers\Csrf::field() ?>
      <?= \App\Helpers\Csrf::field() ?>
      <label class="form-label" for="period_months">Выберите период</label>
      <select name="period_months" id="period_months" class="form-control" required>
        <?php foreach ($periodOptions as $months => $opt): ?>
          <option value="<?= (int)$months ?>"><?= htmlspecialchars($opt['label']) ?> — <?= htmlspecialchars($opt['price']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:12px;">Запросить активацию</button>
    </form>
    <p class="muted sub-hint">Администратор подтвердит оплату и активирует подписку.</p>
  </div>

  <?php if (!empty($history)): ?>
  <div class="card">
    <h3 class="sub-section-title">История</h3>
    <div class="sub-history">
      <?php foreach ($history as $row): ?>
        <div class="sub-history-row">
          <div>
            <strong><?= htmlspecialchars(\App\Models\Subscription::actionLabel((string)$row['action'])) ?></strong>
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
.sub-page { max-width: 720px; margin: 0 auto; }
.sub-hero { text-align: center; padding: 24px 16px; }
.sub-icon { font-size: 48px; margin-bottom: 8px; }
.sub-hero h2 { font-size: 20px; font-weight: 800; margin-top: 4px; }
.sub-status { margin-top: 8px; font-weight: 700; }
.sub-status-trial, .sub-status-active { color: var(--green-dark); }
.sub-status-expired { color: var(--red); }
.sub-dates {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;
  text-align: left; background: var(--gray-50); border-radius: 12px; padding: 12px;
}
.sub-dates strong { display: block; font-size: 15px; margin-top: 4px; }
.sub-warn { margin-top: 12px; text-align: left; }
.sub-section-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; }
.sub-plans {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px;
}
.sub-plan-card {
  border: 1px solid var(--gray-200); border-radius: 12px; padding: 12px; background: var(--gray-50);
}
.sub-plan-label { font-weight: 700; font-size: 14px; }
.sub-plan-price { color: var(--green-dark); font-weight: 800; margin-top: 4px; }
.sub-hint { font-size: 12px; text-align: center; margin-top: 8px; }
.sub-history { display: flex; flex-direction: column; gap: 10px; }
.sub-history-row {
  display: flex; justify-content: space-between; gap: 12px; align-items: flex-start;
  padding-bottom: 10px; border-bottom: 1px solid var(--gray-100);
}
.sub-history-row:last-child { border-bottom: 0; padding-bottom: 0; }
.sub-history-meta { font-size: 12px; color: var(--gray-500); white-space: nowrap; }
@media (min-width: 640px) {
  .sub-plans { grid-template-columns: repeat(4, 1fr); }
  .sub-hero { padding: 28px 24px; }
}
</style>
