<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">📦</span>
    <span class="logo-text">Накладная</span>
  </div>
  <nav class="sidebar-nav">
    <a href="/dashboard" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/dashboard') || $_SERVER['REQUEST_URI'] === '/' ? 'active' : '' ?>">
      <span class="nav-icon">🏠</span> Главная
    </a>
    <a href="/invoices" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/invoices') ? 'active' : '' ?>">
      <span class="nav-icon">📋</span> Накладные
    </a>
    <a href="/clients" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/clients') ? 'active' : '' ?>">
      <span class="nav-icon">👥</span> Клиенты
    </a>
    <a href="/products" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/products') ? 'active' : '' ?>">
      <span class="nav-icon">📦</span> Товары
    </a>
    <a href="/debts" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/debts') ? 'active' : '' ?>">
      <span class="nav-icon">💳</span> Долги
    </a>
    <a href="/profile" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/profile') ? 'active' : '' ?>">
      <span class="nav-icon">👤</span> Профиль
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="/subscription" class="sub-status">
      <?php
      $subStatus = $_SESSION['sub_status'] ?? 'unknown';
      echo match($subStatus) {
        'trial'  => '<span class="badge badge-trial">Пробный</span>',
        'active' => '<span class="badge badge-active">Активен</span>',
        default  => '<span class="badge badge-expired">Истёк</span>',
      };
      ?>
    </a>
  </div>
</aside>
