<div class="auth-page">
  <div class="auth-box">
    <div class="auth-logo">📦</div>
    <h1 class="auth-title">Накладная Cloud</h1>
    <p class="auth-sub">Управление накладными и клиентами</p>

    <div id="loginStatus" class="alert alert-info" style="display:none;"></div>

    <button id="tgLoginBtn" class="btn btn-primary btn-full" style="font-size:16px; padding:14px;">
      <span>✈</span> Войти через Telegram
    </button>

    <?php if (($_ENV['MOCK_LOGIN_ENABLED'] ?? 'false') === 'true'): ?>
    <hr class="divider" style="margin:20px 0;">
    <p style="font-size:12px; color:#9ca3af; margin-bottom:12px;">Режим разработки</p>
    <button id="mockLoginBtn" class="btn btn-secondary btn-full">
      👤 Демо-вход (dev only)
    </button>
    <?php endif; ?>

    <p style="font-size:12px; color:#9ca3af; margin-top:20px;">
      Nakladna Cloud &copy; <?= date('Y') ?>
    </p>
  </div>
</div>

<script>
(function () {
  const tg = window.Telegram?.WebApp;
  const status = document.getElementById('loginStatus');

  function showMsg(msg, type = 'info') {
    status.className = 'alert alert-' + type;
    status.textContent = msg;
    status.style.display = 'block';
  }

  async function doLogin(body) {
    const res = await fetch('/auth/telegram', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    const data = await res.json();
    if (data.success) {
      showMsg('Вход выполнен! Перенаправление...', 'success');
      setTimeout(() => window.location.href = data.redirect, 500);
    } else {
      showMsg(data.error || 'Ошибка входа', 'error');
    }
  }

  document.getElementById('tgLoginBtn')?.addEventListener('click', () => {
    if (tg && tg.initData) {
      doLogin('initData=' + encodeURIComponent(tg.initData));
    } else {
      showMsg('Откройте приложение в Telegram', 'warning');
    }
  });

  document.getElementById('mockLoginBtn')?.addEventListener('click', async () => {
    const res = await fetch('/auth/mock-login', { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      window.location.href = data.redirect;
    } else {
      showMsg(data.error, 'error');
    }
  });

  // Auto-login if Telegram WebApp
  if (tg && tg.initData && tg.initData.length > 10) {
    showMsg('Авторизация...', 'info');
    doLogin('initData=' + encodeURIComponent(tg.initData));
  }
})();
</script>
