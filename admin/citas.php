<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin', 'terapeuta', 'recepcion']);

$flash = '';
$flashClass = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'convertir_paciente') {
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT nombre, nombres, apellidos, fecha_nacimiento, genero, email, telefono, direccion, contacto_emergencia, notas_iniciales, mensaje
             FROM citas
             WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$citaId]);
        $cita = $stmt->fetch();

        if ($cita) {
            $nombres = trim((string) ($cita['nombres'] ?? ''));
            $apellidos = trim((string) ($cita['apellidos'] ?? ''));
            if ($nombres === '' && $apellidos === '') {
                $nombreCompleto = trim((string) $cita['nombre']);
                $partes = preg_split('/\s+/', $nombreCompleto) ?: [];
                $nombres = (string) array_shift($partes);
                $apellidos = trim(implode(' ', $partes));
            }
            if ($apellidos === '') {
                $apellidos = 'Sin apellido';
            }

            $email = trim((string) $cita['email']);
            $telefono = trim((string) $cita['telefono']);
            $existingPacienteId = 0;

            if ($email !== '') {
                $existsByEmail = $pdo->prepare('SELECT id FROM pacientes WHERE email = ? ORDER BY id ASC LIMIT 1');
                $existsByEmail->execute([$email]);
                $existingPacienteId = (int) ($existsByEmail->fetchColumn() ?: 0);
            }

            if ($existingPacienteId === 0 && $telefono !== '') {
                $existsByPhone = $pdo->prepare(
                    'SELECT id FROM pacientes WHERE telefono = ? AND nombres = ? AND apellidos = ? ORDER BY id ASC LIMIT 1'
                );
                $existsByPhone->execute([
                    $telefono,
                    $nombres !== '' ? $nombres : 'Sin nombre',
                    $apellidos,
                ]);
                $existingPacienteId = (int) ($existsByPhone->fetchColumn() ?: 0);
            }

            if ($existingPacienteId > 0) {
                $flash = 'La solicitud ya corresponde a un paciente existente. No se creo duplicado.';
                $flashClass = 'info';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO pacientes (nombres, apellidos, fecha_nacimiento, genero, telefono, email, direccion, contacto_emergencia, notas_iniciales)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $fechaNacimiento = trim((string) ($cita['fecha_nacimiento'] ?? ''));
                $fechaNacimientoValue = null;
                if ($fechaNacimiento !== '') {
                    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
                    if ($fechaObj !== false && $fechaObj->format('Y-m-d') === $fechaNacimiento) {
                        $fechaNacimientoValue = $fechaNacimiento;
                    }
                }

                $notaImport = trim((string) ($cita['notas_iniciales'] ?? ''));
                $mensajeWeb = trim((string) ($cita['mensaje'] ?? ''));
                $notaFinal = $notaImport;
                if ($mensajeWeb !== '') {
                    $notaFinal .= ($notaFinal !== '' ? "\n\n" : '') . 'Importado desde solicitud web: ' . $mensajeWeb;
                }

                $insert->execute([
                    $nombres !== '' ? $nombres : 'Sin nombre',
                    $apellidos,
                    $fechaNacimientoValue,
                    trim((string) ($cita['genero'] ?? '')),
                    $telefono,
                    $email,
                    trim((string) ($cita['direccion'] ?? '')),
                    trim((string) ($cita['contacto_emergencia'] ?? '')),
                    $notaFinal,
                ]);
                $flash = 'Solicitud convertida a paciente correctamente.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'editar_solicitud') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $nombres = trim((string) ($_POST['nombres'] ?? ''));
        $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
        $fechaNacimiento = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
        $genero = trim((string) ($_POST['genero'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $contactoEmergencia = trim((string) ($_POST['contacto_emergencia'] ?? ''));
        $servicio = trim((string) ($_POST['servicio'] ?? ''));
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        $notasIniciales = trim((string) ($_POST['notas_iniciales'] ?? ''));

        if ($id <= 0 || $email === '') {
            $flash = 'Datos invalidos para editar solicitud.';
            $flashClass = 'danger';
        } else {
            $fechaNacimientoValue = null;
            if ($fechaNacimiento !== '') {
                $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
                if ($fechaObj !== false && $fechaObj->format('Y-m-d') === $fechaNacimiento) {
                    $fechaNacimientoValue = $fechaNacimiento;
                }
            }

            $update = $pdo->prepare(
                'UPDATE citas
                 SET nombre = ?, nombres = ?, apellidos = ?, fecha_nacimiento = ?, genero = ?, email = ?, telefono = ?, direccion = ?, contacto_emergencia = ?, notas_iniciales = ?, servicio = ?, mensaje = ?
                 WHERE id = ?'
            );
            $update->execute([
                $nombre,
                $nombres,
                $apellidos,
                $fechaNacimientoValue,
                $genero,
                $email,
                $telefono,
                $direccion,
                $contactoEmergencia,
                $notasIniciales,
                $servicio,
                $mensaje,
                $id,
            ]);
            $flash = 'Solicitud actualizada correctamente.';
            $flashClass = 'success';
        }
    }

    if ($action === 'eliminar_solicitud') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM citas WHERE id = ?');
            $stmt->execute([$id]);
            $flash = 'Solicitud eliminada correctamente.';
            $flashClass = 'success';
        }
    }
}

$stmt = $pdo->query(
    'SELECT id, nombre, nombres, apellidos, fecha_nacimiento, genero, email, telefono, direccion, contacto_emergencia, notas_iniciales, servicio, mensaje, creado_en
     FROM citas
     ORDER BY id DESC'
);
$citas = $stmt->fetchAll();

admin_header('Solicitudes web', 'solicitudes', 'Leads captados desde el formulario publico.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Solicitudes recibidas</h2>
    <span class="badge badge-soft"><?= count($citas) ?> solicitudes</span>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Email</th>
          <th>Telefono</th>
          <th>Servicio</th>
          <th>Mensaje</th>
          <th>Fecha</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$citas): ?>
        <tr><td colspan="8" class="text-center text-secondary py-4">No hay solicitudes registradas.</td></tr>
        <?php else: ?>
        <?php foreach ($citas as $cita): ?>
        <tr>
          <td>#<?= (int) $cita['id'] ?></td>
          <td class="fw-semibold"><?= esc(trim(((string) ($cita['nombres'] ?? '')) . ' ' . ((string) ($cita['apellidos'] ?? ''))) !== '' ? trim(((string) ($cita['nombres'] ?? '')) . ' ' . ((string) ($cita['apellidos'] ?? ''))) : (string) $cita['nombre']) ?></td>
          <td><?= esc((string) $cita['email']) ?></td>
          <td><?= esc((string) $cita['telefono']) ?></td>
          <td><?= esc((string) $cita['servicio']) ?></td>
          <td class="small text-truncate-2" style="max-width: 260px;" title="<?= esc((string) $cita['mensaje']) ?>"><?= esc((string) $cita['mensaje']) ?></td>
          <td><?= esc((string) $cita['creado_en']) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="convertir_paciente">
                <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
                <button type="submit" class="btn btn-sm btn-primary">Crear paciente</button>
              </form>
              <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarSolicitud<?= (int) $cita['id'] ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
              </button>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="eliminar_solicitud">
                <input type="hidden" name="id" value="<?= (int) $cita['id'] ?>">
                <button class="btn btn-sm btn-outline-danger js-confirm" data-confirm="¿Eliminar solicitud #<?= (int) $cita['id'] ?>?">
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

<?php foreach ($citas as $cita): ?>
<div class="modal fade" id="modalEditarSolicitud<?= (int) $cita['id'] ?>" tabindex="-1" aria-labelledby="modalEditarSolicitudLabel<?= (int) $cita['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEditarSolicitudLabel<?= (int) $cita['id'] ?>">Editar solicitud #<?= (int) $cita['id'] ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_solicitud">
        <input type="hidden" name="id" value="<?= (int) $cita['id'] ?>">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Nombre completo</label>
              <input name="nombre" value="<?= esc((string) ($cita['nombre'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Servicio</label>
              <input name="servicio" value="<?= esc((string) ($cita['servicio'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombres</label>
              <input name="nombres" value="<?= esc((string) ($cita['nombres'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Apellidos</label>
              <input name="apellidos" value="<?= esc((string) ($cita['apellidos'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha nacimiento</label>
              <input type="date" name="fecha_nacimiento" value="<?= esc((string) ($cita['fecha_nacimiento'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Genero</label>
              <input name="genero" value="<?= esc((string) ($cita['genero'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefono</label>
              <input name="telefono" value="<?= esc((string) ($cita['telefono'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" value="<?= esc((string) ($cita['email'] ?? '')) ?>" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Direccion</label>
              <input name="direccion" value="<?= esc((string) ($cita['direccion'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Contacto emergencia</label>
              <input name="contacto_emergencia" value="<?= esc((string) ($cita['contacto_emergencia'] ?? '')) ?>" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Notas iniciales</label>
              <textarea name="notas_iniciales" class="form-control" rows="2"><?= esc((string) ($cita['notas_iniciales'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Mensaje</label>
              <textarea name="mensaje" class="form-control" rows="3"><?= esc((string) ($cita['mensaje'] ?? '')) ?></textarea>
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
<?php admin_footer(); ?>

