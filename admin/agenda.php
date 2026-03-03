<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../services/reminder_service.php';

require_admin_roles(['admin', 'terapeuta', 'recepcion']);

$flash = '';
$flashType = 'info';

$profesionales = $pdo->query(
    "SELECT nombre, rol
     FROM usuarios_admin
     WHERE activo = 1 AND rol IN ('admin', 'terapeuta')
     ORDER BY nombre"
)->fetchAll();

$serviciosDisponibles = $pdo->query(
    'SELECT nombre FROM servicios WHERE activo = 1 ORDER BY orden, nombre'
)->fetchAll();

$profesionalNombres = array_column($profesionales, 'nombre');
$servicioNombres = array_column($serviciosDisponibles, 'nombre');

$formPacienteId = 0;
$formProfesional = '';
$formServicio = '';
$formFecha = '';
$formHora = '';
$formDuracion = 45;
$formModalidad = 'presencial';
$formEstado = 'programada';
$formObservaciones = '';
$autoOpenModal = false;

function agenda_datetime_valid(string $format, string $value): bool
{
    $dt = DateTime::createFromFormat($format, $value);
    return $dt !== false && $dt->format($format) === $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'crear_cita') {
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $profesional = trim($_POST['profesional'] ?? '');
        $servicio = trim($_POST['servicio'] ?? '');
        $fecha = trim($_POST['fecha'] ?? '');
        $hora = trim($_POST['hora'] ?? '');
        $duracion = (int) ($_POST['duracion_minutos'] ?? 45);
        $modalidad = trim($_POST['modalidad'] ?? 'presencial');
        $estado = trim($_POST['estado'] ?? 'programada');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $modalidadesPermitidas = ['presencial', 'online'];
        $estadosPermitidos = ['programada', 'confirmada', 'atendida', 'cancelada'];
        if (!in_array($modalidad, $modalidadesPermitidas, true)) {
            $modalidad = 'presencial';
        }
        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = 'programada';
        }
        if ($duracion < 15 || $duracion > 240) {
            $duracion = 45;
        }

        $formPacienteId = $pacienteId;
        $formProfesional = $profesional;
        $formServicio = $servicio;
        $formFecha = $fecha;
        $formHora = $hora;
        $formDuracion = $duracion > 0 ? $duracion : 45;
        $formModalidad = $modalidad;
        $formEstado = $estado;
        $formObservaciones = $observaciones;

        $fechaValida = agenda_datetime_valid('Y-m-d', $fecha);
        $horaValida = agenda_datetime_valid('H:i', $hora);

        if ($pacienteId <= 0 || $profesional === '' || $servicio === '' || $fecha === '' || $hora === '') {
            $flash = 'Completa los campos obligatorios de la cita.';
            $flashType = 'danger';
        } elseif (!$fechaValida || !$horaValida) {
            $flash = 'Fecha u hora no tienen un formato valido.';
            $flashType = 'danger';
        } elseif (!in_array($profesional, $profesionalNombres, true)) {
            $flash = 'Selecciona un profesional valido de la lista.';
            $flashType = 'danger';
        } elseif (!in_array($servicio, $servicioNombres, true)) {
            $flash = 'Selecciona un servicio valido de la lista.';
            $flashType = 'danger';
        } else {
            $overlapStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM citas_clinicas
                 WHERE profesional = ?
                   AND fecha = ?
                   AND estado IN ('programada','confirmada')
                   AND TIME_TO_SEC(?) < TIME_TO_SEC(hora) + (duracion_minutos * 60)
                   AND TIME_TO_SEC(?) + (? * 60) > TIME_TO_SEC(hora)"
            );
            $overlapStmt->execute([$profesional, $fecha, $hora, $hora, $duracion]);
            $hayCruce = (int) $overlapStmt->fetchColumn() > 0;

            if ($hayCruce) {
                $flash = 'Ese profesional ya tiene una cita en ese horario.';
                $flashType = 'danger';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO citas_clinicas (paciente_id, profesional, servicio, fecha, hora, duracion_minutos, modalidad, estado, observaciones)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$pacienteId, $profesional, $servicio, $fecha, $hora, $duracion, $modalidad, $estado, $observaciones]);
                $citaId = (int) $pdo->lastInsertId();

                $creados = create_reminders_for_cita($pdo, $citaId, true, true);
                $flash = 'Cita creada. Recordatorios generados: ' . $creados . '.';
                $flashType = 'success';
            }
        }
    }

    if ($action === 'editar_cita') {
        $id = (int) ($_POST['id'] ?? 0);
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $profesional = trim($_POST['profesional'] ?? '');
        $servicio = trim($_POST['servicio'] ?? '');
        $fecha = trim($_POST['fecha'] ?? '');
        $hora = trim($_POST['hora'] ?? '');
        $duracion = (int) ($_POST['duracion_minutos'] ?? 45);
        $modalidad = trim($_POST['modalidad'] ?? 'presencial');
        $estado = trim($_POST['estado'] ?? 'programada');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $modalidadesPermitidas = ['presencial', 'online'];
        $estadosPermitidos = ['programada', 'confirmada', 'atendida', 'cancelada'];
        if (!in_array($modalidad, $modalidadesPermitidas, true)) {
            $modalidad = 'presencial';
        }
        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = 'programada';
        }
        if ($duracion < 15 || $duracion > 240) {
            $duracion = 45;
        }

        $fechaValida = agenda_datetime_valid('Y-m-d', $fecha);
        $horaValida = agenda_datetime_valid('H:i', $hora);

        if ($id <= 0 || $pacienteId <= 0 || $profesional === '' || $servicio === '' || $fecha === '' || $hora === '') {
            $flash = 'Completa los campos obligatorios para editar.';
            $flashType = 'danger';
        } elseif (!$fechaValida || !$horaValida) {
            $flash = 'Fecha u hora no tienen un formato valido.';
            $flashType = 'danger';
        } elseif (!in_array($profesional, $profesionalNombres, true)) {
            $flash = 'Selecciona un profesional valido de la lista.';
            $flashType = 'danger';
        } elseif (!in_array($servicio, $servicioNombres, true)) {
            $flash = 'Selecciona un servicio valido de la lista.';
            $flashType = 'danger';
        } else {
            $overlapStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM citas_clinicas
                 WHERE profesional = ?
                   AND fecha = ?
                   AND estado IN ('programada','confirmada')
                   AND id <> ?
                   AND TIME_TO_SEC(?) < TIME_TO_SEC(hora) + (duracion_minutos * 60)
                   AND TIME_TO_SEC(?) + (? * 60) > TIME_TO_SEC(hora)"
            );
            $overlapStmt->execute([$profesional, $fecha, $id, $hora, $hora, $duracion]);
            $hayCruce = (int) $overlapStmt->fetchColumn() > 0;

            if ($hayCruce) {
                $flash = 'Ese profesional ya tiene una cita en ese horario.';
                $flashType = 'danger';
            } else {
                $update = $pdo->prepare(
                    'UPDATE citas_clinicas
                     SET paciente_id = ?, profesional = ?, servicio = ?, fecha = ?, hora = ?, duracion_minutos = ?, modalidad = ?, estado = ?, observaciones = ?
                     WHERE id = ?'
                );
                $update->execute([$pacienteId, $profesional, $servicio, $fecha, $hora, $duracion, $modalidad, $estado, $observaciones, $id]);
                $flash = 'Cita actualizada correctamente.';
                $flashType = 'success';
            }
        }
    }

    if ($action === 'eliminar_cita') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $delete = $pdo->prepare('DELETE FROM citas_clinicas WHERE id = ?');
            $delete->execute([$id]);
            $flash = 'Cita eliminada correctamente.';
            $flashType = 'success';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $flashType === 'danger') {
    $autoOpenModal = true;
}

$pacientes = $pdo->query('SELECT id, nombres, apellidos FROM pacientes ORDER BY nombres, apellidos')->fetchAll();

$citas = $pdo->query(
    "SELECT cc.id, cc.paciente_id, cc.fecha, cc.hora, cc.servicio, cc.profesional, cc.duracion_minutos, cc.modalidad, cc.estado, cc.observaciones, p.nombres, p.apellidos
     FROM citas_clinicas cc
     INNER JOIN pacientes p ON p.id = cc.paciente_id
     ORDER BY cc.fecha DESC, cc.hora DESC
     LIMIT 200"
)->fetchAll();

admin_header('Agenda clinica', 'agenda', 'Programacion de sesiones y control de cruces.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashType) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
    <div>
      <h2 class="h5 mb-0">Agenda registrada</h2>
      <p class="text-secondary mb-0">Programacion de sesiones y control de cruces.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($citas) ?> citas</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevaCita">
        <i class="bi bi-calendar-plus me-1"></i>Nueva cita
      </button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Paciente</th>
          <th>Servicio</th>
          <th>Profesional</th>
          <th>Modalidad</th>
          <th>Estado</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$citas): ?>
        <tr><td colspan="8" class="text-center text-secondary py-4">Sin citas registradas.</td></tr>
        <?php else: ?>
        <?php foreach ($citas as $cita): ?>
        <tr>
          <td><?= esc((string) $cita['fecha']) ?></td>
          <td><?= esc(substr((string) $cita['hora'], 0, 5)) ?></td>
          <td class="fw-semibold"><?= esc($cita['nombres'] . ' ' . $cita['apellidos']) ?></td>
          <td><?= esc((string) $cita['servicio']) ?></td>
          <td><?= esc((string) $cita['profesional']) ?></td>
          <td><?= esc((string) $cita['modalidad']) ?></td>
          <td><span class="badge badge-soft"><?= esc((string) $cita['estado']) ?></span></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarCita<?= (int) $cita['id'] ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
              </button>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="eliminar_cita">
                <input type="hidden" name="id" value="<?= (int) $cita['id'] ?>">
                <button class="btn btn-sm btn-outline-danger js-confirm" data-confirm="¿Eliminar cita #<?= (int) $cita['id'] ?>?">
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

<div class="modal fade agenda-modal" id="modalNuevaCita" tabindex="-1" aria-labelledby="modalNuevaCitaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-2">
        <div class="d-flex align-items-center gap-2">
          <span class="agenda-modal-icon"><i class="bi bi-calendar-heart"></i></span>
          <div>
            <h2 class="modal-title h5 mb-0" id="modalNuevaCitaLabel">Nueva cita clinica</h2>
            <small class="text-secondary">Completa los datos para registrar la sesion.</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-primary btn-sm" type="submit" form="formNuevaCita">
            <i class="bi bi-plus-circle me-1"></i>Agregar cita
          </button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
      </div>
      <form method="POST" id="formNuevaCita">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_cita">
        <div class="modal-body">
          <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
            <h3 class="h6 mb-3">Datos de la cita</h3>
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold">Paciente</label>
                <select name="paciente_id" class="form-select" required>
                  <option value="">Selecciona paciente</option>
                  <?php foreach ($pacientes as $paciente): ?>
                  <option value="<?= (int) $paciente['id'] ?>" <?= ((int) $paciente['id'] === $formPacienteId) ? 'selected' : '' ?>>
                    <?= esc($paciente['nombres'] . ' ' . $paciente['apellidos']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold">Profesional</label>
                <select name="profesional" class="form-select" required>
                  <option value="">Selecciona profesional</option>
                  <?php foreach ($profesionales as $profesionalItem): ?>
                  <?php $nombreProfesional = (string) $profesionalItem['nombre']; ?>
                  <?php $rolProfesional = (string) ($profesionalItem['rol'] ?? ''); ?>
                  <?php $etiquetaProfesional = $nombreProfesional . ($rolProfesional === 'terapeuta' ? ' - Psicologa Clinica' : ''); ?>
                  <option value="<?= esc($nombreProfesional) ?>" <?= ($formProfesional === $nombreProfesional) ? 'selected' : '' ?>>
                    <?= esc($etiquetaProfesional) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Servicio</label>
                <select name="servicio" class="form-select" required>
                  <option value="">Selecciona servicio</option>
                  <?php foreach ($serviciosDisponibles as $servicioItem): ?>
                  <?php $nombreServicio = (string) $servicioItem['nombre']; ?>
                  <option value="<?= esc($nombreServicio) ?>" <?= ($formServicio === $nombreServicio) ? 'selected' : '' ?>>
                    <?= esc($nombreServicio) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="border rounded-3 p-3 mb-3">
            <h3 class="h6 mb-3">Programacion</h3>
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Fecha</label>
                <input type="date" name="fecha" value="<?= esc($formFecha) ?>" class="form-control" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Hora</label>
                <input type="time" name="hora" value="<?= esc($formHora) ?>" class="form-control" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Duracion</label>
                <div class="input-group">
                  <input type="number" min="15" step="5" name="duracion_minutos" value="<?= (int) $formDuracion ?>" class="form-control" required>
                  <span class="input-group-text">min</span>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Modalidad</label>
                <select name="modalidad" class="form-select">
                  <option value="presencial" <?= ($formModalidad === 'presencial') ? 'selected' : '' ?>>Presencial</option>
                  <option value="online" <?= ($formModalidad === 'online') ? 'selected' : '' ?>>Online</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Estado</label>
                <select name="estado" class="form-select">
                  <option value="programada" <?= ($formEstado === 'programada') ? 'selected' : '' ?>>Programada</option>
                  <option value="confirmada" <?= ($formEstado === 'confirmada') ? 'selected' : '' ?>>Confirmada</option>
                  <option value="atendida" <?= ($formEstado === 'atendida') ? 'selected' : '' ?>>Atendida</option>
                  <option value="cancelada" <?= ($formEstado === 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                </select>
              </div>
            </div>
          </div>

          <div class="border rounded-3 p-3">
            <h3 class="h6 mb-3">Observaciones</h3>
            <div class="row g-3">
              <div class="col-12">
                <textarea name="observaciones" class="form-control" rows="3" placeholder="Notas clinicas, acuerdos o detalles de la sesion."><?= esc($formObservaciones) ?></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-calendar-plus me-1"></i>Agregar cita
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($citas as $cita): ?>
<div class="modal fade agenda-modal" id="modalEditarCita<?= (int) $cita['id'] ?>" tabindex="-1" aria-labelledby="modalEditarCitaLabel<?= (int) $cita['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-2">
        <div class="d-flex align-items-center gap-2">
          <span class="agenda-modal-icon"><i class="bi bi-pencil-square"></i></span>
          <div>
            <h2 class="modal-title h5 mb-0" id="modalEditarCitaLabel<?= (int) $cita['id'] ?>">Editar cita #<?= (int) $cita['id'] ?></h2>
            <small class="text-secondary">Actualiza datos de la sesion clinica.</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_cita">
        <input type="hidden" name="id" value="<?= (int) $cita['id'] ?>">
        <div class="modal-body">
          <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
            <h3 class="h6 mb-3">Datos de la cita</h3>
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold">Paciente</label>
                <select name="paciente_id" class="form-select" required>
                  <option value="">Selecciona paciente</option>
                  <?php foreach ($pacientes as $paciente): ?>
                  <option value="<?= (int) $paciente['id'] ?>" <?= ((int) $paciente['id'] === (int) $cita['paciente_id']) ? 'selected' : '' ?>>
                    <?= esc($paciente['nombres'] . ' ' . $paciente['apellidos']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold">Profesional</label>
                <select name="profesional" class="form-select" required>
                  <option value="">Selecciona profesional</option>
                  <?php foreach ($profesionales as $profesionalItem): ?>
                  <?php $nombreProfesional = (string) $profesionalItem['nombre']; ?>
                  <?php $rolProfesional = (string) ($profesionalItem['rol'] ?? ''); ?>
                  <?php $etiquetaProfesional = $nombreProfesional . ($rolProfesional === 'terapeuta' ? ' - Psicologa Clinica' : ''); ?>
                  <option value="<?= esc($nombreProfesional) ?>" <?= ((string) $cita['profesional'] === $nombreProfesional) ? 'selected' : '' ?>>
                    <?= esc($etiquetaProfesional) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Servicio</label>
                <select name="servicio" class="form-select" required>
                  <option value="">Selecciona servicio</option>
                  <?php foreach ($serviciosDisponibles as $servicioItem): ?>
                  <?php $nombreServicio = (string) $servicioItem['nombre']; ?>
                  <option value="<?= esc($nombreServicio) ?>" <?= ((string) $cita['servicio'] === $nombreServicio) ? 'selected' : '' ?>>
                    <?= esc($nombreServicio) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="border rounded-3 p-3 mb-3">
            <h3 class="h6 mb-3">Programacion</h3>
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Fecha</label>
                <input type="date" name="fecha" value="<?= esc((string) $cita['fecha']) ?>" class="form-control" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Hora</label>
                <input type="time" name="hora" value="<?= esc(substr((string) $cita['hora'], 0, 5)) ?>" class="form-control" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Duracion</label>
                <div class="input-group">
                  <input type="number" min="15" step="5" name="duracion_minutos" value="<?= (int) $cita['duracion_minutos'] ?>" class="form-control" required>
                  <span class="input-group-text">min</span>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Modalidad</label>
                <select name="modalidad" class="form-select">
                  <option value="presencial" <?= ((string) $cita['modalidad'] === 'presencial') ? 'selected' : '' ?>>Presencial</option>
                  <option value="online" <?= ((string) $cita['modalidad'] === 'online') ? 'selected' : '' ?>>Online</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Estado</label>
                <select name="estado" class="form-select">
                  <option value="programada" <?= ((string) $cita['estado'] === 'programada') ? 'selected' : '' ?>>Programada</option>
                  <option value="confirmada" <?= ((string) $cita['estado'] === 'confirmada') ? 'selected' : '' ?>>Confirmada</option>
                  <option value="atendida" <?= ((string) $cita['estado'] === 'atendida') ? 'selected' : '' ?>>Atendida</option>
                  <option value="cancelada" <?= ((string) $cita['estado'] === 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                </select>
              </div>
            </div>
          </div>
          <div class="border rounded-3 p-3">
            <h3 class="h6 mb-3">Observaciones</h3>
            <textarea name="observaciones" class="form-control" rows="3"><?= esc((string) ($cita['observaciones'] ?? '')) ?></textarea>
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
  var modalElement = document.getElementById('modalNuevaCita');
  if (!modalElement) {
    return;
  }
  var modal = new bootstrap.Modal(modalElement);
  modal.show();
});
</script>
<?php endif; ?>
<?php admin_footer(); ?>
