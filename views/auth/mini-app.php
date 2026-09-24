<?php
$botUsername = $botUsername ?? '';
$botUrl = $botUsername !== '' ? 'https://t.me/' . rawurlencode($botUsername) : '';
?>
<div class="mini-app-page" id="miniAppRoot">
  <div class="mini-app-glow" aria-hidden="true"></div>
  <div class="mini-app-card">
    <div class="mini-app-logo" aria-hidden="true">📦</div>
    <div class="mini-app-brand">Nakladna Cloud</div>
    <p class="mini-app-sub">Накладные, клиенты и склад — в Telegram</p>
    <p class="mini-app-user" id="miniAppUser" hidden></p>

    <div id="authLoading" class="mini-app-state">
      <div class="mini-app-spinner" aria-hidden="true"></div>
      <p id="authLoadingText">Подключаемся...</p>
    </div>

    <div id="authError" class="mini-app-state" hidden>
      <div class="mini-app-error-icon" aria-hidden="true">!</div>
      <p id="authErrorText">Ошибка авторизации</p>
      <?php if ($botUrl !== ''): ?>
        <a href="<?= htmlspecialchars($botUrl) ?>" class="btn btn-primary btn-full mini-app-bot-btn" target="_blank" rel="noopener">Открыть в Telegram</a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary btn-full" id="authRetryBtn">Повторить</button>
    </div>
  </div>
</div>

<style>
.mini-app-page {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: max(24px, env(safe-area-inset-top)) 20px max(24px, env(safe-area-inset-bottom));
  background: linear-gradient(165deg, #ecfdf5 0%, #ffffff 45%, #f0fdf4 100%);
  position: relative;
  overflow: hidden;
}
.mini-app-glow {
  position: absolute;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(34, 197, 94, 0.25) 0%, transparent 70%);
  top: 10%;
  left: 50%;
  transform: translateX(-50%);
  pointer-events: none;
}
.mini-app-card {
  width: 100%;
  max-width: 440px;
  text-align: center;
  position: relative;
  z-index: 1;
  padding: 8px 4px;
}
.mini-app-logo {
  font-size: 52px;
  line-height: 1;
  margin-bottom: 12px;
  filter: drop-shadow(0 8px 16px rgba(34, 197, 94, 0.25));
}
.mini-app-brand {
  font-size: clamp(26px, 5vw, 32px);
  font-weight: 800;
  color: #16a34a;
  letter-spacing: -0.03em;
}
.mini-app-sub {
  margin-top: 10px;
  color: #6b7280;
  font-size: 15px;
  line-height: 1.45;
  max-width: 320px;
  margin-left: auto;
  margin-right: auto;
}
.mini-app-user {
  margin-top: 12px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}
.mini-app-state {
  margin-top: 32px;
}
.mini-app-spinner {
  width: 44px;
  height: 44px;
  margin: 0 auto 16px;
  border: 3px solid #dcfce7;
  border-top-color: #22c55e;
  border-radius: 50%;
  animation: mini-spin .75s linear infinite;
}
@keyframes mini-spin { to { transform: rotate(360deg); } }
#authLoadingText {
  color: #4b5563;
  font-size: 15px;
}
.mini-app-error-icon {
  width: 52px;
  height: 52px;
  margin: 0 auto 14px;
  border-radius: 50%;
  background: #fee2e2;
  color: #ef4444;
  font-size: 26px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
#authErrorText {
  color: #374151;
  margin-bottom: 16px;
  font-size: 15px;
  line-height: 1.45;
}
.mini-app-bot-btn { margin-bottom: 10px; }
@media (min-width: 768px) {
  .mini-app-card { padding: 24px 16px; }
  .mini-app-logo { font-size: 64px; }
}
</style>

<script>
(function () {
  const tg = window.Telegram && window.Telegram.WebApp;
  const loading = document.getElementById('authLoading');
  const loadingText = document.getElementById('authLoadingText');
  const errorBox = document.getElementById('authError');
  const errorText = document.getElementById('authErrorText');
  const retryBtn = document.getElementById('authRetryBtn');
  const userLine = document.getElementById('miniAppUser');

  function applyTheme() {
    if (!tg) return;
    try {
      tg.ready();
      tg.expand();
      if (typeof tg.disableVerticalSwipes === 'function') {
        tg.disableVerticalSwipes();
      }
      document.documentElement.classList.add('tg-webapp');
      document.body.classList.add('tg-webapp');
      const tp = tg.themeParams || {};
      if (tp.bg_color) {
        document.body.style.background = tp.bg_color;
        document.querySelector('.mini-app-page').style.background = tp.bg_color;
      }
      if (typeof tg.setHeaderColor === 'function') {
        tg.setHeaderColor(tp.bg_color || '#ffffff');
      }
      if (typeof tg.setBackgroundColor === 'function') {
        tg.setBackgroundColor(tp.bg_color || '#ffffff');
      }
      const u = tg.initDataUnsafe && tg.initDataUnsafe.user;
      if (u && u.first_name) {
        userLine.hidden = false;
        userLine.textContent = 'Привет, ' + u.first_name + '!';
      }
    } catch (e) {}
  }

  function showError(msg) {
    loading.hidden = true;
    errorBox.hidden = false;
    errorText.textContent = msg || 'Ошибка авторизации Telegram';
  }

  function showLoading() {
    errorBox.hidden = true;
    loading.hidden = false;
    loadingText.textContent = 'Авторизация...';
  }

  async function authenticate() {
    showLoading();
    applyTheme();

    if (!tg || !tg.initData || tg.initData.length < 10) {
      showError('Откройте приложение через кнопку «Открыть» в Telegram-боте (телефон или Telegram Desktop).');
      return;
    }

    try {
      const res = await fetch('/api/auth/telegram', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json'
        },
        body: 'initData=' + encodeURIComponent(tg.initData),
        credentials: 'same-origin'
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        showError(data.error || 'Ошибка авторизации');
        return;
      }
      loadingText.textContent = 'Готово! Загружаем...';
      window.location.replace(data.redirect || (data.onboarding_required ? '/onboarding' : '/dashboard'));
    } catch (e) {
      showError('Нет соединения. Проверьте интернет и повторите.');
    }
  }

  retryBtn.addEventListener('click', authenticate);
  applyTheme();
  authenticate();
})();
</script>
