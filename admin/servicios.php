<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin']);

try {
    $pdo->exec("ALTER TABLE servicios ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT '' AFTER icono");
} catch (Throwable $e) {
    // Compatibilidad: si falla la migracion, el modulo seguira con lo existente.
}

$flash = '';
$flashClass = 'success';

$uploadDirFs = __DIR__ . '/../assets/uploads/services';
$uploadDirWeb = 'assets/uploads/services';

function sanitize_service_icon(string $icon): string
{
    $icon = trim($icon);
    return preg_match('/^bi-[a-z0-9-]+$/i', $icon) === 1 ? $icon : 'bi-stars';
}

function normalize_service_image_value(string $image): string
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

function image_preview_src(string $image): string
{
    $normalized = normalize_service_image_value($image);
    if ($normalized === '') {
        return '';
    }
    if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
        return $normalized;
    }
    return '../' . ltrim($normalized, '/');
}

function process_uploaded_service_image(string $field, string $uploadDirFs, string $uploadDirWeb, string &$error): ?string
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

    $filename = 'servicio_' . date('Ymd_His') . '_' . $suffix . '.' . $allowed[$mime];
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

    if ($action === 'crear_servicio') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $icono = sanitize_service_icon((string) ($_POST['icono'] ?? ''));
        $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
        $activo = isset($_POST['activo']) ? 1 : 0;
        $imagen = normalize_service_image_value((string) ($_POST['imagen'] ?? ''));

        $uploadError = '';
        $uploadedImage = process_uploaded_service_image('imagen_archivo', $uploadDirFs, $uploadDirWeb, $uploadError);
        if ($uploadedImage !== null) {
            $imagen = $uploadedImage;
        } elseif ($uploadError !== '') {
            $flash = $uploadError;
            $flashClass = 'danger';
        }

        if ($flashClass !== 'danger') {
            if ($nombre === '' || $descripcion === '') {
                $flash = 'Nombre y descripcion son obligatorios.';
                $flashClass = 'danger';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO servicios (nombre, descripcion, icono, imagen, orden, activo)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$nombre, $descripcion, $icono, $imagen, $orden, $activo]);
                $flash = 'Servicio creado correctamente.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'actualizar_servicio') {
        $id = (int) ($_POST['id'] ?? 0);
        if (isset($_POST['delete_servicio'])) {
            if ($id > 0) {
                $delete = $pdo->prepare('DELETE FROM servicios WHERE id = ?');
                $delete->execute([$id]);
                $flash = 'Servicio eliminado correctamente.';
                $flashClass = 'success';
            } else {
                $flash = 'Servicio invalido.';
                $flashClass = 'danger';
            }
        } else {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $icono = sanitize_service_icon((string) ($_POST['icono'] ?? ''));
            $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
            $activo = ((string) ($_POST['activo'] ?? '1')) === '1' ? 1 : 0;
            $imagenInput = normalize_service_image_value((string) ($_POST['imagen'] ?? ''));
            $removeImagen = isset($_POST['remove_imagen']);

            if ($id <= 0) {
                $flash = 'Servicio invalido.';
                $flashClass = 'danger';
            } else {
                $currentStmt = $pdo->prepare('SELECT imagen FROM servicios WHERE id = ? LIMIT 1');
                $currentStmt->execute([$id]);
                $current = $currentStmt->fetch();

                if (!$current) {
                    $flash = 'No existe el servicio seleccionado.';
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
                    $uploadedImage = process_uploaded_service_image('imagen_archivo', $uploadDirFs, $uploadDirWeb, $uploadError);
                    if ($uploadedImage !== null) {
                        $imagen = $uploadedImage;
                    } elseif ($uploadError !== '') {
                        $flash = $uploadError;
                        $flashClass = 'danger';
                    }

                    if ($flashClass !== 'danger') {
                        if ($nombre === '' || $descripcion === '') {
                            $flash = 'Nombre y descripcion son obligatorios.';
                            $flashClass = 'danger';
                        } else {
                            $update = $pdo->prepare(
                                'UPDATE servicios
                                 SET nombre = ?, descripcion = ?, icono = ?, imagen = ?, orden = ?, activo = ?
                                 WHERE id = ?'
                            );
                            $update->execute([$nombre, $descripcion, $icono, $imagen, $orden, $activo, $id]);
                            $flash = 'Servicio actualizado correctamente.';
                            $flashClass = 'success';
                        }
                    }
                }
            }
        }
    }

}

$servicios = [];
try {
    $servicios = $pdo->query(
        'SELECT id, nombre, descripcion, icono, imagen, orden, activo
         FROM servicios
         ORDER BY orden ASC, id ASC'
    )->fetchAll();
} catch (Throwable $e) {
    $servicios = [];
}

admin_header('Servicios web', 'servicios', 'Edita imagenes y contenido de las tarjetas publicas.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Nuevo servicio</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($servicios) ?> servicios</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoServicio">
        <i class="bi bi-plus-circle me-1"></i>Nuevo servicio
      </button>
    </div>
  </div>
  <p class="text-secondary mb-0">Usa el boton "Nuevo servicio" para crear una tarjeta en la web publica.</p>
</section>

<div class="modal fade" id="modalNuevoServicio" tabindex="-1" aria-labelledby="modalNuevoServicioLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalNuevoServicioLabel">Nuevo servicio</h2>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formNuevoServicio">
            <i class="bi bi-save me-1"></i>Guardar cambios
          </button>
        </div>
      </div>
      <form method="POST" enctype="multipart/form-data" id="formNuevoServicio">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_servicio">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input name="nombre" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Orden</label>
              <input type="number" name="orden" min="0" max="9999" value="0" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Icono Bootstrap</label>
              <input name="icono" class="form-control" value="bi-stars" placeholder="bi-person-heart">
            </div>
            <div class="col-12">
              <label class="form-label">Descripcion</label>
              <textarea name="descripcion" class="form-control" rows="2" required></textarea>
            </div>
            <div class="col-md-7">
              <label class="form-label">Imagen URL (opcional)</label>
              <input name="imagen" class="form-control" placeholder="https://... o assets/...">
            </div>
            <div class="col-md-3">
              <label class="form-label">Subir imagen (opcional)</label>
              <input type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="activo" id="new_activo" checked>
                <label class="form-check-label" for="new_activo">Activo</label>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (!$servicios): ?>
<section class="admin-card p-3 p-lg-4">
  <p class="text-secondary mb-0">No hay servicios registrados.</p>
</section>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($servicios as $service): ?>
  <?php $previewSrc = image_preview_src((string) ($service['imagen'] ?? '')); ?>
  <div class="col-12 col-xl-6">
    <section class="admin-card p-3 p-lg-4 h-100">
      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="actualizar_servicio">
        <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">

        <div class="col-12">
          <div class="service-admin-preview">
            <?php if ($previewSrc !== ''): ?>
            <img src="<?= esc($previewSrc) ?>" alt="<?= esc('Imagen de ' . (string) $service['nombre']) ?>" loading="lazy">
            <?php else: ?>
            <div class="service-admin-empty">
              <i class="bi <?= esc(sanitize_service_icon((string) $service['icono'])) ?>"></i>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-7">
          <label class="form-label">Nombre</label>
          <input name="nombre" class="form-control" value="<?= esc((string) $service['nombre']) ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Orden</label>
          <input type="number" min="0" max="9999" name="orden" class="form-control" value="<?= (int) $service['orden'] ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Activo</label>
          <select name="activo" class="form-select">
            <option value="1" <?= (int) $service['activo'] === 1 ? 'selected' : '' ?>>Si</option>
            <option value="0" <?= (int) $service['activo'] !== 1 ? 'selected' : '' ?>>No</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Descripcion</label>
          <textarea name="descripcion" class="form-control" rows="2" required><?= esc((string) $service['descripcion']) ?></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Icono Bootstrap</label>
          <input name="icono" class="form-control" value="<?= esc(sanitize_service_icon((string) $service['icono'])) ?>">
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remove_imagen" id="remove_<?= (int) $service['id'] ?>">
            <label class="form-check-label" for="remove_<?= (int) $service['id'] ?>">Quitar imagen actual</label>
          </div>
        </div>

        <div class="col-md-8">
          <label class="form-label">Imagen URL</label>
          <input name="imagen" class="form-control" value="<?= esc((string) $service['imagen']) ?>" placeholder="https://... o assets/...">
        </div>
        <div class="col-md-4">
          <label class="form-label">Subir imagen</label>
          <input type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-control">
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center">
          <small class="text-secondary">ID #<?= (int) $service['id'] ?></small>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-danger js-confirm" type="submit" name="delete_servicio" value="1" data-confirm="¿Eliminar servicio #<?= (int) $service['id'] ?>?">
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
