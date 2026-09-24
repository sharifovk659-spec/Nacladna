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
