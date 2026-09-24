<div class="mini-app-page" id="miniAppRoot">
  <div class="mini-app-card">
    <div class="mini-app-brand">Nakladna Cloud</div>
    <p class="mini-app-sub">Управление накладными в Telegram</p>

    <div id="authLoading" class="mini-app-state">
      <div class="mini-app-spinner" aria-hidden="true"></div>
      <p>Авторизация...</p>
    </div>

    <div id="authError" class="mini-app-state" hidden>
      <div class="mini-app-error-icon" aria-hidden="true">!</div>
      <p id="authErrorText">Ошибка авторизации</p>
      <button type="button" class="btn btn-primary btn-full" id="authRetryBtn">Повторить</button>
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
  padding: max(20px, env(safe-area-inset-top)) 16px max(20px, env(safe-area-inset-bottom));
  background: #ffffff;
}
.mini-app-card {
  width: 100%;
  max-width: 420px;
  text-align: center;
}
.mini-app-brand {
  font-size: 28px;
  font-weight: 800;
  color: #16a34a;
  letter-spacing: -0.02em;
}
.mini-app-sub {
  margin-top: 8px;
  color: #6b7280;
  font-size: 14px;
}
.mini-app-state {
  margin-top: 36px;
}
.mini-app-spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 16px;
  border: 3px solid #dcfce7;
  border-top-color: #22c55e;
  border-radius: 50%;
  animation: mini-spin .8s linear infinite;
}
@keyframes mini-spin { to { transform: rotate(360deg); } }
.mini-app-error-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 12px;
  border-radius: 50%;
  background: #fee2e2;
  color: #ef4444;
  font-size: 24px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
#authErrorText {
  color: #374151;
  margin-bottom: 16px;
  font-size: 15px;
}
</style>

<script>
(function () {
  const tg = window.Telegram && window.Telegram.WebApp;
  const loading = document.getElementById('authLoading');
  const errorBox = document.getElementById('authError');
  const errorText = document.getElementById('authErrorText');
  const retryBtn = document.getElementById('authRetryBtn');

  function applyTheme() {
    if (!tg) return;
    try {
      tg.ready();
      tg.expand();
      if (typeof tg.disableVerticalSwipes === 'function') {
        tg.disableVerticalSwipes();
      }
      const tp = tg.themeParams || {};
      if (tp.bg_color) {
        document.body.style.background = tp.bg_color;
        document.documentElement.style.setProperty('--tg-bg', tp.bg_color);
      }
      if (tp.text_color) {
        document.body.style.color = tp.text_color;
      }
      if (typeof tg.setHeaderColor === 'function') {
        tg.setHeaderColor(tp.bg_color || '#ffffff');
      }
      if (typeof tg.setBackgroundColor === 'function') {
        tg.setBackgroundColor(tp.bg_color || '#ffffff');
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
  }

  async function authenticate() {
    showLoading();
    applyTheme();

    if (!tg || !tg.initData || tg.initData.length < 10) {
      showError('Откройте приложение через кнопку в Telegram-боте.');
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
