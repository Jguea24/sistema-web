<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin', 'terapeuta']);

$flash = '';
$flashClass = 'success';
$autoOpenModal = false;

$formPacienteId = 0;
$formCitaId = 0;
$formTipoNota = 'evolucion';
$formContenido = '';
$formConfidencial = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'crear_nota') {
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $citaId = (int) ($_POST['cita_clinica_id'] ?? 0);
        $tipoNota = trim($_POST['tipo_nota'] ?? 'evolucion');
        $contenido = trim($_POST['contenido'] ?? '');
        $confidencial = isset($_POST['confidencial']) ? 1 : 0;

        $formPacienteId = $pacienteId;
        $formCitaId = $citaId;
        $formTipoNota = $tipoNota;
        $formContenido = $contenido;
        $formConfidencial = $confidencial;
        $tiposPermitidos = ['evolucion', 'evaluacion', 'diagnostico', 'plan_terapeutico'];
        if (!in_array($tipoNota, $tiposPermitidos, true)) {
            $tipoNota = 'evolucion';
            $formTipoNota = 'evolucion';
        }

        if ($pacienteId > 0 && $contenido !== '') {
            $puedeInsertar = true;
            if ($citaId > 0) {
                $citaStmt = $pdo->prepare('SELECT paciente_id FROM citas_clinicas WHERE id = ? LIMIT 1');
                $citaStmt->execute([$citaId]);
                $citaPacienteId = (int) ($citaStmt->fetchColumn() ?: 0);
                if ($citaPacienteId === 0) {
                    $flash = 'La cita asociada no existe.';
                    $flashClass = 'danger';
                    $autoOpenModal = true;
                    $puedeInsertar = false;
                } elseif ($citaPacienteId !== $pacienteId) {
                    $flash = 'La cita seleccionada no pertenece al paciente elegido.';
                    $flashClass = 'danger';
                    $autoOpenModal = true;
                    $puedeInsertar = false;
                }
            }

            if ($puedeInsertar) {
                $stmt = $pdo->prepare(
                    'INSERT INTO historial_psicologico (paciente_id, cita_clinica_id, tipo_nota, contenido, confidencial, creado_por)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $pacienteId,
                    $citaId > 0 ? $citaId : null,
                    $tipoNota,
                    $contenido,
                    $confidencial,
                    get_admin_user_name(),
                ]);
                $flash = 'Nota clinica registrada.';
                $flashClass = 'success';
            }
        } else {
            $flash = 'Selecciona paciente y escribe la nota.';
            $flashClass = 'danger';
            $autoOpenModal = true;
        }
    }

    if ($action === 'editar_nota') {
        $id = (int) ($_POST['id'] ?? 0);
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $citaId = (int) ($_POST['cita_clinica_id'] ?? 0);
        $tipoNota = trim($_POST['tipo_nota'] ?? 'evolucion');
        $contenido = trim($_POST['contenido'] ?? '');
        $confidencial = isset($_POST['confidencial']) ? 1 : 0;
        $tiposPermitidos = ['evolucion', 'evaluacion', 'diagnostico', 'plan_terapeutico'];
        if (!in_array($tipoNota, $tiposPermitidos, true)) {
            $tipoNota = 'evolucion';
        }

        if ($id <= 0 || $pacienteId <= 0 || $contenido === '') {
            $flash = 'Datos invalidos para editar la nota.';
            $flashClass = 'danger';
        } else {
            $puedeActualizar = true;
            if ($citaId > 0) {
                $citaStmt = $pdo->prepare('SELECT paciente_id FROM citas_clinicas WHERE id = ? LIMIT 1');
                $citaStmt->execute([$citaId]);
                $citaPacienteId = (int) ($citaStmt->fetchColumn() ?: 0);
                if ($citaPacienteId === 0) {
                    $flash = 'La cita asociada no existe.';
                    $flashClass = 'danger';
                    $puedeActualizar = false;
                } elseif ($citaPacienteId !== $pacienteId) {
                    $flash = 'La cita seleccionada no pertenece al paciente elegido.';
                    $flashClass = 'danger';
                    $puedeActualizar = false;
                }
            }

            if ($puedeActualizar) {
                $update = $pdo->prepare(
                    'UPDATE historial_psicologico
                     SET paciente_id = ?, cita_clinica_id = ?, tipo_nota = ?, contenido = ?, confidencial = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $pacienteId,
                    $citaId > 0 ? $citaId : null,
                    $tipoNota,
                    $contenido,
                    $confidencial,
                    $id,
                ]);
                $flash = 'Nota clinica actualizada.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'eliminar_nota') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM historial_psicologico WHERE id = ?');
            $stmt->execute([$id]);
            $flash = 'Nota clinica eliminada.';
            $flashClass = 'success';
        }
    }
}

$pacientes = $pdo->query('SELECT id, nombres, apellidos FROM pacientes ORDER BY nombres, apellidos')->fetchAll();
$citas = $pdo->query(
    'SELECT id, fecha, hora, paciente_id FROM citas_clinicas ORDER BY fecha DESC, hora DESC LIMIT 300'
)->fetchAll();

$notas = $pdo->query(
    "SELECT h.id, h.paciente_id, h.cita_clinica_id, h.tipo_nota, h.contenido, h.confidencial, h.creado_por, h.creado_en, p.nombres, p.apellidos
     FROM historial_psicologico h
     INNER JOIN pacientes p ON p.id = h.paciente_id
     ORDER BY h.id DESC
     LIMIT 200"
)->fetchAll();

admin_header('Historial psicologico', 'historial', 'Registro privado de evolucion clinica.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
    <div>
      <h2 class="h5 mb-0">Notas registradas</h2>
      <p class="text-secondary mb-0">Registro privado de evolucion clinica.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($notas) ?> notas</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevaNota">
        <i class="bi bi-journal-plus me-1"></i>Nueva nota
      </button>
    </div>
  </div>

  <?php if (!$notas): ?>
  <p class="text-secondary mb-0">No hay notas registradas.</p>
  <?php else: ?>
  <div class="vstack gap-3">
    <?php foreach ($notas as $nota): ?>
    <article class="border rounded-3 p-3">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="badge badge-soft"><?= esc((string) $nota['tipo_nota']) ?></span>
        <?php if ((int) $nota['confidencial'] === 1): ?>
        <span class="badge text-bg-warning">Confidencial</span>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-warning ms-auto" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarNota<?= (int) $nota['id'] ?>">
          <i class="bi bi-pencil-square me-1"></i>Editar
        </button>
        <form method="POST">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="eliminar_nota">
          <input type="hidden" name="id" value="<?= (int) $nota['id'] ?>">
          <button class="btn btn-sm btn-outline-danger js-confirm" data-confirm="¿Eliminar nota #<?= (int) $nota['id'] ?>?">
            <i class="bi bi-trash me-1"></i>Eliminar
          </button>
        </form>
        <small class="text-secondary">
          <?= esc((string) $nota['creado_en']) ?> | <?= esc((string) $nota['creado_por']) ?>
        </small>
      </div>
      <h3 class="h6 fw-semibold mb-2"><?= esc($nota['nombres'] . ' ' . $nota['apellidos']) ?></h3>
      <p class="mb-0 small" style="white-space: pre-wrap;"><?= esc((string) $nota['contenido']) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<div class="modal fade" id="modalNuevaNota" tabindex="-1" aria-labelledby="modalNuevaNotaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalNuevaNotaLabel">Nueva nota</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_nota">
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
              <label class="form-label">Cita asociada (opcional)</label>
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
              <label class="form-label">Tipo de nota</label>
              <select name="tipo_nota" class="form-select">
                <option value="evolucion" <?= ($formTipoNota === 'evolucion') ? 'selected' : '' ?>>Evolucion</option>
                <option value="evaluacion" <?= ($formTipoNota === 'evaluacion') ? 'selected' : '' ?>>Evaluacion</option>
                <option value="diagnostico" <?= ($formTipoNota === 'diagnostico') ? 'selected' : '' ?>>Diagnostico</option>
                <option value="plan_terapeutico" <?= ($formTipoNota === 'plan_terapeutico') ? 'selected' : '' ?>>Plan terapeutico</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Contenido</label>
              <textarea name="contenido" class="form-control" rows="5" required><?= esc($formContenido) ?></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="confidencial" id="confidencial" <?= $formConfidencial === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="confidencial">Marcar como confidencial</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i>Guardar nota
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($notas as $nota): ?>
<div class="modal fade" id="modalEditarNota<?= (int) $nota['id'] ?>" tabindex="-1" aria-labelledby="modalEditarNotaLabel<?= (int) $nota['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEditarNotaLabel<?= (int) $nota['id'] ?>">Editar nota #<?= (int) $nota['id'] ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_nota">
        <input type="hidden" name="id" value="<?= (int) $nota['id'] ?>">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Paciente</label>
              <select name="paciente_id" class="form-select" required>
                <option value="">Selecciona paciente</option>
                <?php foreach ($pacientes as $paciente): ?>
                <option value="<?= (int) $paciente['id'] ?>" <?= ((int) $paciente['id'] === (int) $nota['paciente_id']) ? 'selected' : '' ?>>
                  <?= esc($paciente['nombres'] . ' ' . $paciente['apellidos']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Cita asociada (opcional)</label>
              <select name="cita_clinica_id" class="form-select">
                <option value="">Sin cita asociada</option>
                <?php foreach ($citas as $cita): ?>
                <option value="<?= (int) $cita['id'] ?>" <?= ((int) $cita['id'] === (int) ($nota['cita_clinica_id'] ?? 0)) ? 'selected' : '' ?>>
                  Cita #<?= (int) $cita['id'] ?> | <?= esc((string) $cita['fecha']) ?> <?= esc(substr((string) $cita['hora'], 0, 5)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Tipo de nota</label>
              <select name="tipo_nota" class="form-select">
                <option value="evolucion" <?= ((string) $nota['tipo_nota'] === 'evolucion') ? 'selected' : '' ?>>Evolucion</option>
                <option value="evaluacion" <?= ((string) $nota['tipo_nota'] === 'evaluacion') ? 'selected' : '' ?>>Evaluacion</option>
                <option value="diagnostico" <?= ((string) $nota['tipo_nota'] === 'diagnostico') ? 'selected' : '' ?>>Diagnostico</option>
                <option value="plan_terapeutico" <?= ((string) $nota['tipo_nota'] === 'plan_terapeutico') ? 'selected' : '' ?>>Plan terapeutico</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Contenido</label>
              <textarea name="contenido" class="form-control" rows="5" required><?= esc((string) $nota['contenido']) ?></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="confidencial" id="confidencialEditar<?= (int) $nota['id'] ?>" <?= (int) $nota['confidencial'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="confidencialEditar<?= (int) $nota['id'] ?>">Marcar como confidencial</label>
              </div>
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
  var modalElement = document.getElementById('modalNuevaNota');
  if (!modalElement) {
    return;
  }
  var modal = new bootstrap.Modal(modalElement);
  modal.show();
});
</script>
<?php endif; ?>
<?php admin_footer(); ?>
