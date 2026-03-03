<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin']);

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS equipo_web (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(140) NOT NULL,
            cargo VARCHAR(140) NOT NULL DEFAULT '',
            descripcion VARCHAR(255) NOT NULL DEFAULT '',
            iniciales VARCHAR(8) NOT NULL DEFAULT '',
            imagen VARCHAR(255) DEFAULT '',
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("ALTER TABLE equipo_web ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT '' AFTER iniciales");
} catch (Throwable $e) {
    // Si hay un problema de compatibilidad SQL, el modulo sigue operativo con lo que exista.
}

$flash = '';
$flashClass = 'success';

$uploadDirFs = __DIR__ . '/../assets/uploads/team';
$uploadDirWeb = 'assets/uploads/team';

function normalize_team_image_value(string $image): string
{
    $image = trim($image);
    if ($image === '') {
        return '';
    }

    if (filter_var($image, FILTER_VALIDATE_URL) !== false) {
        $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $image;
        }
    }

    if (preg_match('#^(?:/)?assets/[a-z0-9/_\\.-]+$#i', $image) === 1) {
        return ltrim($image, '/');
    }

    return '';
}

function team_image_preview_src(string $image): string
{
    $normalized = normalize_team_image_value($image);
    if ($normalized === '') {
        return '';
    }
    if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
        return $normalized;
    }
    return '../' . ltrim($normalized, '/');
}

function sanitize_team_initials(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z]/', '', $value) ?? '';
    return substr($value, 0, 4);
}

function process_uploaded_team_image(string $field, string $uploadDirFs, string $uploadDirWeb, string &$error): ?string
{
    $error = '';
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        $error = 'No se pudo subir la imagen.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $error = 'Archivo de imagen invalido.';
        return null;
    }
    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        $error = 'La imagen debe ser menor a 4MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        $error = 'Formato no permitido. Usa JPG, PNG, WEBP o GIF.';
        return null;
    }

    if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0755, true) && !is_dir($uploadDirFs)) {
        $error = 'No se pudo crear la carpeta de imagenes.';
        return null;
    }

    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $suffix = (string) mt_rand(1000, 9999);
    }

    $filename = 'equipo_' . date('Ymd_His') . '_' . $suffix . '.' . $allowed[$mime];
    $destinoFs = rtrim($uploadDirFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $destinoFs)) {
        $error = 'No se pudo guardar la imagen subida.';
        return null;
    }

    return rtrim($uploadDirWeb, '/') . '/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_post();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'crear_miembro') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cargo = trim((string) ($_POST['cargo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $iniciales = sanitize_team_initials((string) ($_POST['iniciales'] ?? ''));
        $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
        $activo = isset($_POST['activo']) ? 1 : 0;
        $imagen = normalize_team_image_value((string) ($_POST['imagen'] ?? ''));

        $uploadError = '';
        $uploadedImage = process_uploaded_team_image('imagen_archivo', $uploadDirFs, $uploadDirWeb, $uploadError);
        if ($uploadedImage !== null) {
            $imagen = $uploadedImage;
        } elseif ($uploadError !== '') {
            $flash = $uploadError;
            $flashClass = 'danger';
        }

        if ($flashClass !== 'danger') {
            if ($nombre === '' || $cargo === '' || $descripcion === '') {
                $flash = 'Nombre, cargo y descripcion son obligatorios.';
                $flashClass = 'danger';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO equipo_web (nombre, cargo, descripcion, iniciales, imagen, orden, activo)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$nombre, $cargo, $descripcion, $iniciales, $imagen, $orden, $activo]);
                $flash = 'Miembro del equipo creado correctamente.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'actualizar_miembro') {
        $id = (int) ($_POST['id'] ?? 0);
        if (isset($_POST['delete_miembro'])) {
            if ($id > 0) {
                $delete = $pdo->prepare('DELETE FROM equipo_web WHERE id = ?');
                $delete->execute([$id]);
                $flash = 'Miembro eliminado correctamente.';
                $flashClass = 'success';
            } else {
                $flash = 'Miembro invalido.';
                $flashClass = 'danger';
            }
        } else {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cargo = trim((string) ($_POST['cargo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $iniciales = sanitize_team_initials((string) ($_POST['iniciales'] ?? ''));
        $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
        $activo = ((string) ($_POST['activo'] ?? '1')) === '1' ? 1 : 0;
        $imagenInput = normalize_team_image_value((string) ($_POST['imagen'] ?? ''));
        $removeImagen = isset($_POST['remove_imagen']);

        if ($id <= 0) {
            $flash = 'Miembro invalido.';
            $flashClass = 'danger';
        } else {
            $currentStmt = $pdo->prepare('SELECT imagen FROM equipo_web WHERE id = ? LIMIT 1');
            $currentStmt->execute([$id]);
            $current = $currentStmt->fetch();

            if (!$current) {
                $flash = 'No existe el miembro seleccionado.';
                $flashClass = 'danger';
            } else {
                $imagen = (string) ($current['imagen'] ?? '');
                if ($removeImagen) {
                    $imagen = '';
                }
                if ($imagenInput !== '') {
                    $imagen = $imagenInput;
                }

                $uploadError = '';
                $uploadedImage = process_uploaded_team_image('imagen_archivo', $uploadDirFs, $uploadDirWeb, $uploadError);
                if ($uploadedImage !== null) {
                    $imagen = $uploadedImage;
                } elseif ($uploadError !== '') {
                    $flash = $uploadError;
                    $flashClass = 'danger';
                }

                if ($flashClass !== 'danger') {
                    if ($nombre === '' || $cargo === '' || $descripcion === '') {
                        $flash = 'Nombre, cargo y descripcion son obligatorios.';
                        $flashClass = 'danger';
                    } else {
                        $update = $pdo->prepare(
                            'UPDATE equipo_web
                             SET nombre = ?, cargo = ?, descripcion = ?, iniciales = ?, imagen = ?, orden = ?, activo = ?
                             WHERE id = ?'
                        );
                        $update->execute([$nombre, $cargo, $descripcion, $iniciales, $imagen, $orden, $activo, $id]);
                        $flash = 'Miembro actualizado correctamente.';
                        $flashClass = 'success';
                    }
                }
            }
        }
        }
    }
}

$equipo = [];
try {
    $equipo = $pdo->query(
        'SELECT id, nombre, cargo, descripcion, iniciales, imagen, orden, activo
         FROM equipo_web
         ORDER BY orden ASC, id ASC'
    )->fetchAll();
} catch (Throwable $e) {
    $equipo = [];
}

admin_header('Equipo web', 'equipo', 'Edita tarjetas del equipo profesional en la web publica.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Nuevo miembro</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($equipo) ?> miembros</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoMiembro">
        <i class="bi bi-plus-circle me-1"></i>Nuevo miembro
      </button>
    </div>
  </div>
  <p class="text-secondary mb-0">Usa el boton "Nuevo miembro" para crear una tarjeta del equipo en la web publica.</p>
</section>

<div class="modal fade" id="modalNuevoMiembro" tabindex="-1" aria-labelledby="modalNuevoMiembroLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalNuevoMiembroLabel">Nuevo miembro</h2>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formNuevoMiembro">
            <i class="bi bi-save me-1"></i>Guardar cambios
          </button>
        </div>
      </div>
      <form method="POST" enctype="multipart/form-data" id="formNuevoMiembro">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_miembro">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input name="nombre" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cargo</label>
              <input name="cargo" class="form-control" placeholder="Psicologia Clinica" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Iniciales</label>
              <input name="iniciales" class="form-control" maxlength="4" placeholder="ML">
            </div>

            <div class="col-12">
              <label class="form-label">Descripcion corta</label>
              <input name="descripcion" class="form-control" placeholder="Trauma, ansiedad y bienestar emocional" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Imagen URL (opcional)</label>
              <input name="imagen" class="form-control" placeholder="https://... o assets/...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Subir imagen (opcional)</label>
              <input type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Orden</label>
              <input type="number" name="orden" min="0" max="9999" value="0" class="form-control">
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="activo" id="new_team_activo" checked>
                <label class="form-check-label" for="new_team_activo">Activo en web</label>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (!$equipo): ?>
<section class="admin-card p-3 p-lg-4">
  <p class="text-secondary mb-0">No hay miembros de equipo registrados.</p>
</section>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($equipo as $member): ?>
  <?php $previewSrc = team_image_preview_src((string) ($member['imagen'] ?? '')); ?>
  <div class="col-12 col-xl-6">
    <section class="admin-card p-3 p-lg-4 h-100">
      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="actualizar_miembro">
        <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">

        <div class="col-12">
          <div class="team-admin-preview">
            <?php if ($previewSrc !== ''): ?>
            <img src="<?= esc($previewSrc) ?>" alt="<?= esc('Foto de ' . (string) $member['nombre']) ?>" loading="lazy">
            <?php else: ?>
            <div class="service-admin-empty">
              <span><?= esc(sanitize_team_initials((string) $member['iniciales']) !== '' ? sanitize_team_initials((string) $member['iniciales']) : 'PB') ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <input name="nombre" class="form-control" value="<?= esc((string) $member['nombre']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Cargo</label>
          <input name="cargo" class="form-control" value="<?= esc((string) $member['cargo']) ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Iniciales</label>
          <input name="iniciales" class="form-control" maxlength="4" value="<?= esc((string) $member['iniciales']) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Descripcion corta</label>
          <input name="descripcion" class="form-control" value="<?= esc((string) $member['descripcion']) ?>" required>
        </div>

        <div class="col-md-7">
          <label class="form-label">Imagen URL</label>
          <input name="imagen" class="form-control" value="<?= esc((string) $member['imagen']) ?>" placeholder="https://... o assets/...">
        </div>
        <div class="col-md-3">
          <label class="form-label">Subir imagen</label>
          <input type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Orden</label>
          <input type="number" min="0" max="9999" name="orden" class="form-control" value="<?= (int) $member['orden'] ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Activo</label>
          <select name="activo" class="form-select">
            <option value="1" <?= (int) $member['activo'] === 1 ? 'selected' : '' ?>>Si</option>
            <option value="0" <?= (int) $member['activo'] !== 1 ? 'selected' : '' ?>>No</option>
          </select>
        </div>
        <div class="col-md-5 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remove_imagen" id="remove_team_<?= (int) $member['id'] ?>">
            <label class="form-check-label" for="remove_team_<?= (int) $member['id'] ?>">Quitar imagen actual</label>
          </div>
        </div>
        <div class="col-md-4 d-flex align-items-end justify-content-md-end">
          <div class="d-flex gap-2">
            <button class="btn btn-outline-danger js-confirm" type="submit" name="delete_miembro" value="1" data-confirm="¿Eliminar miembro #<?= (int) $member['id'] ?>?">
              <i class="bi bi-trash me-1"></i>Eliminar
            </button>
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-save me-1"></i>Guardar cambios
            </button>
          </div>
        </div>
      </form>
    </section>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php admin_footer(); ?>
