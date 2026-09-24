<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#22c55e">
<title><?= htmlspecialchars($pageTitle ?? 'Накладная Cloud') ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
<meta name="csrf-token" content="<?= htmlspecialchars(\App\Helpers\Csrf::token()) ?>">
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
(function () {
  var tg = window.Telegram && window.Telegram.WebApp;
  if (!tg) return;
  try {
    tg.ready();
    tg.expand();
    var tp = tg.themeParams || {};
    if (tp.bg_color) document.documentElement.style.setProperty('--tg-bg', tp.bg_color);
    document.documentElement.style.setProperty('--safe-top', (tg.safeAreaInset && tg.safeAreaInset.top || 0) + 'px');
  } catch (e) {}
})();
</script>
</head>
<body class="<?= isset($bodyClass) ? htmlspecialchars($bodyClass) : '' ?>">

<?php if (!isset($hideNav) || !$hideNav): ?>
<?php require __DIR__ . '/sidebar.php'; ?>
<?php endif; ?>

<div class="main-content <?= isset($hideNav) && $hideNav ? 'full-width' : '' ?>">
  <?php if (!isset($hideHeader) || !$hideHeader): ?>
  <?php require __DIR__ . '/header.php'; ?>
  <?php endif; ?>

  <div class="page-body">
    <?= $content ?? '' ?>
  </div>
</div>

<?php if (!isset($hideBottomNav) || !$hideBottomNav): ?>
<?php require __DIR__ . '/bottom-nav.php'; ?>
<?php endif; ?>

<script src="/assets/js/app.js"></script>
<?php if (isset($extraJs)): echo $extraJs; endif; ?>
</body>
</html>
