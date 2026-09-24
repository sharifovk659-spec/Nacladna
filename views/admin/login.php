<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Nakladna Cloud</title>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-box">
    <div style="font-size:48px; margin-bottom:8px;">⚙️</div>
    <h1 class="auth-title">Панель администратора</h1>
    <p class="auth-sub">Nakladna Cloud</p>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/login">
      <?= \App\Helpers\Csrf::field() ?>
      <div class="form-group">
        <label class="form-label">Логин</label>
        <input type="text" name="username" class="form-control" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Пароль</label>
        <input type="password" name="password" class="form-control" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full">Войти</button>
    </form>
  </div>
</div>
</body>
</html>
