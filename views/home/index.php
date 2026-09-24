<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:linear-gradient(180deg,#f0fdf4 0%,#ffffff 100%);">
  <div style="width:100%;max-width:1040px;">
    <div class="card" style="padding:28px;overflow:hidden;">
      <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;justify-content:space-between;">
        <div style="max-width:520px;">
          <div style="display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#dcfce7;color:#166534;font-size:13px;font-weight:700;">
            MVP Foundation Ready
          </div>
          <h1 style="font-size:36px;line-height:1.1;margin-top:16px;color:#111827;">Nakladna Cloud</h1>
          <p style="font-size:16px;color:#4b5563;margin-top:12px;">
            Production-ready PHP foundation connected to the existing infrastructure.
          </p>
          <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:20px;">
            <a href="/health" class="btn btn-primary">Open Health</a>
            <a href="/auth/login" class="btn btn-outline">Open Login</a>
            <a href="/admin" class="btn btn-secondary">Open Admin</a>
          </div>
        </div>

        <div style="flex:1;min-width:280px;max-width:420px;">
          <div class="stats-grid" style="grid-template-columns:1fr 1fr;">
            <div class="stat-card">
              <div class="stat-label">Database Connected</div>
              <div class="stat-value <?= $dbConnected ? 'green' : 'red' ?>">
                <?= $dbConnected ? 'Connected' : 'Error' ?>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Health Status</div>
              <div class="stat-value <?= $healthStatus === 'ok' ? 'green' : 'red' ?>">
                <?= strtoupper($healthStatus) ?>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Environment</div>
              <div class="stat-value" style="font-size:18px;"><?= htmlspecialchars($environment) ?></div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Version</div>
              <div class="stat-value" style="font-size:18px;"><?= htmlspecialchars($version) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;">
      <div class="card">
        <div class="card-title">Architecture</div>
        <div class="card-value" style="font-size:18px;">Simple MVC</div>
      </div>
      <div class="card">
        <div class="card-title">Database Driver</div>
        <div class="card-value" style="font-size:18px;">PDO MySQL</div>
      </div>
      <div class="card">
        <div class="card-title">Security</div>
        <div class="card-value" style="font-size:18px;">CSRF + Sessions</div>
      </div>
      <div class="card">
        <div class="card-title">Deployment</div>
        <div class="card-value" style="font-size:18px;">GitHub Actions</div>
      </div>
    </div>
  </div>
</div>
