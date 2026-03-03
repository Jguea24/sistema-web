<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @return array<int, array{key:string, href:string, label:string, icon:string, roles:array<int, string>}>
 */
function admin_nav_items(): array
{
    return [
        ['key' => 'dashboard', 'href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'passkeys', 'href' => 'passkeys.php', 'label' => 'Passkeys', 'icon' => 'bi-person-bounding-box', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'pacientes', 'href' => 'pacientes.php', 'label' => 'Pacientes', 'icon' => 'bi-people', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'agenda', 'href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'bi-calendar-check', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'historial', 'href' => 'historial.php', 'label' => 'Historial', 'icon' => 'bi-journal-medical', 'roles' => ['admin', 'terapeuta']],
        ['key' => 'pagos', 'href' => 'pagos.php', 'label' => 'Pagos', 'icon' => 'bi-cash-stack', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'recordatorios', 'href' => 'recordatorios.php', 'label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'solicitudes', 'href' => 'citas.php', 'label' => 'Solicitudes Web', 'icon' => 'bi-inboxes', 'roles' => ['admin', 'terapeuta', 'recepcion']],
        ['key' => 'hero', 'href' => 'hero.php', 'label' => 'Hero Web', 'icon' => 'bi-window-sidebar', 'roles' => ['admin']],
        ['key' => 'servicios', 'href' => 'servicios.php', 'label' => 'Servicios Web', 'icon' => 'bi-images', 'roles' => ['admin']],
        ['key' => 'equipo', 'href' => 'equipo.php', 'label' => 'Equipo Web', 'icon' => 'bi-person-badge', 'roles' => ['admin']],
        ['key' => 'usuarios', 'href' => 'usuarios.php', 'label' => 'Usuarios', 'icon' => 'bi-shield-lock', 'roles' => ['admin']],
    ];
}

function render_admin_nav(string $active): void
{
    $role = get_admin_user_role();
    $allowedByRole = [
        'admin' => ['dashboard', 'passkeys', 'pacientes', 'agenda', 'historial', 'pagos', 'recordatorios', 'solicitudes', 'hero', 'servicios', 'equipo', 'usuarios'],
        'terapeuta' => ['passkeys', 'pacientes', 'agenda', 'historial', 'pagos', 'recordatorios', 'solicitudes'],
        'recepcion' => ['passkeys', 'pacientes', 'agenda', 'pagos', 'recordatorios', 'solicitudes'],
    ];
    $allowedKeys = $allowedByRole[$role] ?? [];

    foreach (admin_nav_items() as $item) {
        if (!in_array($role, $item['roles'], true)) {
            continue;
        }
        if (!in_array($item['key'], $allowedKeys, true)) {
            continue;
        }
        $isActive = $active === $item['key'];
        ?>
        <li class="nav-item">
          <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= esc($item['href']) ?>">
            <i class="bi <?= esc($item['icon']) ?>"></i>
            <span><?= esc($item['label']) ?></span>
          </a>
        </li>
        <?php
    }
}

function admin_header(string $title, string $active = 'dashboard', string $subtitle = ''): void
{
    $userName = get_admin_user_name();
    $userRole = ucfirst(get_admin_user_role());
    $mustChange = admin_must_change_password();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title) ?> | Admin Consultorio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-shell">
  <div class="admin-layout">
    <aside class="admin-sidebar d-none d-lg-flex flex-column p-3">
      <a href="index.php" class="admin-brand d-flex align-items-center gap-2 mb-4">
        <span class="admin-brand-mark">PB</span>
        <span>Admin Consultorio</span>
      </a>
      <ul class="nav flex-column admin-nav gap-1">
        <?php render_admin_nav($active); ?>
      </ul>
      <div class="mt-auto small text-white-50 pt-4">
        <div class="mb-2">Sesion activa:</div>
        <div class="fw-semibold text-white"><?= esc($userName) ?></div>
        <div><?= esc($userRole) ?></div>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar py-2 px-3 px-lg-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileNav" aria-controls="adminMobileNav">
            <i class="bi bi-list"></i>
          </button>
          <a href="../index.php" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-house-door me-1"></i>Ver web publica
          </a>
        </div>

        <div class="d-flex align-items-center gap-2">
          <span class="small text-secondary d-none d-md-inline"><?= esc($userName) ?> (<?= esc($userRole) ?>)</span>
          <a href="passkeys.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-person-bounding-box me-1"></i>Passkeys
          </a>
          <a href="cambiar_password.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-key me-1"></i>Contrasena
          </a>
          <a href="logout.php" class="btn btn-sm btn-danger js-confirm" data-confirm="Quieres salir? Aceptar para salir o Cancelar para quedarte.">
            <i class="bi bi-box-arrow-right me-1"></i>Salir
          </a>
        </div>
      </header>

      <?php if ($mustChange): ?>
      <div class="alert alert-warning rounded-0 mb-0 border-0">
        Debes cambiar tu contrasena inicial para continuar usando el sistema.
      </div>
      <?php endif; ?>

      <div class="offcanvas offcanvas-start" tabindex="-1" id="adminMobileNav" aria-labelledby="adminMobileNavLabel">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title" id="adminMobileNavLabel">Menu admin</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="nav flex-column admin-nav gap-1">
            <?php render_admin_nav($active); ?>
          </ul>
        </div>
      </div>

      <main class="admin-content">
        <div class="mb-4">
          <h1 class="admin-page-title"><?= esc($title) ?></h1>
          <?php if ($subtitle !== ''): ?>
          <p class="admin-page-subtitle"><?= esc($subtitle) ?></p>
          <?php endif; ?>
        </div>
<?php
}

function admin_footer(): void
{
    ?>
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
<?php
}

