<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin', 'terapeuta', 'recepcion']);

$flash = '';
$flashClass = 'success';
$autoOpenModal = false;

$formNombres = '';
$formApellidos = '';
$formFechaNacimiento = '';
$formGenero = '';
$formTelefono = '';
$formEmail = '';
$formDireccion = '';
$formContactoEmergencia = '';
$formNotasIniciales = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'crear_paciente') {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $genero = trim($_POST['genero'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $contactoEmergencia = trim($_POST['contacto_emergencia'] ?? '');
        $notasIniciales = trim($_POST['notas_iniciales'] ?? '');

        $formNombres = $nombres;
        $formApellidos = $apellidos;
        $formFechaNacimiento = $fechaNacimiento;
        $formGenero = $genero;
        $formTelefono = $telefono;
        $formEmail = $email;
        $formDireccion = $direccion;
        $formContactoEmergencia = $contactoEmergencia;
        $formNotasIniciales = $notasIniciales;

        if ($nombres !== '' && $apellidos !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO pacientes (nombres, apellidos, fecha_nacimiento, genero, telefono, email, direccion, contacto_emergencia, notas_iniciales)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $nombres,
                $apellidos,
                $fechaNacimiento !== '' ? $fechaNacimiento : null,
                $genero,
                $telefono,
                $email,
                $direccion,
                $contactoEmergencia,
                $notasIniciales,
            ]);
            $flash = 'Paciente registrado correctamente.';
            $flashClass = 'success';
        } else {
            $flash = 'Nombres y apellidos son obligatorios.';
            $flashClass = 'danger';
            $autoOpenModal = true;
        }
    }

    if ($action === 'editar_paciente') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $genero = trim($_POST['genero'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $contactoEmergencia = trim($_POST['contacto_emergencia'] ?? '');
        $notasIniciales = trim($_POST['notas_iniciales'] ?? '');

        if ($id <= 0 || $nombres === '' || $apellidos === '') {
            $flash = 'Datos invalidos para editar paciente.';
            $flashClass = 'danger';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE pacientes
                 SET nombres = ?, apellidos = ?, fecha_nacimiento = ?, genero = ?, telefono = ?, email = ?, direccion = ?, contacto_emergencia = ?, notas_iniciales = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $nombres,
                $apellidos,
                $fechaNacimiento !== '' ? $fechaNacimiento : null,
                $genero,
                $telefono,
                $email,
                $direccion,
                $contactoEmergencia,
                $notasIniciales,
                $id,
            ]);
            $flash = 'Paciente actualizado correctamente.';
            $flashClass = 'success';
        }
    }

    if ($action === 'eliminar_paciente') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM pacientes WHERE id = ?');
            $stmt->execute([$id]);
            $flash = 'Paciente eliminado correctamente.';
            $flashClass = 'success';
        }
    }
}

$pacientes = $pdo->query(
    'SELECT id, nombres, apellidos, fecha_nacimiento, genero, telefono, email, direccion, contacto_emergencia, notas_iniciales, creado_en
     FROM pacientes ORDER BY id DESC LIMIT 200'
)->fetchAll();

admin_header('Pacientes', 'pacientes', 'Registro y gestion de pacientes.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
    <div>
      <h2 class="h5 mb-0">Listado de pacientes</h2>
      <p class="text-secondary mb-0">Registro y gestion de pacientes.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($pacientes) ?> registros</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoPaciente">
        <i class="bi bi-person-plus me-1"></i>Nuevo paciente
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Paciente</th>
          <th>Telefono</th>
          <th>Correo</th>
          <th>Registro</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$pacientes): ?>
        <tr><td colspan="6" class="text-center text-secondary py-4">Sin pacientes aun.</td></tr>
        <?php else: ?>
        <?php foreach ($pacientes as $paciente): ?>
        <tr>
          <td>#<?= (int) $paciente['id'] ?></td>
          <td class="fw-semibold"><?= esc($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></td>
          <td><?= esc((string) $paciente['telefono']) ?></td>
          <td><?= esc((string) $paciente['email']) ?></td>
          <td><?= esc((string) $paciente['creado_en']) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarPaciente<?= (int) $paciente['id'] ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
              </button>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="eliminar_paciente">
                <input type="hidden" name="id" value="<?= (int) $paciente['id'] ?>">
                <button class="btn btn-sm btn-outline-danger js-confirm" data-confirm="¿Eliminar paciente #<?= (int) $paciente['id'] ?>? Esta accion eliminara citas, pagos, historial y recordatorios asociados.">
                  <i class="bi bi-trash me-1"></i>Eliminar
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal fade paciente-modal" id="modalNuevoPaciente" tabindex="-1" aria-labelledby="modalNuevoPacienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-2">
        <div class="d-flex align-items-center gap-2">
          <span class="paciente-modal-icon"><i class="bi bi-person-vcard"></i></span>
          <div>
            <h2 class="modal-title h5 mb-0" id="modalNuevoPacienteLabel">Nuevo paciente</h2>
            <small class="text-secondary">Completa los datos para registrar la historia inicial.</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-primary btn-sm" type="submit" form="formNuevoPaciente">
            <i class="bi bi-check2-square me-1"></i>Guardar paciente
          </button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
      </div>
      <form method="POST" id="formNuevoPaciente">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_paciente">
        <div class="modal-body">
          <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
            <h3 class="h6 mb-3">Informacion personal</h3>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nombres</label>
                <input name="nombres" value="<?= esc($formNombres) ?>" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Apellidos</label>
                <input name="apellidos" value="<?= esc($formApellidos) ?>" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Fecha nacimiento</label>
                <input type="date" name="fecha_nacimiento" value="<?= esc($formFechaNacimiento) ?>" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Genero</label>
                <input name="genero" value="<?= esc($formGenero) ?>" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Telefono</label>
                <input name="telefono" value="<?= esc($formTelefono) ?>" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Correo</label>
                <input type="email" name="email" value="<?= esc($formEmail) ?>" class="form-control">
              </div>
            </div>
          </div>
          <div class="border rounded-3 p-3">
            <h3 class="h6 mb-3">Datos complementarios</h3>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Direccion</label>
                <input name="direccion" value="<?= esc($formDireccion) ?>" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Contacto emergencia</label>
                <input name="contacto_emergencia" value="<?= esc($formContactoEmergencia) ?>" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notas iniciales</label>
                <textarea name="notas_iniciales" class="form-control" rows="3" placeholder="Antecedentes relevantes, motivo de consulta, observaciones iniciales."><?= esc($formNotasIniciales) ?></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i>Guardar paciente
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($pacientes as $paciente): ?>
<div class="modal fade paciente-modal" id="modalEditarPaciente<?= (int) $paciente['id'] ?>" tabindex="-1" aria-labelledby="modalEditarPacienteLabel<?= (int) $paciente['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-2">
        <div class="d-flex align-items-center gap-2">
          <span class="paciente-modal-icon"><i class="bi bi-pencil-square"></i></span>
          <div>
            <h2 class="modal-title h5 mb-0" id="modalEditarPacienteLabel<?= (int) $paciente['id'] ?>">Editar paciente #<?= (int) $paciente['id'] ?></h2>
            <small class="text-secondary">Actualiza informacion del paciente.</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_paciente">
        <input type="hidden" name="id" value="<?= (int) $paciente['id'] ?>">
        <div class="modal-body">
          <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
            <h3 class="h6 mb-3">Informacion personal</h3>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nombres</label>
                <input name="nombres" value="<?= esc((string) $paciente['nombres']) ?>" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Apellidos</label>
                <input name="apellidos" value="<?= esc((string) $paciente['apellidos']) ?>" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Fecha nacimiento</label>
                <input type="date" name="fecha_nacimiento" value="<?= esc((string) ($paciente['fecha_nacimiento'] ?? '')) ?>" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Genero</label>
                <input name="genero" value="<?= esc((string) ($paciente['genero'] ?? '')) ?>" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Telefono</label>
                <input name="telefono" value="<?= esc((string) ($paciente['telefono'] ?? '')) ?>" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Correo</label>
                <input type="email" name="email" value="<?= esc((string) ($paciente['email'] ?? '')) ?>" class="form-control">
              </div>
            </div>
          </div>
          <div class="border rounded-3 p-3">
            <h3 class="h6 mb-3">Datos complementarios</h3>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Direccion</label>
                <input name="direccion" value="<?= esc((string) ($paciente['direccion'] ?? '')) ?>" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Contacto emergencia</label>
                <input name="contacto_emergencia" value="<?= esc((string) ($paciente['contacto_emergencia'] ?? '')) ?>" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notas iniciales</label>
                <textarea name="notas_iniciales" class="form-control" rows="3"><?= esc((string) ($paciente['notas_iniciales'] ?? '')) ?></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-2">
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

<?php if ($autoOpenModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalElement = document.getElementById('modalNuevoPaciente');
  if (!modalElement) {
    return;
  }
  var modal = new bootstrap.Modal(modalElement);
  modal.show();
});
</script>
<?php endif; ?>
<?php admin_footer(); ?>
