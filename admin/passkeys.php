<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_login();

$flash = '';
$flashClass = 'success';

start_admin_session();
$currentUserId = (int) (($_SESSION['admin_user'] ?? [])['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_passkey') {
        $passkeyId = (int) ($_POST['passkey_id'] ?? 0);
        if ($passkeyId <= 0) {
            $flash = 'Passkey invalida.';
            $flashClass = 'danger';
        } else {
            $delete = $pdo->prepare('DELETE FROM admin_passkeys WHERE id = ? AND usuario_admin_id = ?');
            $delete->execute([$passkeyId, $currentUserId]);
            if ($delete->rowCount() > 0) {
                $flash = 'Passkey eliminada correctamente.';
                $flashClass = 'success';
            } else {
                $flash = 'No se encontro la passkey seleccionada.';
                $flashClass = 'warning';
            }
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT id, label, credential_id, transports, sign_count, ultimo_uso_en, creado_en
     FROM admin_passkeys
     WHERE usuario_admin_id = ?
     ORDER BY id DESC'
);
$stmt->execute([$currentUserId]);
$passkeys = $stmt->fetchAll();

admin_header('Passkeys', 'passkeys', 'Configura acceso con reconocimiento facial o huella (WebAuthn).');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h5 mb-0">Registrar nueva passkey</h2>
    <button type="button" class="btn btn-primary" id="btnRegisterPasskey">
      <i class="bi bi-person-bounding-box me-1"></i>Registrar con reconocimiento
    </button>
  </div>
  <div class="row g-2 align-items-end">
    <div class="col-12 col-lg-6">
      <label class="form-label">Etiqueta (opcional)</label>
      <input type="text" id="passkeyLabel" class="form-control" placeholder="Ej: Laptop oficina, Celular personal">
    </div>
    <div class="col-12 col-lg-6">
      <div class="alert alert-info mb-0">
        Usa Windows Hello, Face ID, Touch ID o huella compatible con tu dispositivo.
      </div>
    </div>
  </div>
  <div id="passkeyResult" class="mt-3"></div>
</section>

<section class="admin-card p-3 p-lg-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Tus passkeys</h2>
    <span class="badge badge-soft"><?= count($passkeys) ?> registradas</span>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Etiqueta</th>
          <th>Credential</th>
          <th>Transportes</th>
          <th>Contador</th>
          <th>Ultimo uso</th>
          <th>Creada</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($passkeys) === 0): ?>
        <tr>
          <td colspan="8" class="text-center text-secondary py-4">No tienes passkeys registradas.</td>
        </tr>
        <?php endif; ?>
        <?php foreach ($passkeys as $item): ?>
        <?php
          $credentialId = (string) $item['credential_id'];
          $maskedCredential = strlen($credentialId) > 24
              ? substr($credentialId, 0, 12) . '...' . substr($credentialId, -8)
              : $credentialId;
        ?>
        <tr>
          <td>#<?= (int) $item['id'] ?></td>
          <td><?= esc((string) ($item['label'] ?: 'Sin etiqueta')) ?></td>
          <td><code><?= esc($maskedCredential) ?></code></td>
          <td><?= esc((string) ($item['transports'] ?: '-')) ?></td>
          <td><?= (int) $item['sign_count'] ?></td>
          <td><?= esc((string) ($item['ultimo_uso_en'] ?? '-')) ?></td>
          <td><?= esc((string) $item['creado_en']) ?></td>
          <td>
            <form method="POST" class="m-0">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="delete_passkey">
              <input type="hidden" name="passkey_id" value="<?= (int) $item['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger js-confirm" data-confirm="Eliminar esta passkey?">
                <i class="bi bi-trash me-1"></i>Eliminar
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('btnRegisterPasskey');
  var labelInput = document.getElementById('passkeyLabel');
  var resultNode = document.getElementById('passkeyResult');
  var csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;

  function showResult(type, text) {
    resultNode.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + text + '</div>';
  }

  function b64urlToArrayBuffer(value) {
    var base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    while (base64.length % 4 !== 0) base64 += '=';
    var binary = atob(base64);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  function arrayBufferToB64url(buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  async function postJson(payload) {
    var response = await fetch('webauthn.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    var data = await response.json().catch(function () { return { ok: false, error: 'Respuesta invalida del servidor.' }; });
    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'No se pudo completar la operacion.');
    }
    return data;
  }

  if (!window.PublicKeyCredential) {
    btn.disabled = true;
    showResult('warning', 'Este navegador no soporta WebAuthn.');
    return;
  }

  btn.addEventListener('click', async function () {
    btn.disabled = true;
    showResult('info', 'Iniciando registro de passkey...');

    try {
      var label = labelInput ? labelInput.value.trim() : '';
      var begin = await postJson({ action: 'begin_registration', csrf: csrfToken });
      var publicKey = begin.publicKey || {};

      publicKey.challenge = b64urlToArrayBuffer(publicKey.challenge);
      if (publicKey.user && publicKey.user.id) {
        publicKey.user.id = b64urlToArrayBuffer(publicKey.user.id);
      }
      if (Array.isArray(publicKey.excludeCredentials)) {
        publicKey.excludeCredentials = publicKey.excludeCredentials.map(function (item) {
          var copy = Object.assign({}, item);
          copy.id = b64urlToArrayBuffer(item.id);
          return copy;
        });
      }

      var credential = await navigator.credentials.create({ publicKey: publicKey });
      if (!credential) {
        throw new Error('No se pudo crear la passkey.');
      }

      var transports = [];
      if (credential.response && typeof credential.response.getTransports === 'function') {
        transports = credential.response.getTransports();
      }

      await postJson({
        action: 'finish_registration',
        csrf: csrfToken,
        label: label,
        credential: {
          id: arrayBufferToB64url(credential.rawId),
          rawId: arrayBufferToB64url(credential.rawId),
          type: credential.type,
          response: {
            clientDataJSON: arrayBufferToB64url(credential.response.clientDataJSON),
            attestationObject: arrayBufferToB64url(credential.response.attestationObject),
            transports: transports
          }
        }
      });

      showResult('success', 'Passkey registrada correctamente. Recargando...');
      setTimeout(function () {
        window.location.reload();
      }, 800);
    } catch (error) {
      showResult('danger', error.message || 'No se pudo registrar la passkey.');
    } finally {
      btn.disabled = false;
    }
  });
});
</script>

<?php admin_footer(); ?>

