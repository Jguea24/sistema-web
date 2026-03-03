<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../services/whatsapp_service.php';
require_once __DIR__ . '/../services/reminder_service.php';

require_admin_roles(['admin', 'terapeuta', 'recepcion']);

$flash = '';
$flashClass = 'success';
$config = load_whatsapp_config();
$provider = (string) ($config['provider'] ?? 'simulate');
$formLimit = 50;
$formLimitCitas = 200;

/**
 * Resume la respuesta de la API para no romper la tabla.
 */
function summarize_api_response(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return 'Sin respuesta API';
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        if (isset($decoded['messages'][0]['id'])) {
            return 'Enviado (id: ' . (string) $decoded['messages'][0]['id'] . ')';
        }
        if (isset($decoded['error']['message'])) {
            return 'Error: ' . (string) $decoded['error']['message'];
        }
    }

    return strlen($raw) > 80 ? substr($raw, 0, 80) . '...' : $raw;
}

function build_wa_me_link(string $phone, string $message, array $config): string
{
    $normalized = whatsapp_normalize_phone($phone, $config);
    if ($normalized === '') {
        return '';
    }

    return 'https://wa.me/' . rawurlencode($normalized) . '?text=' . rawurlencode($message);
}

function format_datetime_local(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if ($dt === false) {
        return $value;
    }

    return $dt->format('d/m/Y H:i');
}

function reminder_state_badge_class(string $state): string
{
    $state = strtolower(trim($state));
    return match ($state) {
        'pendiente' => 'is-pendiente',
        'error' => 'is-error',
        'enviado' => 'is-enviado',
        default => 'is-default',
    };
}

function datetime_to_input(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    if ($dt === false) {
        return '';
    }
    return $dt->format('Y-m-d\TH:i');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'enviar_ahora') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $result = process_pending_whatsapp_queue($pdo, 1, $id);
            if ($result['sent'] > 0) {
                $flash = 'Recordatorio enviado correctamente.';
                $flashClass = 'success';
            } else {
                $errorStmt = $pdo->prepare('SELECT respuesta_api FROM recordatorios_whatsapp WHERE id = ? LIMIT 1');
                $errorStmt->execute([$id]);
                $raw = (string) ($errorStmt->fetchColumn() ?: '');
                $flash = 'No se pudo enviar el recordatorio. ' . summarize_api_response($raw);
                $flashClass = 'danger';
            }
        }
    }

    if ($action === 'marcar_enviado_manual') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE recordatorios_whatsapp
                 SET estado = 'enviado',
                     enviado_en = NOW(),
                     message_id_wamid = NULL,
                     respuesta_api = 'manual_web'
                 WHERE id = ?"
            );
            $stmt->execute([$id]);
            $flash = 'Recordatorio marcado como enviado manualmente.';
            $flashClass = 'success';
        }
    }

    if ($action === 'editar_recordatorio') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = trim((string) ($_POST['tipo'] ?? 'recordatorio'));
        $telefono = trim((string) ($_POST['telefono_destino'] ?? ''));
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        $programado = trim((string) ($_POST['programado_para'] ?? ''));
        $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));
        $estadosPermitidos = ['pendiente', 'error', 'enviado'];

        if ($id <= 0 || $telefono === '' || $mensaje === '' || $programado === '') {
            $flash = 'Completa los campos requeridos para editar el recordatorio.';
            $flashClass = 'danger';
        } elseif (!in_array($estado, $estadosPermitidos, true)) {
            $flash = 'Estado invalido para el recordatorio.';
            $flashClass = 'danger';
        } else {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $programado);
            if ($dt === false) {
                $flash = 'Fecha y hora invalidas.';
                $flashClass = 'danger';
            } else {
                $programadoSql = $dt->format('Y-m-d H:i:00');
                $stmt = $pdo->prepare(
                    "UPDATE recordatorios_whatsapp
                     SET tipo = ?,
                         telefono_destino = ?,
                         mensaje = ?,
                         programado_para = ?,
                         estado = ?,
                         enviado_en = CASE WHEN ? = 'enviado' THEN COALESCE(enviado_en, NOW()) ELSE NULL END,
                         message_id_wamid = CASE WHEN ? = 'enviado' THEN message_id_wamid ELSE NULL END,
                         respuesta_api = CASE WHEN ? = 'enviado' THEN respuesta_api ELSE NULL END
                     WHERE id = ?"
                );
                $stmt->execute([
                    $tipo !== '' ? $tipo : 'recordatorio',
                    $telefono,
                    $mensaje,
                    $programadoSql,
                    $estado,
                    $estado,
                    $estado,
                    $estado,
                    $id,
                ]);
                $flash = 'Recordatorio actualizado correctamente.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'eliminar_recordatorio') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM recordatorios_whatsapp WHERE id = ?');
            $stmt->execute([$id]);
            $flash = 'Recordatorio eliminado correctamente.';
            $flashClass = 'success';
        }
    }

    if ($action === 'procesar_pendientes') {
        $limit = (int) ($_POST['limit'] ?? 50);
        $formLimit = max(1, min(500, $limit));
        $result = process_pending_whatsapp_queue($pdo, max(1, $limit));
        $flash = sprintf(
            'Proveedor %s: procesados=%d enviados=%d fallidos=%d',
            $result['provider'],
            $result['processed'],
            $result['sent'],
            $result['failed']
        );
        $flashClass = 'success';
    }

    if ($action === 'generar_faltantes') {
        $limit = (int) ($_POST['limit_citas'] ?? 200);
        $formLimitCitas = max(1, min(1000, $limit));
        $result = backfill_missing_reminders($pdo, max(1, $limit));
        $flash = sprintf(
            'Citas evaluadas=%d | Recordatorios creados=%d',
            $result['citas'],
            $result['recordatorios']
        );
        $flashClass = 'info';
    }
}

$recordatorios = $pdo->query(
    "SELECT rw.id, rw.tipo, rw.telefono_destino, rw.mensaje, rw.programado_para, rw.estado, rw.enviado_en, rw.message_id_wamid, rw.respuesta_api,
            p.nombres, p.apellidos, cc.fecha, cc.hora
     FROM recordatorios_whatsapp rw
     INNER JOIN pacientes p ON p.id = rw.paciente_id
     INNER JOIN citas_clinicas cc ON cc.id = rw.cita_clinica_id
     ORDER BY rw.programado_para DESC
     LIMIT 300"
)->fetchAll();

admin_header('Recordatorios WhatsApp', 'recordatorios', 'Cola de avisos y confirmaciones de cita.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
    <div>
      <h2 class="h5 mb-0">Cola de recordatorios</h2>
      <p class="text-secondary mb-0">Proveedor activo: <strong><?= esc($provider) ?></strong></p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($recordatorios) ?> items</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionRecordatorios">
        <i class="bi bi-gear me-1"></i>Gestionar cola
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0 recordatorios-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Paciente</th>
          <th>Telefono</th>
          <th>Programado</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th>Mensaje</th>
          <th>Enviado</th>
          <th>WAMID</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$recordatorios): ?>
        <tr><td colspan="10" class="text-center text-secondary py-4">No hay recordatorios.</td></tr>
        <?php else: ?>
        <?php foreach ($recordatorios as $row): ?>
        <tr>
          <td>#<?= (int) $row['id'] ?></td>
          <td class="fw-semibold"><?= esc($row['nombres'] . ' ' . $row['apellidos']) ?></td>
          <td><?= esc((string) $row['telefono_destino']) ?></td>
          <td><?= esc(format_datetime_local((string) $row['programado_para'])) ?></td>
          <td><span class="badge badge-soft"><?= esc((string) $row['tipo']) ?></span></td>
          <td><span class="badge badge-state <?= reminder_state_badge_class((string) $row['estado']) ?>"><?= esc((string) $row['estado']) ?></span></td>
          <td class="small text-truncate-2 recordatorio-message" title="<?= esc((string) $row['mensaje']) ?>"><?= esc((string) $row['mensaje']) ?></td>
          <td><?= esc(format_datetime_local((string) ($row['enviado_en'] ?? ''))) ?></td>
          <td class="small recordatorio-wamid" title="<?= esc((string) ($row['message_id_wamid'] ?? '')) ?>">
            <?= esc((string) ($row['message_id_wamid'] ?? '-')) ?>
          </td>
          <td>
            <?php $waLink = build_wa_me_link((string) $row['telefono_destino'], (string) $row['mensaje'], $config); ?>
            <div class="recordatorio-actions">
              <?php if (in_array((string) $row['estado'], ['pendiente', 'error'], true)): ?>
              <?php if ($waLink !== ''): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= esc($waLink) ?>" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-whatsapp me-1"></i>WhatsApp Web
              </a>
              <?php endif; ?>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="marcar_enviado_manual">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-sm btn-outline-success">
                  <i class="bi bi-check2-circle me-1"></i>Marcar enviado
                </button>
              </form>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="enviar_ahora">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-send me-1"></i><?= (string) $row['estado'] === 'error' ? 'Reintentar API' : 'Enviar por API' ?>
                </button>
              </form>
              <?php else: ?>
              <div class="small text-secondary mb-1 text-truncate-2 recordatorio-message"><?= esc(summarize_api_response((string) $row['respuesta_api'])) ?></div>
              <details class="small recordatorio-details">
                <summary class="text-primary">Ver respuesta</summary>
                <pre class="mb-0 mt-1 p-2 border rounded bg-light"><?= esc((string) $row['respuesta_api']) ?></pre>
              </details>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarRecordatorio<?= (int) $row['id'] ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
              </button>
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="eliminar_recordatorio">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-sm btn-outline-danger js-confirm" data-confirm="¿Eliminar recordatorio #<?= (int) $row['id'] ?>? Esta accion no se puede deshacer.">
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

<div class="modal fade" id="modalGestionRecordatorios" tabindex="-1" aria-labelledby="modalGestionRecordatoriosLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalGestionRecordatoriosLabel">Gestion de recordatorios</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" class="recordatorio-tool-form mb-3">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="procesar_pendientes">
          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label mb-0">Procesar pendientes vencidos</label>
              <input type="number" min="1" max="500" name="limit" class="form-control" value="<?= (int) $formLimit ?>">
            </div>
            <div class="col-md-7">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-send-check me-1"></i>Procesar vencidos
              </button>
            </div>
          </div>
        </form>

        <form method="POST" class="recordatorio-tool-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="generar_faltantes">
          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label mb-0">Generar recordatorios faltantes</label>
              <input type="number" min="1" max="1000" name="limit_citas" class="form-control" value="<?= (int) $formLimitCitas ?>">
            </div>
            <div class="col-md-7">
              <button class="btn btn-outline-primary" type="submit">
                <i class="bi bi-magic me-1"></i>Generar
              </button>
              <small class="text-secondary ms-2">Usar cuando ya tienes citas creadas sin recordatorios.</small>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?php foreach ($recordatorios as $row): ?>
<div class="modal fade" id="modalEditarRecordatorio<?= (int) $row['id'] ?>" tabindex="-1" aria-labelledby="modalEditarRecordatorioLabel<?= (int) $row['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEditarRecordatorioLabel<?= (int) $row['id'] ?>">Editar recordatorio #<?= (int) $row['id'] ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar_recordatorio">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Tipo</label>
              <input name="tipo" class="form-control" value="<?= esc((string) $row['tipo']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="pendiente" <?= (string) $row['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="error" <?= (string) $row['estado'] === 'error' ? 'selected' : '' ?>>Error</option>
                <option value="enviado" <?= (string) $row['estado'] === 'enviado' ? 'selected' : '' ?>>Enviado</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefono destino</label>
              <input name="telefono_destino" class="form-control" value="<?= esc((string) $row['telefono_destino']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Programado para</label>
              <input type="datetime-local" name="programado_para" class="form-control" value="<?= esc(datetime_to_input((string) $row['programado_para'])) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Mensaje</label>
              <textarea name="mensaje" class="form-control" rows="4" required><?= esc((string) $row['mensaje']) ?></textarea>
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

