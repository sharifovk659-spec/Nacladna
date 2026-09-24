<nav class="bottom-nav">
  <a href="/dashboard" class="bn-item <?= $_SERVER['REQUEST_URI'] === '/' || str_starts_with($_SERVER['REQUEST_URI'], '/dashboard') ? 'active' : '' ?>">
    <span class="bn-icon">🏠</span>
    <span class="bn-label">Главная</span>
  </a>
  <a href="/invoices" class="bn-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/invoices') && !str_contains($_SERVER['REQUEST_URI'], 'create') ? 'active' : '' ?>">
    <span class="bn-icon">📋</span>
    <span class="bn-label">Накладные</span>
  </a>
  <a href="/invoices/create" class="bn-item bn-create">
    <span class="bn-plus">＋</span>
  </a>
  <a href="/clients" class="bn-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/clients') ? 'active' : '' ?>">
    <span class="bn-icon">👥</span>
    <span class="bn-label">Клиенты</span>
  </a>
  <a href="/profile" class="bn-item <?= str_starts_with($_SERVER['REQUEST_URI'], '/profile') ? 'active' : '' ?>">
    <span class="bn-icon">👤</span>
    <span class="bn-label">Профиль</span>
  </a>
</nav>
