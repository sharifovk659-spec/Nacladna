<?php
use App\Middleware\PermissionMiddleware;
use App\Services\PermissionRegistry;

$inviteLink = $_SESSION['employee_invite_link'] ?? null;
unset($_SESSION['employee_invite_link']);
?>
<?php if ($flash): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($inviteLink): ?>
<div class="card" style="margin-bottom:12px;">
  <strong>Ссылка для сотрудника</strong>
  <p class="muted" style="font-size:13px;margin:8px 0;">Откройте в Telegram на телефоне сотрудника:</p>
  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($inviteLink) ?>" onclick="this.select()">
</div>
<?php endif; ?>

<div class="page-head-row">
  <h1 class="page-title">Сотрудники</h1>
  <?php if ($canCreate): ?>
    <a href="/employees/create" class="btn btn-primary btn-sm">+ Добавить</a>
  <?php endif; ?>
</div>

<div class="card-list">
  <?php foreach ($employees as $emp):
    $label = PermissionRegistry::ROLE_LABELS[$emp['role']] ?? $emp['role'];
    $name = trim((string)($emp['display_name'] ?: ($emp['first_name'] . ' ' . $emp['last_name'])));
    $active = ($emp['status'] ?? '') === 'active';
    ?>
  <a href="/employees/<?= (int)$emp['id'] ?>/edit" class="card card-link emp-card">
    <div class="card-row">
      <div>
        <strong><?= htmlspecialchars($name) ?></strong>
        <div class="muted" style="font-size:12px;">
          <?= $emp['telegram_username'] ? '@' . htmlspecialchars($emp['telegram_username']) : 'Telegram подключён' ?>
        </div>
      </div>
      <div style="text-align:right;">
        <span class="badge badge-secondary"><?= htmlspecialchars($label) ?></span>
        <div class="muted" style="font-size:11px;margin-top:4px;">
          <?= $active ? 'Активен' : 'Отключён' ?>
        </div>
      </div>
    </div>
    <?php if (!empty($emp['last_activity_at']) || !empty($emp['last_login_at'])): ?>
    <div class="muted" style="font-size:11px;margin-top:8px;">
      Активность: <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)($emp['last_activity_at'] ?: $emp['last_login_at'])))) ?>
    </div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($invites !== []): ?>
<h2 class="section-title" style="margin-top:20px;">Ожидают подключения</h2>
<?php foreach ($invites as $inv):
  $label = PermissionRegistry::ROLE_LABELS[$inv['role']] ?? $inv['role'];
  ?>
<div class="card emp-invite-card">
  <div class="card-row">
    <div>
      <strong><?= htmlspecialchars($inv['display_name']) ?></strong>
      <div class="muted" style="font-size:12px;"><?= htmlspecialchars($label) ?> · до <?= date('d.m.Y', strtotime($inv['expires_at'])) ?></div>
    </div>
    <?php if (PermissionMiddleware::can('employees.edit')): ?>
    <form method="POST" action="/employees/invites/<?= (int)$inv['id'] ?>/revoke">
      <?= \App\Helpers\Csrf::field() ?>
      <button type="submit" class="btn btn-secondary btn-sm">Отменить</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<style>
.page-head-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
.emp-card { margin-bottom:10px; }
.section-title { font-size:16px; font-weight:700; }
</style>
