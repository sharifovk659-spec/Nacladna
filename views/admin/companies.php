<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Компании — Admin</title>
<link rel="stylesheet" href="/assets/css/main.css">
<style>
.admin-layout { display:flex; min-height:100vh; }
.admin-sidebar { width:220px; background:#1f2937; color:#fff; padding:20px 0; flex-shrink:0; }
.admin-sidebar h1 { font-size:16px; padding:0 20px 16px; border-bottom:1px solid #374151; }
.admin-sidebar a { display:block; padding:10px 20px; color:#d1d5db; text-decoration:none; font-size:14px; }
.admin-sidebar a:hover, .admin-sidebar a.active { background:#374151; color:#fff; }
.admin-main { flex:1; padding:24px 32px; background:#f9fafb; overflow-y:auto; }
</style>
</head>
<body>
<div class="admin-layout">
  <div class="admin-sidebar">
    <h1>⚙️ Admin</h1>
    <a href="/admin/dashboard">📊 Главная</a>
    <a href="/admin/companies" class="active">🏢 Компании</a>
    <form method="POST" action="/admin/logout" style="padding:10px 20px;">
      <?= \App\Helpers\Csrf::field() ?>
      <button type="submit" style="background:none;border:none;color:#d1d5db;cursor:pointer;font-size:14px;">🚪 Выход</button>
    </form>
  </div>
  <div class="admin-main">
    <h2 style="font-size:20px; font-weight:700; margin-bottom:16px;">🏢 Компании</h2>

    <?php $msg = $_SESSION['admin_msg'] ?? null; unset($_SESSION['admin_msg']); ?>
    <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <form method="GET" style="margin-bottom:16px;">
      <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Поиск..." class="form-control" style="max-width:300px;">
    </form>

    <div style="background:#fff; border-radius:12px; padding:0; box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:hidden;">
      <table class="data-table">
        <thead>
          <tr><th>ID</th><th>Компания</th><th>Владелец</th><th>Telegram</th><th>Накладные</th><th>Подписка</th><th>До</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($companies as $co): ?>
        <tr>
          <td><?= $co['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($co['name']) ?></strong><br>
            <span class="badge badge-<?= $co['status'] === 'active' ? 'active' : 'expired' ?>"><?= $co['status'] ?></span>
          </td>
          <td><?= htmlspecialchars(($co['first_name'] ?? '') . ' ' . ($co['last_name'] ?? '')) ?></td>
          <td style="font-size:12px;">@<?= htmlspecialchars($co['telegram_username'] ?? '—') ?></td>
          <td><?= $co['invoice_count'] ?></td>
          <td><span class="badge badge-<?= $co['sub_status'] ?? 'expired' ?>"><?= $co['sub_status'] ?? '—' ?></span></td>
          <td style="font-size:12px;"><?= $co['ends_at'] ? date('d.m.Y', strtotime($co['ends_at'])) : '—' ?></td>
          <td>
            <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
              <form method="POST" action="/admin/companies/<?= $co['id'] ?>/activate" style="display:flex; gap:4px;">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="number" name="days" value="30" min="1" max="365" style="width:60px; padding:4px 6px; border:1px solid #e5e7eb; border-radius:6px; font-size:12px;">
                <button type="submit" class="btn btn-sm btn-primary" style="font-size:12px;">✅ Активировать</button>
              </form>
              <form method="POST" action="/admin/companies/<?= $co['id'] ?>/extend" style="display:flex; gap:4px;">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="number" name="days" value="30" min="1" max="365" style="width:60px; padding:4px 6px; border:1px solid #e5e7eb; border-radius:6px; font-size:12px;">
                <button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;">➕ Продлить</button>
              </form>
              <form method="POST" action="/admin/companies/<?= $co['id'] ?>/suspend">
                <?= \App\Helpers\Csrf::field() ?>
                <button type="submit" class="btn btn-sm btn-danger" style="font-size:12px;" onclick="return confirm('Приостановить компанию?')">🚫</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
