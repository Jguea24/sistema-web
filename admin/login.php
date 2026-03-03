<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

if (is_admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Completa correo y contrasena.';
    } elseif (!admin_login($pdo, $email, $password)) {
        $error = 'Credenciales invalidas.';
    } else {
        if (admin_must_change_password()) {
            header('Location: cambiar_password.php');
            exit;
        }
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
  <style>
    .face-scan-wrap {
      position: relative;
      overflow: hidden;
      border-radius: 16px;
      background: #0b1020;
      border: 1px solid rgba(255, 255, 255, 0.12);
      aspect-ratio: 16 / 10;
    }
    .face-video,
    .face-canvas {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .face-video {
      transform: scaleX(-1);
      opacity: 0.32;
    }
    .face-canvas {
      transform: scaleX(-1);
    }
    .scan-guide {
      position: absolute;
      inset: 8% 20%;
      border: 2px solid rgba(126, 231, 135, 0.85);
      border-radius: 120px;
      box-shadow: 0 0 0 9999px rgba(5, 9, 18, 0.42) inset;
      pointer-events: none;
    }
  </style>
</head>
<body class="login-shell d-flex align-items-center justify-content-center p-3">
  <div class="login-card p-4 p-lg-5">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="admin-brand-mark">PB</span>
      <div>
        <h1 class="h4 mb-0 fw-bold">Ingreso administrativo</h1>
        <small class="text-secondary">Consultorio PsicoBienestar</small>
      </div>
    </div>

    <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div id="passkeyAlert" class="alert d-none" role="alert"></div>

    <form method="POST" class="row g-3">
      <?= csrf_input() ?>
      <div class="col-12">
        <label class="form-label">Correo</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" required>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label">Contrasena</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control" required>
        </div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right me-1"></i>Entrar al panel
        </button>
      </div>
      <div class="col-12">
        <div class="text-center text-secondary small my-1">o</div>
      </div>
      <div class="col-12">
        <button type="button" class="btn btn-outline-primary w-100" id="btnPasskeyLogin">
          <i class="bi bi-person-bounding-box me-1"></i>Entrar con reconocimiento facial
        </button>
      </div>
      <div class="col-12">
        <small class="text-secondary">Requiere passkey registrada y navegador compatible con WebAuthn.</small>
      </div>
    </form>

    <div class="mt-3">
      <a href="../index.php" class="btn btn-sm btn-outline-secondary">Volver al sitio</a>
    </div>
  </div>

  <div class="modal fade" id="faceScanModal" tabindex="-1" aria-labelledby="faceScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5 mb-0" id="faceScanModalLabel">Verificacion facial</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="face-scan-wrap">
            <video id="faceVideo" class="face-video" autoplay muted playsinline></video>
            <canvas id="faceCanvas" class="face-canvas"></canvas>
            <div class="scan-guide"></div>
          </div>
          <div class="progress mt-3" role="progressbar" aria-label="Progreso escaneo">
            <div id="scanProgress" class="progress-bar bg-success" style="width: 0%">0%</div>
          </div>
          <div id="scanStatus" class="small text-secondary mt-2">Preparando camara...</div>
          <div class="small text-secondary mt-1">Mantente centrado dentro del ovalo hasta completar el 100%.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js"></script>
  <script src="../assets/js/admin.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var passkeyBtn = document.getElementById('btnPasskeyLogin');
    var alertNode = document.getElementById('passkeyAlert');
    var modalEl = document.getElementById('faceScanModal');
    var videoEl = document.getElementById('faceVideo');
    var canvasEl = document.getElementById('faceCanvas');
    var scanStatusEl = document.getElementById('scanStatus');
    var scanProgressEl = document.getElementById('scanProgress');
    var faceModal = modalEl ? new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false }) : null;
    var faceMesh = null;
    var camera = null;
    var scanResolved = false;
    var stableFrames = 0;
    var requiredFrames = 24;
    var scanRunning = false;

    function setAlert(type, text) {
      if (!alertNode) return;
      alertNode.className = 'alert alert-' + type;
      alertNode.textContent = text;
      alertNode.classList.remove('d-none');
    }

    function setScanStatus(text, isError) {
      if (!scanStatusEl) return;
      scanStatusEl.className = 'small mt-2 ' + (isError ? 'text-danger' : 'text-secondary');
      scanStatusEl.textContent = text;
    }

    function setScanProgress(value) {
      if (!scanProgressEl) return;
      var pct = Math.max(0, Math.min(100, Math.round(value)));
      scanProgressEl.style.width = pct + '%';
      scanProgressEl.textContent = pct + '%';
    }

    function stopCamera() {
      scanRunning = false;
      if (camera && typeof camera.stop === 'function') {
        camera.stop();
      }
      camera = null;
      if (videoEl && videoEl.srcObject) {
        var tracks = videoEl.srcObject.getTracks ? videoEl.srcObject.getTracks() : [];
        tracks.forEach(function (track) { track.stop(); });
        videoEl.srcObject = null;
      }
    }

    function drawOverlay(results) {
      if (!canvasEl || !videoEl) return;
      var ctx = canvasEl.getContext('2d');
      if (!ctx) return;

      var width = videoEl.videoWidth || 640;
      var height = videoEl.videoHeight || 400;
      if (canvasEl.width !== width) canvasEl.width = width;
      if (canvasEl.height !== height) canvasEl.height = height;

      ctx.save();
      ctx.clearRect(0, 0, width, height);
      if (results.image) {
        ctx.drawImage(results.image, 0, 0, width, height);
      }

      var hasFace = !!(results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0);
      if (hasFace) {
        var landmarks = results.multiFaceLandmarks[0];
        drawConnectors(ctx, landmarks, FACEMESH_TESSELATION, { color: '#75f0b5', lineWidth: 0.6 });
        drawConnectors(ctx, landmarks, FACEMESH_LEFT_EYE, { color: '#46b8ff', lineWidth: 1.1 });
        drawConnectors(ctx, landmarks, FACEMESH_RIGHT_EYE, { color: '#46b8ff', lineWidth: 1.1 });
        drawConnectors(ctx, landmarks, FACEMESH_FACE_OVAL, { color: '#e8f7ff', lineWidth: 1.2 });

        var minX = 1, maxX = 0, minY = 1, maxY = 0;
        for (var i = 0; i < landmarks.length; i++) {
          var p = landmarks[i];
          if (p.x < minX) minX = p.x;
          if (p.x > maxX) maxX = p.x;
          if (p.y < minY) minY = p.y;
          if (p.y > maxY) maxY = p.y;
        }
        var centerX = (minX + maxX) / 2;
        var centerY = (minY + maxY) / 2;
        var faceW = maxX - minX;
        var faceH = maxY - minY;

        var centered = Math.abs(centerX - 0.5) < 0.14 && Math.abs(centerY - 0.5) < 0.18;
        var sized = faceW > 0.2 && faceH > 0.28;
        if (centered && sized) {
          stableFrames += 1;
          setScanStatus('Rostro detectado. Mantente quieto para validar...');
        } else {
          stableFrames = Math.max(0, stableFrames - 2);
          setScanStatus('Ajusta tu rostro dentro del ovalo.');
        }

        var progress = (stableFrames / requiredFrames) * 100;
        setScanProgress(progress);

        if (!scanResolved && stableFrames >= requiredFrames) {
          scanResolved = true;
          stopCamera();
          setScanStatus('Rostro validado. Continuando autenticacion...');
          setScanProgress(100);
          if (faceModal) faceModal.hide();
          runPasskeyAuth();
        }
      } else {
        stableFrames = Math.max(0, stableFrames - 2);
        setScanProgress((stableFrames / requiredFrames) * 100);
        setScanStatus('Buscando rostro...');
      }
      ctx.restore();
    }

    async function initFaceScanner() {
      if (!window.FaceMesh || !window.Camera) {
        throw new Error('No se pudo cargar el motor de reconocimiento facial.');
      }

      if (!faceMesh) {
        faceMesh = new FaceMesh({
          locateFile: function (file) {
            return 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/' + file;
          }
        });
        faceMesh.setOptions({
          maxNumFaces: 1,
          refineLandmarks: true,
          minDetectionConfidence: 0.6,
          minTrackingConfidence: 0.6
        });
        faceMesh.onResults(drawOverlay);
      }

      stableFrames = 0;
      setScanProgress(0);
      setScanStatus('Inicializando camara...');
      scanRunning = true;

      camera = new Camera(videoEl, {
        onFrame: async function () {
          if (!scanRunning) return;
          await faceMesh.send({ image: videoEl });
        },
        width: 960,
        height: 540
      });
      await camera.start();
      setScanStatus('Camara activa. Mira al centro.');
    }

    function b64urlToArrayBuffer(value) {
      var base64 = value.replace(/-/g, '+').replace(/_/g, '/');
      while (base64.length % 4 !== 0) base64 += '=';
      var binary = atob(base64);
      var bytes = new Uint8Array(binary.length);
      for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
      return bytes.buffer;
    }

    function arrayBufferToB64url(buffer) {
      var bytes = new Uint8Array(buffer);
      var binary = '';
      for (var i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
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
        throw new Error(data.error || 'No se pudo completar la autenticacion.');
      }
      return data;
    }

    if (!passkeyBtn) return;

    if (!window.isSecureContext || !window.PublicKeyCredential) {
      passkeyBtn.disabled = true;
      setAlert('warning', 'WebAuthn solo funciona en HTTPS o localhost con navegador compatible.');
      return;
    }

    async function runPasskeyAuth() {
      setAlert('info', 'Validando passkey...');
      try {
        var begin = await postJson({ action: 'begin_authentication' });
        var publicKey = begin.publicKey || {};
        publicKey.challenge = b64urlToArrayBuffer(publicKey.challenge);

        if (Array.isArray(publicKey.allowCredentials)) {
          publicKey.allowCredentials = publicKey.allowCredentials.map(function (item) {
            var copy = Object.assign({}, item);
            copy.id = b64urlToArrayBuffer(item.id);
            return copy;
          });
        }

        var assertion = await navigator.credentials.get({ publicKey: publicKey });
        if (!assertion) {
          throw new Error('No se obtuvo una credencial valida.');
        }

        var result = await postJson({
          action: 'finish_authentication',
          credential: {
            id: arrayBufferToB64url(assertion.rawId),
            rawId: arrayBufferToB64url(assertion.rawId),
            type: assertion.type,
            response: {
              clientDataJSON: arrayBufferToB64url(assertion.response.clientDataJSON),
              authenticatorData: arrayBufferToB64url(assertion.response.authenticatorData),
              signature: arrayBufferToB64url(assertion.response.signature),
              userHandle: assertion.response.userHandle ? arrayBufferToB64url(assertion.response.userHandle) : ''
            }
          }
        });

        window.location.href = result.redirect || 'index.php';
      } catch (error) {
        setAlert('danger', error.message || 'No se pudo iniciar sesion con passkey.');
        passkeyBtn.disabled = false;
      }
    }

    passkeyBtn.addEventListener('click', async function () {
      passkeyBtn.disabled = true;
      scanResolved = false;
      setAlert('info', 'Iniciando escaneo facial...');

      if (!faceModal) {
        runPasskeyAuth();
        return;
      }

      faceModal.show();
      try {
        await initFaceScanner();
      } catch (error) {
        stopCamera();
        faceModal.hide();
        setAlert('warning', (error && error.message ? error.message : 'No se pudo abrir la camara.') + ' Se abrira autenticacion passkey directa.');
        runPasskeyAuth();
      }
    });

    if (modalEl) {
      modalEl.addEventListener('hidden.bs.modal', function () {
        stopCamera();
        if (!scanResolved) {
          passkeyBtn.disabled = false;
          setAlert('secondary', 'Escaneo cancelado. Puedes intentar nuevamente.');
        }
      });
    }
  });
  </script>
</body>
</html>
