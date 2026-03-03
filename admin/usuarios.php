<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin']);

$flash = '';
$flashClass = 'success';
$autoOpenNuevoUsuario = false;
$formNombre = '';
$formEmail = '';
$formRol = 'recepcion';

start_admin_session();
$currentUserId = (int) ($_SESSION['admin_user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'crear_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = trim($_POST['rol'] ?? 'recepcion');
        $passwordInicial = $_POST['password_inicial'] ?? '';

        $formNombre = $nombre;
        $formEmail = $email;
        $formRol = in_array($rol, ['admin', 'terapeuta', 'recepcion'], true) ? $rol : 'recepcion';

        if ($nombre === '' || $email === '' || $passwordInicial === '') {
            $flash = 'Nombre, correo y contrasena inicial son obligatorios.';
            $flashClass = 'danger';
            $autoOpenNuevoUsuario = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash = 'Correo invalido.';
            $flashClass = 'danger';
            $autoOpenNuevoUsuario = true;
        } elseif (!in_array($rol, ['admin', 'terapeuta', 'recepcion'], true)) {
            $flash = 'Rol invalido.';
            $flashClass = 'danger';
            $autoOpenNuevoUsuario = true;
        } else {
            $hash = password_hash($passwordInicial, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios_admin (nombre, email, password_hash, rol, debe_cambiar_password, activo)
                 VALUES (?, ?, ?, ?, 1, 1)'
            );
            try {
                $stmt->execute([$nombre, $email, $hash, $rol]);
                $flash = 'Usuario creado correctamente.';
                $flashClass = 'success';
                $formNombre = '';
                $formEmail = '';
                $formRol = 'recepcion';
            } catch (PDOException $e) {
                $flash = 'No se pudo crear el usuario (correo ya registrado).';
                $flashClass = 'danger';
                $autoOpenNuevoUsuario = true;
            }
        }
    }

    if ($action === 'toggle_activo') {
        $id = (int) ($_POST['id'] ?? 0);
        $nuevo = (int) ($_POST['nuevo_estado'] ?? 1);
        if ($id === $currentUserId && $nuevo === 0) {
            $flash = 'No puedes desactivar tu propio usuario.';
            $flashClass = 'danger';
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios_admin SET activo = ? WHERE id = ?');
            $stmt->execute([$nuevo, $id]);
            $flash = 'Estado del usuario actualizado.';
            $flashClass = 'success';
        }
    }

    if ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $nueva = $_POST['nueva_password'] ?? '';
        if (strlen($nueva) < 8) {
            $flash = 'La nueva contrasena debe tener al menos 8 caracteres.';
            $flashClass = 'danger';
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios_admin SET password_hash = ?, debe_cambiar_password = 1 WHERE id = ?');
            $stmt->execute([$hash, $id]);
            $flash = 'Contrasena restablecida. El usuario debera cambiarla al iniciar sesion.';
            $flashClass = 'success';
        }
    }

    if ($action === 'editar_usuario') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $rol = trim((string) ($_POST['rol'] ?? 'recepcion'));
        $activo = ((string) ($_POST['activo'] ?? '1')) === '1' ? 1 : 0;
        $debeCambiar = ((string) ($_POST['debe_cambiar_password'] ?? '0')) === '1' ? 1 : 0;

        if ($id <= 0 || $nombre === '' || $email === '') {
            $flash = 'Datos invalidos para editar usuario.';
            $flashClass = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash = 'Correo invalido.';
            $flashClass = 'danger';
        } elseif (!in_array($rol, ['admin', 'terapeuta', 'recepcion'], true)) {
            $flash = 'Rol invalido.';
            $flashClass = 'danger';
        } elseif ($id === $currentUserId && $activo === 0) {
            $flash = 'No puedes desactivar tu propio usuario.';
            $flashClass = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios_admin
                     SET nombre = ?, email = ?, rol = ?, activo = ?, debe_cambiar_password = ?
                     WHERE id = ?'
                );
                $stmt->execute([$nombre, $email, $rol, $activo, $debeCambiar, $id]);
                $flash = 'Usuario actualizado correctamente.';
                $flashClass = 'success';
            } catch (PDOException $e) {
                $flash = 'No se pudo actualizar el usuario (correo ya registrado).';
                $flashClass = 'danger';
            }
        }
    }

    if ($action === 'eliminar_usuario') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $currentUserId) {
            $flash = 'No puedes eliminar tu propio usuario.';
            $flashClass = 'danger';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM usuarios_admin WHERE id = ?');
            $stmt->execute([$id]);
            $flash = 'Usuario eliminado correctamente.';
            $flashClass = 'success';
        }
    }
}

$usuarios = $pdo->query(
    'SELECT id, nombre, email, rol, activo, debe_cambiar_password, ultimo_login_en, creado_en
     FROM usuarios_admin
     ORDER BY id DESC'
)->fetchAll();

admin_header('Usuarios y roles', 'usuarios', 'Gestion de accesos del sistema.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Usuarios registrados</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($usuarios) ?> usuarios</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
        <i class="bi bi-person-plus me-1"></i>Nuevo usuario
      </button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Rol</th>
          <th>Activo</th>
          <th>Forzar cambio</th>
          <th>Ultimo login</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td>#<?= (int) $u['id'] ?></td>
          <td class="fw-semibold"><?= esc((string) $u['nombre']) ?></td>
          <td><?= esc((string) $u['email']) ?></td>
          <td><span class="badge badge-soft"><?= esc((string) $u['rol']) ?></span></td>
          <td><?= (int) $u['activo'] === 1 ? 'Si' : 'No' ?></td>
          <td><?= (int) $u['debe_cambiar_password'] === 1 ? 'Si' : 'No' ?></td>
          <td><?= esc((string) ($u['ultimo_login_en'] ?? '')) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAccionesUsuario<?= (int) $u['id'] ?>">
              <i class="bi bi-gear me-1"></i>Acciones
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-labelledby="modalNuevoUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalNuevoUsuarioLabel">Nuevo usuario</h2>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formNuevoUsuario">
            <i class="bi bi-save me-1"></i>Guardar cambios
          </button>
        </div>
      </div>
      <form method="POST" id="formNuevoUsuario">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_usuario">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Nombre</label>
              <input name="nombre" class="form-control" value="<?= esc($formNombre) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Correo</label>
              <input type="email" name="email" class="form-control" value="<?= esc($formEmail) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Rol</label>
              <select name="rol" class="form-select">
                <option value="admin" <?= $formRol === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="terapeuta" <?= $formRol === 'terapeuta' ? 'selected' : '' ?>>Terapeuta</option>
                <option value="recepcion" <?= $formRol === 'recepcion' ? 'selected' : '' ?>>Recepcion</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Contrasena inicial</label>
              <input type="password" name="password_inicial" minlength="8" class="form-control" required>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($usuarios as $u): ?>
<div class="modal fade" id="modalAccionesUsuario<?= (int) $u['id'] ?>" tabindex="-1" aria-labelledby="modalAccionesUsuarioLabel<?= (int) $u['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalAccionesUsuarioLabel<?= (int) $u['id'] ?>">Acciones usuario #<?= (int) $u['id'] ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-column gap-2">
          <button class="btn btn-outline-warning text-start" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?= (int) $u['id'] ?>" data-bs-dismiss="modal">
            <i class="bi bi-pencil-square me-1"></i>Editar
          </button>

          <form method="POST" class="m-0">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="toggle_activo">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="hidden" name="nuevo_estado" value="<?= (int) $u['activo'] === 1 ? 0 : 1 ?>">
            <button class="btn btn-outline-secondary w-100 text-start">
              <?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
            </button>
          </form>

          <form method="POST" class="d-flex gap-2 m-0">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="password" name="nueva_password" class="form-control" placeholder="Nueva clave" minlength="8" required>
            <button class="btn btn-outline-primary">Reset</button>
          </form>

          <form method="POST" class="m-0">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="eliminar_usuario">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn-outline-danger w-100 text-start js-confirm" <?= (int) $u['id'] === $currentUserId ? 'disabled' : '' ?> data-confirm="Eliminar usuario #<?= (int) $u['id'] ?>?">
              Eliminar
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditarUsuario<?= (int) $u['id'] ?>" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel<?= (int) $u['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEditarUsuarioLabel<?= (int) $u['id'] ?>">Editar usuario #<?= (int) $u['id'] ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_usuario">
        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input name="nombre" value="<?= esc((string) $u['nombre']) ?>" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Correo</label>
              <input type="email" name="email" value="<?= esc((string) $u['email']) ?>" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Rol</label>
              <select name="rol" class="form-select">
                <option value="admin" <?= (string) $u['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="terapeuta" <?= (string) $u['rol'] === 'terapeuta' ? 'selected' : '' ?>>Terapeuta</option>
                <option value="recepcion" <?= (string) $u['rol'] === 'recepcion' ? 'selected' : '' ?>>Recepcion</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Activo</label>
              <select name="activo" class="form-select">
                <option value="1" <?= (int) $u['activo'] === 1 ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= (int) $u['activo'] !== 1 ? 'selected' : '' ?>>No</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Forzar cambio clave</label>
              <select name="debe_cambiar_password" class="form-select">
                <option value="1" <?= (int) $u['debe_cambiar_password'] === 1 ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= (int) $u['debe_cambiar_password'] !== 1 ? 'selected' : '' ?>>No</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i>Guardar cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if ($autoOpenNuevoUsuario): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalElement = document.getElementById('modalNuevoUsuario');
  if (!modalElement) {
    return;
  }
  var modal = new bootstrap.Modal(modalElement);
  modal.show();
});
</script>
<?php endif; ?>
<?php admin_footer(); ?>
