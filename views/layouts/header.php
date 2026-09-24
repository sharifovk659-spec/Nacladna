<header class="top-header">
  <button class="menu-toggle" id="menuToggle" aria-label="Меню">
    <span></span><span></span><span></span>
  </button>
  <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Главная') ?></h1>
  <div class="header-actions">
    <a href="/subscription" class="notif-btn" title="Подписка">🔔</a>
    <?php if (!empty($_SESSION['user']['photo_url'])): ?>
    <img src="<?= htmlspecialchars($_SESSION['user']['photo_url']) ?>" class="avatar" alt="avatar">
    <?php else: ?>
    <div class="avatar-placeholder"><?= mb_substr($_SESSION['user']['first_name'] ?? 'U', 0, 1) ?></div>
    <?php endif; ?>
  </div>
</header>
<?php
$showReadOnlyBanner = false;
if (!empty($_SESSION['company_id'])) {
    try {
        $showReadOnlyBanner = \App\Middleware\SubscriptionMiddleware::isReadOnly();
    } catch (\Throwable) {
        $showReadOnlyBanner = false;
    }
}
if ($showReadOnlyBanner): ?>
<div class="readonly-banner page-body" style="margin:0;padding-top:12px;padding-bottom:0;">
  Подписка истекла — доступ только для просмотра. <a href="/subscription">Продлить</a>
</div>
<?php endif; ?>
