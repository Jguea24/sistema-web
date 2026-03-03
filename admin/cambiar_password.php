<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_login();

$error = '';
$success = '';

start_admin_session();
$userId = (int) ($_SESSION['admin_user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $actual = $_POST['password_actual'] ?? '';
    $nueva = $_POST['password_nueva'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($nueva === '' || $confirm === '' || $actual === '') {
        $error = 'Completa todos los campos.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La nueva contrasena debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirm) {
        $error = 'La confirmacion de contrasena no coincide.';
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM usuarios_admin WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($actual, (string) $row['password_hash'])) {
            $error = 'La contrasena actual es incorrecta.';
        } else {
            admin_change_password($pdo, $userId, $nueva);
            $success = 'Contrasena actualizada correctamente.';
        }
    }
}

admin_header('Cambiar contrasena', 'dashboard', 'Actualiza tu contrasena para continuar.');
?>
<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
<div class="alert alert-success">
  <?= esc($success) ?>
  <a class="alert-link ms-2" href="index.php">Ir al dashboard</a>
</div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4" style="max-width: 640px;">
  <h2 class="h5 mb-3">Cambio obligatorio de contrasena</h2>
  <form method="POST" class="row g-3">
    <?= csrf_input() ?>
    <div class="col-12">
      <label class="form-label">Contrasena actual</label>
      <input type="password" name="password_actual" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Nueva contrasena</label>
      <input type="password" name="password_nueva" class="form-control" minlength="8" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Confirmar nueva contrasena</label>
      <input type="password" name="password_confirm" class="form-control" minlength="8" required>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Guardar nueva contrasena</button>
      <a class="btn btn-outline-secondary" href="logout.php">Cerrar sesion</a>
    </div>
  </form>
</section>
<?php admin_footer(); ?>
