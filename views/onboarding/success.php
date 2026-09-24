<?php
$trialEndFormatted = date('d.m.Y H:i', strtotime((string)$trialEnd));
?>
<div class="success-page">
  <div class="success-card">
    <div class="success-icon" aria-hidden="true">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
        <path d="M20 6L9 17l-5-5" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h1 class="success-title">Компания создана</h1>
    <p class="success-text">3 дня бесплатного доступа активированы</p>
    <?php if (!empty($companyName)): ?>
    <p class="success-company"><?= htmlspecialchars($companyName) ?></p>
    <?php endif; ?>
    <p class="success-trial">Пробный период до <?= htmlspecialchars($trialEndFormatted) ?></p>
    <a href="/dashboard" class="btn btn-primary btn-full success-btn">Перейти в приложение</a>
  </div>
</div>

<style>
.success-page {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: max(20px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
  background: #ffffff;
}
.success-card {
  width: 100%;
  max-width: 560px;
  text-align: center;
}
.success-icon {
  width: 72px;
  height: 72px;
  margin: 0 auto 20px;
  border-radius: 50%;
  background: #22c55e;
  display: flex;
  align-items: center;
  justify-content: center;
}
.success-title {
  font-size: 24px;
  font-weight: 800;
  color: #111827;
}
.success-text {
  margin-top: 8px;
  font-size: 15px;
  color: #166534;
  font-weight: 600;
}
.success-company {
  margin-top: 16px;
  font-size: 15px;
  color: #374151;
}
.success-trial {
  margin-top: 6px;
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 28px;
}
.success-btn {
  min-height: 52px;
  font-size: 16px;
}
@media (min-width: 768px) {
  .success-page { background: #f3f4f6; }
  .success-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 40px 32px;
    box-shadow: 0 8px 30px rgba(0,0,0,.06);
  }
}
</style>
