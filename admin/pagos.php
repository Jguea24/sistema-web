<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin', 'terapeuta', 'recepcion']);

$flash = '';
$flashClass = 'success';
$autoOpenModal = false;

$formPacienteId = 0;
$formCitaId = 0;
$formMonto = '';
$formMetodo = 'efectivo';
$formEstado = 'pendiente';
$formReferencia = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'crear_pago') {
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $citaId = (int) ($_POST['cita_clinica_id'] ?? 0);
        $monto = (float) ($_POST['monto'] ?? 0);
        $metodo = trim($_POST['metodo_pago'] ?? 'efectivo');
        $estado = trim($_POST['estado'] ?? 'pendiente');
        $referencia = trim($_POST['referencia_externa'] ?? '');
        $metodosPermitidos = ['efectivo', 'transferencia', 'tarjeta', 'pasarela_online'];
        $estadosPermitidos = ['pendiente', 'pagado', 'rechazado'];
        if (!in_array($metodo, $metodosPermitidos, true)) {
            $metodo = 'efectivo';
        }
        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = 'pendiente';
        }

        $formPacienteId = $pacienteId;
        $formCitaId = $citaId;
        $formMonto = isset($_POST['monto']) ? trim((string) $_POST['monto']) : '';
        $formMetodo = $metodo;
        $formEstado = $estado;
        $formReferencia = $referencia;

        if ($pacienteId > 0 && $monto > 0) {
            if ($citaId > 0) {
                $citaStmt = $pdo->prepare('SELECT paciente_id FROM citas_clinicas WHERE id = ? LIMIT 1');
                $citaStmt->execute([$citaId]);
                $citaPacienteId = (int) ($citaStmt->fetchColumn() ?: 0);
                if ($citaPacienteId === 0) {
                    $flash = 'La cita asociada no existe.';
                    $flashClass = 'danger';
                    $autoOpenModal = true;
                } elseif ($citaPacienteId !== $pacienteId) {
                    $flash = 'La cita seleccionada no corresponde al paciente elegido.';
                    $flashClass = 'danger';
                    $autoOpenModal = true;
                }
            }

            if ($flashClass !== 'danger') {
            $fechaPago = $estado === 'pagado' ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare(
                'INSERT INTO pagos (cita_clinica_id, paciente_id, monto, moneda, metodo_pago, estado, referencia_externa, fecha_pago)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $citaId > 0 ? $citaId : null,
                $pacienteId,
                $monto,
                'USD',
                $metodo,
                $estado,
                $referencia,
                $fechaPago,
            ]);
            $flash = 'Pago registrado correctamente.';
            $flashClass = 'success';
            }
        } else {
            $flash = 'Paciente y monto son obligatorios.';
            $flashClass = 'danger';
            $autoOpenModal = true;
        }
    }

    if ($action === 'actualizar_estado') {
        $pagoId = (int) ($_POST['pago_id'] ?? 0);
        $nuevoEstado = trim($_POST['nuevo_estado'] ?? 'pendiente');
        $estadosPermitidos = ['pendiente', 'pagado', 'rechazado'];
        if ($pagoId > 0 && in_array($nuevoEstado, $estadosPermitidos, true)) {
            $stmt = $pdo->prepare('UPDATE pagos SET estado = ?, fecha_pago = CASE WHEN ? = "pagado" THEN NOW() ELSE fecha_pago END WHERE id = ?');
            $stmt->execute([$nuevoEstado, $nuevoEstado, $pagoId]);
            $flash = 'Estado de pago actualizado.';
            $flashClass = 'success';
        } elseif ($pagoId > 0) {
            $flash = 'Estado de pago invalido.';
            $flashClass = 'danger';
        }
    }

    if ($action === 'editar_pago') {
        $id = (int) ($_POST['id'] ?? 0);
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $citaId = (int) ($_POST['cita_clinica_id'] ?? 0);
        $monto = (float) ($_POST['monto'] ?? 0);
        $metodo = trim($_POST['metodo_pago'] ?? 'efectivo');
        $estado = trim($_POST['estado'] ?? 'pendiente');
        $referencia = trim($_POST['referencia_externa'] ?? '');
        $metodosPermitidos = ['efectivo', 'transferencia', 'tarjeta', 'pasarela_online'];
        $estadosPermitidos = ['pendiente', 'pagado', 'rechazado'];
        if (!in_array($metodo, $metodosPermitidos, true)) {
            $metodo = 'efectivo';
        }
        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = 'pendiente';
        }

        if ($id <= 0 || $pacienteId <= 0 || $monto <= 0) {
            $flash = 'Datos invalidos para editar pago.';
            $flashClass = 'danger';
        } else {
            if ($citaId > 0) {
                $citaStmt = $pdo->prepare('SELECT paciente_id FROM citas_clinicas WHERE id = ? LIMIT 1');
                $citaStmt->execute([$citaId]);
                $citaPacienteId = (int) ($citaStmt->fetchColumn() ?: 0);
                if ($citaPacienteId === 0) {
                    $flash = 'La cita asociada no existe.';
                    $flashClass = 'danger';
                } elseif ($citaPacienteId !== $pacienteId) {
                    $flash = 'La cita seleccionada no corresponde al paciente elegido.';
                    $flashClass = 'danger';
                }
            }

            if ($flashClass !== 'danger') {
                $update = $pdo->prepare(
                    'UPDATE pagos
                     SET cita_clinica_id = ?, paciente_id = ?, monto = ?, metodo_pago = ?, estado = ?, referencia_externa = ?,
                         fecha_pago = CASE
                           WHEN ? = "pagado" AND fecha_pago IS NULL THEN NOW()
                           WHEN ? <> "pagado" THEN NULL
                           ELSE fecha_pago
                         END
                     WHERE id = ?'
                );
                $update->execute([
                    $citaId > 0 ? $citaId : null,
                    $pacienteId,
                    $monto,
                    $metodo,
                    $estado,
                    $referencia,
                    $estado,
                    $estado,
                    $id,
                ]);
                $flash = 'Pago actualizado correctamente.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'eliminar_pago') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM pagos WHERE id = ?');
            $stmt->execute([$id]);
            $flash = 'Pago eliminado correctamente.';
            $flashClass = 'success';
        }
    }
}

$pacientes = $pdo->query('SELECT id, nombres, apellidos FROM pacientes ORDER BY nombres, apellidos')->fetchAll();
$citas = $pdo->query('SELECT id, paciente_id, fecha, hora FROM citas_clinicas ORDER BY fecha DESC, hora DESC LIMIT 250')->fetchAll();

$pagos = $pdo->query(
    "SELECT pa.id, pa.cita_clinica_id, pa.paciente_id, pa.monto, pa.moneda, pa.metodo_pago, pa.estado, pa.referencia_externa, pa.fecha_pago, pa.creado_en,
            p.nombres, p.apellidos
     FROM pagos pa
     INNER JOIN pacientes p ON p.id = pa.paciente_id
     ORDER BY pa.id DESC
     LIMIT 250"
)->fetchAll();

admin_header('Pagos', 'pagos', 'Registro de cobros y estado de transacciones.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
    <div>
      <h2 class="h5 mb-0">Movimientos</h2>
      <p class="text-secondary mb-0">Registro de cobros y estado de transacciones.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($pagos) ?> pagos</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoPago">
        <i class="bi bi-cash-coin me-1"></i>Nuevo pago
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Paciente</th>
          <th>Monto</th>
          <th>Metodo</th>
          <th>Estado</th>
          <th>Referencia</th>
          <th>Fecha pago</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$pagos): ?>
        <tr><td colspan="8" class="text-center text-secondary py-4">No hay pagos.</td></tr>
        <?php else: ?>
        <?php foreach ($pagos as $pago): ?>
        <tr>
          <td>#<?= (int) $pago['id'] ?></td>
          <td class="fw-semibold"><?= esc($pago['nombres'] . ' ' . $pago['apellidos']) ?></td>
          <td>$<?= number_format((float) $pago['monto'], 2) ?> <?= esc((string) $pago['moneda']) ?></td>
          <td><?= esc((string) $pago['metodo_pago']) ?></td>
          <td><span class="badge badge-soft"><?= esc((string) $pago['estado']) ?></span></td>
          <td><?= esc((string) $pago['referencia_externa']) ?></td>
          <td><?= esc((string) ($pago['fecha_pago'] ?? '')) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <form method="POST" class="d-flex gap-1">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="actualizar_estado">
                <input type="hidden" name="pago_id" value="<?= (int) $pago['id'] ?>">
                <select name="nuevo_estado" class="form-select form-select-sm">
                  <option value="pendiente" <?= ((string) $pago['estado'] === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                  <option value="pagado" <?= ((string) $pago['estado'] === 'pagado') ? 'selected' : '' ?>>Pagado</option>
                  <option value="rechazado" <?= ((string) $pago['estado'] === 'rechazado') ? 'selected' : '' ?>>Rechazado</option>
                </select>
                <button class="btn btn-sm btn-outline-primary">OK</button>
              </form>
              <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarPago<?= (int) $pago['id'] ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
              </button>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="eliminar_pago">
                <input type="hidden" name="id" value="<?= (int) $pago['id'] ?>">
                <button class="btn btn-sm btn-outline-danger js-confirm" data-confirm="¿Eliminar pago #<?= (int) $pago['id'] ?>?">
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

<div class="modal fade" id="modalNuevoPago" tabindex="-1" aria-labelledby="modalNuevoPagoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalNuevoPagoLabel">Registrar pago</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_pago">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Paciente</label>
              <select name="paciente_id" class="form-select" required>
                <option value="">Selecciona paciente</option>
                <?php foreach ($pacientes as $paciente): ?>
                <option value="<?= (int) $paciente['id'] ?>" <?= ((int) $paciente['id'] === $formPacienteId) ? 'selected' : '' ?>>
                  <?= esc($paciente['nombres'] . ' ' . $paciente['apellidos']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Cita asociada</label>
              <select name="cita_clinica_id" class="form-select">
                <option value="">Sin cita asociada</option>
                <?php foreach ($citas as $cita): ?>
                <option value="<?= (int) $cita['id'] ?>" <?= ((int) $cita['id'] === $formCitaId) ? 'selected' : '' ?>>
                  Cita #<?= (int) $cita['id'] ?> | <?= esc((string) $cita['fecha']) ?> <?= esc(substr((string) $cita['hora'], 0, 5)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Monto (USD)</label>
              <input type="number" step="0.01" min="0.01" name="monto" value="<?= esc($formMonto) ?>" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Metodo</label>
              <select name="metodo_pago" class="form-select">
                <option value="efectivo" <?= ($formMetodo === 'efectivo') ? 'selected' : '' ?>>Efectivo</option>
                <option value="transferencia" <?= ($formMetodo === 'transferencia') ? 'selected' : '' ?>>Transferencia</option>
                <option value="tarjeta" <?= ($formMetodo === 'tarjeta') ? 'selected' : '' ?>>Tarjeta</option>
                <option value="pasarela_online" <?= ($formMetodo === 'pasarela_online') ? 'selected' : '' ?>>Pasarela online</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Estado inicial</label>
              <select name="estado" class="form-select">
                <option value="pendiente" <?= ($formEstado === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                <option value="pagado" <?= ($formEstado === 'pagado') ? 'selected' : '' ?>>Pagado</option>
                <option value="rechazado" <?= ($formEstado === 'rechazado') ? 'selected' : '' ?>>Rechazado</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Referencia externa</label>
              <input name="referencia_externa" value="<?= esc($formReferencia) ?>" class="form-control" placeholder="Codigo de pasarela (opcional)">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i>Guardar pago
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($pagos as $pago): ?>
<div class="modal fade" id="modalEditarPago<?= (int) $pago['id'] ?>" tabindex="-1" aria-labelledby="modalEditarPagoLabel<?= (int) $pago['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEditarPagoLabel<?= (int) $pago['id'] ?>">Editar pago #<?= (int) $pago['id'] ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_pago">
        <input type="hidden" name="id" value="<?= (int) $pago['id'] ?>">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Paciente</label>
              <select name="paciente_id" class="form-select" required>
                <option value="">Selecciona paciente</option>
                <?php foreach ($pacientes as $paciente): ?>
                <option value="<?= (int) $paciente['id'] ?>" <?= ((int) $paciente['id'] === (int) $pago['paciente_id']) ? 'selected' : '' ?>>
                  <?= esc($paciente['nombres'] . ' ' . $paciente['apellidos']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Cita asociada</label>
              <select name="cita_clinica_id" class="form-select">
                <option value="">Sin cita asociada</option>
                <?php foreach ($citas as $cita): ?>
                <option value="<?= (int) $cita['id'] ?>" <?= ((int) $cita['id'] === (int) ($pago['cita_clinica_id'] ?? 0)) ? 'selected' : '' ?>>
                  Cita #<?= (int) $cita['id'] ?> | <?= esc((string) $cita['fecha']) ?> <?= esc(substr((string) $cita['hora'], 0, 5)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Monto (USD)</label>
              <input type="number" step="0.01" min="0.01" name="monto" value="<?= esc((string) $pago['monto']) ?>" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Metodo</label>
              <select name="metodo_pago" class="form-select">
                <option value="efectivo" <?= ((string) $pago['metodo_pago'] === 'efectivo') ? 'selected' : '' ?>>Efectivo</option>
                <option value="transferencia" <?= ((string) $pago['metodo_pago'] === 'transferencia') ? 'selected' : '' ?>>Transferencia</option>
                <option value="tarjeta" <?= ((string) $pago['metodo_pago'] === 'tarjeta') ? 'selected' : '' ?>>Tarjeta</option>
                <option value="pasarela_online" <?= ((string) $pago['metodo_pago'] === 'pasarela_online') ? 'selected' : '' ?>>Pasarela online</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="pendiente" <?= ((string) $pago['estado'] === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                <option value="pagado" <?= ((string) $pago['estado'] === 'pagado') ? 'selected' : '' ?>>Pagado</option>
                <option value="rechazado" <?= ((string) $pago['estado'] === 'rechazado') ? 'selected' : '' ?>>Rechazado</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Referencia externa</label>
              <input name="referencia_externa" value="<?= esc((string) $pago['referencia_externa']) ?>" class="form-control">
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

<?php if ($autoOpenModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalElement = document.getElementById('modalNuevoPago');
  if (!modalElement) {
    return;
  }
  var modal = new bootstrap.Modal(modalElement);
  modal.show();
});
</script>
<?php endif; ?>
<?php admin_footer(); ?>

