<?php
use App\Middleware\CurrentCompanyMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Models\Permission;
use App\Services\PermissionRegistry;

$actorRole = CurrentCompanyMiddleware::role();
$assignable = ['admin', 'manager', 'cashier', 'accountant'];
$roleOptions = array_filter($assignable, fn ($r) => PermissionRegistry::canAssignRole($actorRole, $r));
$error = $_SESSION['employee_error'] ?? null;
unset($_SESSION['employee_error']);
$isEdit = ($mode ?? '') === 'edit';
$effective = $isEdit ? Permission::effectiveForMembership($employee) : [];
$overrides = $overrides ?? [];
?>
<?php if ($error): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="page-head-row">
  <a href="/employees" class="inv-back">←</a>
  <h1 class="page-title"><?= $isEdit ? 'Редактирование' : 'Новый сотрудник' ?></h1>
</div>

<form method="POST" action="<?= $isEdit ? '/employees/' . (int)$employee['id'] : '/employees' ?>" class="card stack-form">
  <?= \App\Helpers\Csrf::field() ?>

  <label class="form-label">Имя в компании</label>
  <input type="text" name="display_name" class="form-control" required maxlength="200"
    value="<?= htmlspecialchars($isEdit ? (string)($employee['display_name'] ?: $employee['first_name']) : '') ?>">

  <?php if ($isEdit && (string)$employee['role'] === 'owner'): ?>
    <p class="muted">Роль: <strong>Владелец</strong></p>
  <?php else: ?>
    <label class="form-label">Роль</label>
    <select name="role" class="form-control" required>
      <?php foreach ($roleOptions as $r): ?>
        <option value="<?= htmlspecialchars($r) ?>" <?= ($isEdit && ($employee['role'] ?? '') === $r) ? 'selected' : '' ?>>
          <?= htmlspecialchars(PermissionRegistry::ROLE_LABELS[$r] ?? $r) ?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <?php if ($isEdit && (string)$employee['role'] !== 'owner' && PermissionMiddleware::can('employees.disable')): ?>
    <label class="form-label">Статус</label>
    <select name="status" class="form-control">
      <option value="active" <?= ($employee['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
      <option value="inactive" <?= ($employee['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Отключён</option>
    </select>
  <?php endif; ?>

  <?php if (!$isEdit): ?>
    <p class="muted" style="font-size:13px;">После сохранения вы получите ссылку-приглашение. Сотрудник откроет её в Telegram — ID подтверждается автоматически.</p>
  <?php endif; ?>

  <?php if ($isEdit && !empty($canPermissions) && (string)$employee['role'] !== 'owner'): ?>
    <h3 style="margin-top:16px;font-size:15px;">Дополнительные права</h3>
    <p class="muted" style="font-size:12px;">Отметьте только отличия от роли. Пусто = права роли по умолчанию.</p>
    <div class="perm-grid">
      <?php foreach (PermissionRegistry::ALL as $slug):
        $checked = array_key_exists($slug, $overrides) ? ($overrides[$slug] ? '1' : '0') : '';
        $hasRole = in_array($slug, $effective, true);
        ?>
        <label class="perm-row">
          <span class="perm-name"><?= htmlspecialchars($slug) ?></span>
          <select name="perm[<?= htmlspecialchars($slug) ?>]" class="form-control form-control-sm">
            <option value="" <?= $checked === '' ? 'selected' : '' ?>><?= $hasRole ? 'По роли ✓' : 'По роли ✗' ?></option>
            <option value="1" <?= $checked === '1' ? 'selected' : '' ?>>Разрешить</option>
            <option value="0" <?= $checked === '0' ? 'selected' : '' ?>>Запретить</option>
          </select>
        </label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary btn-full"><?= $isEdit ? 'Сохранить' : 'Создать приглашение' ?></button>
</form>

<style>
.stack-form { padding:16px; display:flex; flex-direction:column; gap:10px; }
.perm-grid { max-height:280px; overflow:auto; border:1px solid var(--border); border-radius:12px; padding:8px; }
.perm-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 4px; font-size:12px; }
.perm-name { flex:1; word-break:break-word; }
.form-control-sm { max-width:120px; padding:6px 8px; font-size:12px; }
</style>
