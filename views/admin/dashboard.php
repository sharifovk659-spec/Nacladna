<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Nakladna Cloud</title>
<link rel="stylesheet" href="/assets/css/main.css">
<style>
.admin-layout { display:flex; min-height:100vh; }
.admin-sidebar { width:220px; background:#1f2937; color:#fff; padding:20px 0; flex-shrink:0; }
.admin-sidebar h1 { font-size:16px; padding:0 20px 16px; border-bottom:1px solid #374151; }
.admin-sidebar a { display:block; padding:10px 20px; color:#d1d5db; text-decoration:none; font-size:14px; }
.admin-sidebar a:hover, .admin-sidebar a.active { background:#374151; color:#fff; }
.admin-main { flex:1; padding:24px 32px; background:#f9fafb; overflow-y:auto; }
.admin-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
.admin-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.admin-stat { background:#fff; border-radius:12px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.admin-stat-label { font-size:12px; color:#6b7280; }
.admin-stat-value { font-size:24px; font-weight:700; margin-top:4px; }
</style>
</head>
<body>
<div class="admin-layout">
  <div class="admin-sidebar">
    <h1>⚙️ Admin Panel</h1>
    <a href="/admin/dashboard" class="active">📊 Главная</a>
    <a href="/admin/companies">🏢 Компании</a>
    <form method="POST" action="/admin/logout" style="padding:10px 20px;">
      <?= \App\Helpers\Csrf::field() ?>
      <button type="submit" style="background:none;border:none;color:#d1d5db;cursor:pointer;font-size:14px;padding:0;">🚪 Выход</button>
    </form>
  </div>
  <div class="admin-main">
    <div class="admin-header">
      <h2 style="font-size:20px; font-weight:700;">Панель управления</h2>
      <span style="font-size:13px; color:#6b7280;">Привет, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?>!</span>
    </div>

    <?php $msg = $_SESSION['admin_msg'] ?? null; unset($_SESSION['admin_msg']); ?>
    <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="admin-stats">
      <div class="admin-stat">
        <div class="admin-stat-label">Компании</div>
        <div class="admin-stat-value"><?= $stats['total_companies'] ?></div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-label">Пользователи</div>
        <div class="admin-stat-value"><?= $stats['total_users'] ?></div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-label">Накладные</div>
        <div class="admin-stat-value"><?= $stats['total_invoices'] ?></div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-label">Выручка</div>
        <div class="admin-stat-value" style="font-size:18px;"><?= number_format((float)$stats['total_revenue'], 0) ?> с.</div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-label">Пробных</div>
        <div class="admin-stat-value text-blue"><?= $stats['active_trials'] ?></div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-label">Активных</div>
        <div class="admin-stat-value text-green"><?= $stats['active_subs'] ?></div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-label">Истекших</div>
        <div class="admin-stat-value text-red"><?= $stats['expired_subs'] ?></div>
      </div>
    </div>

    <!-- Recent companies -->
    <div style="background:#fff; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 1px 4px rgba(0,0,0,.06);">
      <h3 style="font-size:16px; font-weight:700; margin-bottom:16px;">Последние компании</h3>
      <table class="data-table">
        <thead><tr><th>Компания</th><th>Владелец</th><th>Накладные</th><th>Подписка</th><th>До</th></tr></thead>
        <tbody>
        <?php foreach ($recentCompanies as $co): ?>
        <tr>
          <td><strong><?= htmlspecialchars($co['name']) ?></strong></td>
          <td><?= htmlspecialchars(($co['first_name'] ?? '') . ' ' . ($co['last_name'] ?? '')) ?></td>
          <td><?= $co['invoice_count'] ?></td>
          <td><span class="badge badge-<?= $co['sub_status'] ?? 'expired' ?>"><?= $co['sub_status'] ?? '—' ?></span></td>
          <td style="font-size:12px;"><?= $co['sub_ends'] ? date('d.m.Y', strtotime($co['sub_ends'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent logs -->
    <div style="background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.06);">
      <h3 style="font-size:16px; font-weight:700; margin-bottom:16px;">Последние действия</h3>
      <table class="data-table">
        <thead><tr><th>Время</th><th>Действие</th><th>Пользователь</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($recentLogs as $log): ?>
        <tr>
          <td style="font-size:12px;"><?= date('d.m H:i', strtotime($log['created_at'])) ?></td>
          <td><?= htmlspecialchars($log['action']) ?></td>
          <td><?= htmlspecialchars($log['first_name'] ?? '—') ?></td>
          <td style="font-size:12px; color:#9ca3af;"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
