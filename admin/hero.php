<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_admin_roles(['admin']);

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hero_slides (
            id INT AUTO_INCREMENT PRIMARY KEY,
            badge VARCHAR(140) NOT NULL DEFAULT '',
            titulo VARCHAR(220) NOT NULL,
            descripcion TEXT NOT NULL,
            imagen VARCHAR(255) DEFAULT '',
            cta_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Agendar',
            cta_principal_href VARCHAR(180) NOT NULL DEFAULT '#contacto',
            cta_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Ver mas',
            cta_secundario_href VARCHAR(180) NOT NULL DEFAULT '#servicios',
            card_titulo VARCHAR(180) NOT NULL DEFAULT '',
            card_item_1 VARCHAR(180) NOT NULL DEFAULT '',
            card_item_2 VARCHAR(180) NOT NULL DEFAULT '',
            card_item_3 VARCHAR(180) NOT NULL DEFAULT '',
            card_icono VARCHAR(60) NOT NULL DEFAULT 'bi-shield-check',
            card_footer_titulo VARCHAR(180) NOT NULL DEFAULT '',
            card_footer_descripcion VARCHAR(220) NOT NULL DEFAULT '',
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT '' AFTER descripcion");
} catch (Throwable $e) {
    // Mantener el modulo operativo aunque la migracion falle.
}

$flash = '';
$flashClass = 'success';
$uploadDirFs = __DIR__ . '/../assets/uploads/hero';
$uploadDirWeb = 'assets/uploads/hero';

function sanitize_hero_icon(string $icon): string
{
    $icon = trim($icon);
    return preg_match('/^bi-[a-z0-9-]+$/i', $icon) === 1 ? $icon : 'bi-shield-check';
}

function normalize_hero_href(string $href, string $default): string
{
    $href = trim($href);
    if ($href === '') {
        return $default;
    }

    if ($href[0] === '#') {
        return preg_match('/^#[a-zA-Z][a-zA-Z0-9_-]*$/', $href) === 1 ? $href : $default;
    }

    if (filter_var($href, FILTER_VALIDATE_URL) !== false) {
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $href;
        }
    }

    if (preg_match('/^[a-z0-9][a-z0-9_\\/-]*\\.php(?:\\?[a-z0-9_=&-]*)?$/i', $href) === 1) {
        return $href;
    }

    return $default;
}

function normalize_hero_image(string $image): string
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

function hero_image_preview_src(string $image): string
{
    $normalized = normalize_hero_image($image);
    if ($normalized === '') {
        return '';
    }
    if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
        return $normalized;
    }
    return '../' . ltrim($normalized, '/');
}

function process_uploaded_hero_image(string $field, string $uploadDirFs, string $uploadDirWeb, string &$error): ?string
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

    $filename = 'hero_' . date('Ymd_His') . '_' . $suffix . '.' . $allowed[$mime];
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

    if ($action === 'crear_slide') {
        $badge = trim((string) ($_POST['badge'] ?? ''));
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $imagen = normalize_hero_image((string) ($_POST['imagen'] ?? ''));
        $ctaPrincipalTexto = trim((string) ($_POST['cta_principal_texto'] ?? 'Agendar'));
        $ctaPrincipalHref = normalize_hero_href((string) ($_POST['cta_principal_href'] ?? ''), '#contacto');
        $ctaSecundarioTexto = trim((string) ($_POST['cta_secundario_texto'] ?? 'Ver mas'));
        $ctaSecundarioHref = normalize_hero_href((string) ($_POST['cta_secundario_href'] ?? ''), '#servicios');
        $cardTitulo = trim((string) ($_POST['card_titulo'] ?? ''));
        $cardItem1 = trim((string) ($_POST['card_item_1'] ?? ''));
        $cardItem2 = trim((string) ($_POST['card_item_2'] ?? ''));
        $cardItem3 = trim((string) ($_POST['card_item_3'] ?? ''));
        $cardIcono = sanitize_hero_icon((string) ($_POST['card_icono'] ?? 'bi-shield-check'));
        $cardFooterTitulo = trim((string) ($_POST['card_footer_titulo'] ?? ''));
        $cardFooterDescripcion = trim((string) ($_POST['card_footer_descripcion'] ?? ''));
        $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
        $activo = isset($_POST['activo']) ? 1 : 0;

        $uploadError = '';
        $uploadedImage = process_uploaded_hero_image('imagen_archivo', $uploadDirFs, $uploadDirWeb, $uploadError);
        if ($uploadedImage !== null) {
            $imagen = $uploadedImage;
        } elseif ($uploadError !== '') {
            $flash = $uploadError;
            $flashClass = 'danger';
        }

        if ($flashClass !== 'danger') {
            if ($titulo === '' || $descripcion === '' || $cardTitulo === '') {
                $flash = 'Titulo, descripcion y titulo de tarjeta son obligatorios.';
                $flashClass = 'danger';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO hero_slides (
                        badge, titulo, descripcion, imagen, cta_principal_texto, cta_principal_href,
                        cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1,
                        card_item_2, card_item_3, card_icono, card_footer_titulo, card_footer_descripcion,
                        orden, activo
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $badge,
                    $titulo,
                    $descripcion,
                    $imagen,
                    $ctaPrincipalTexto !== '' ? $ctaPrincipalTexto : 'Agendar',
                    $ctaPrincipalHref,
                    $ctaSecundarioTexto !== '' ? $ctaSecundarioTexto : 'Ver mas',
                    $ctaSecundarioHref,
                    $cardTitulo,
                    $cardItem1,
                    $cardItem2,
                    $cardItem3,
                    $cardIcono,
                    $cardFooterTitulo,
                    $cardFooterDescripcion,
                    $orden,
                    $activo,
                ]);
                $flash = 'Slide creado correctamente.';
                $flashClass = 'success';
            }
        }
    }

    if ($action === 'actualizar_slide') {
        $id = (int) ($_POST['id'] ?? 0);
        $badge = trim((string) ($_POST['badge'] ?? ''));
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $imagenInput = normalize_hero_image((string) ($_POST['imagen'] ?? ''));
        $removeImagen = isset($_POST['remove_imagen']);
        $ctaPrincipalTexto = trim((string) ($_POST['cta_principal_texto'] ?? 'Agendar'));
        $ctaPrincipalHref = normalize_hero_href((string) ($_POST['cta_principal_href'] ?? ''), '#contacto');
        $ctaSecundarioTexto = trim((string) ($_POST['cta_secundario_texto'] ?? 'Ver mas'));
        $ctaSecundarioHref = normalize_hero_href((string) ($_POST['cta_secundario_href'] ?? ''), '#servicios');
        $cardTitulo = trim((string) ($_POST['card_titulo'] ?? ''));
        $cardItem1 = trim((string) ($_POST['card_item_1'] ?? ''));
        $cardItem2 = trim((string) ($_POST['card_item_2'] ?? ''));
        $cardItem3 = trim((string) ($_POST['card_item_3'] ?? ''));
        $cardIcono = sanitize_hero_icon((string) ($_POST['card_icono'] ?? 'bi-shield-check'));
        $cardFooterTitulo = trim((string) ($_POST['card_footer_titulo'] ?? ''));
        $cardFooterDescripcion = trim((string) ($_POST['card_footer_descripcion'] ?? ''));
        $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
        $activo = ((string) ($_POST['activo'] ?? '1')) === '1' ? 1 : 0;

        if ($id <= 0) {
            $flash = 'Slide invalido.';
            $flashClass = 'danger';
        } elseif ($titulo === '' || $descripcion === '' || $cardTitulo === '') {
            $flash = 'Titulo, descripcion y titulo de tarjeta son obligatorios.';
            $flashClass = 'danger';
        } else {
            $currentStmt = $pdo->prepare('SELECT imagen FROM hero_slides WHERE id = ? LIMIT 1');
            $currentStmt->execute([$id]);
            $current = $currentStmt->fetch();
            if (!$current) {
                $flash = 'No existe el slide seleccionado.';
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
                $uploadedImage = process_uploaded_hero_image('imagen_archivo', $uploadDirFs, $uploadDirWeb, $uploadError);
                if ($uploadedImage !== null) {
                    $imagen = $uploadedImage;
                } elseif ($uploadError !== '') {
                    $flash = $uploadError;
                    $flashClass = 'danger';
                }

                if ($flashClass !== 'danger') {
                    $update = $pdo->prepare(
                        'UPDATE hero_slides
                         SET badge = ?, titulo = ?, descripcion = ?, imagen = ?, cta_principal_texto = ?, cta_principal_href = ?,
                             cta_secundario_texto = ?, cta_secundario_href = ?, card_titulo = ?, card_item_1 = ?,
                             card_item_2 = ?, card_item_3 = ?, card_icono = ?, card_footer_titulo = ?,
                             card_footer_descripcion = ?, orden = ?, activo = ?
                         WHERE id = ?'
                    );
                    $update->execute([
                        $badge,
                        $titulo,
                        $descripcion,
                        $imagen,
                        $ctaPrincipalTexto !== '' ? $ctaPrincipalTexto : 'Agendar',
                        $ctaPrincipalHref,
                        $ctaSecundarioTexto !== '' ? $ctaSecundarioTexto : 'Ver mas',
                        $ctaSecundarioHref,
                        $cardTitulo,
                        $cardItem1,
                        $cardItem2,
                        $cardItem3,
                        $cardIcono,
                        $cardFooterTitulo,
                        $cardFooterDescripcion,
                        $orden,
                        $activo,
                        $id,
                    ]);
                    $flash = 'Slide actualizado correctamente.';
                    $flashClass = 'success';
                }
            }
        }
    }
}

$slides = [];
try {
    $slides = $pdo->query(
        'SELECT id, badge, titulo, descripcion, imagen, cta_principal_texto, cta_principal_href,
                cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1,
                card_item_2, card_item_3, card_icono, card_footer_titulo, card_footer_descripcion,
                orden, activo
         FROM hero_slides
         ORDER BY orden ASC, id ASC'
    )->fetchAll();
} catch (Throwable $e) {
    $slides = [];
}

admin_header('Hero web', 'hero', 'Gestion dinamica del carrusel principal de la portada.');
?>
<?php if ($flash !== ''): ?>
<div class="alert alert-<?= esc($flashClass) ?>"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="admin-card p-3 p-lg-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Nuevo slide</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-soft"><?= count($slides) ?> slides</span>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoSlide">
        <i class="bi bi-plus-circle me-1"></i>Nuevo slide
      </button>
    </div>
  </div>
  <p class="text-secondary mb-0">Usa el boton "Nuevo slide" para crear un elemento del carrusel.</p>
</section>

<div class="modal fade" id="modalNuevoSlide" tabindex="-1" aria-labelledby="modalNuevoSlideLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalNuevoSlideLabel">Nuevo slide</h2>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formNuevoSlide">
            <i class="bi bi-save me-1"></i>Guardar cambios
          </button>
        </div>
      </div>
      <form method="POST" enctype="multipart/form-data" id="formNuevoSlide">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="crear_slide">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Badge</label>
              <input name="badge" class="form-control" placeholder="Salud mental con enfoque clinico">
            </div>
            <div class="col-md-6">
              <label class="form-label">Titulo</label>
              <input name="titulo" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Orden</label>
              <input type="number" min="0" max="9999" name="orden" value="0" class="form-control">
            </div>

            <div class="col-12">
              <label class="form-label">Descripcion</label>
              <textarea name="descripcion" class="form-control" rows="2" required></textarea>
            </div>
            <div class="col-md-8">
              <label class="form-label">Imagen hero (URL o assets/...)</label>
              <input name="imagen" class="form-control" placeholder="https://... o assets/uploads/...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Subir desde escritorio</label>
              <input type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-control">
            </div>

            <div class="col-md-3">
              <label class="form-label">Boton principal</label>
              <input name="cta_principal_texto" class="form-control" value="Agendar">
            </div>
            <div class="col-md-3">
              <label class="form-label">Link principal</label>
              <input name="cta_principal_href" class="form-control" value="#contacto">
            </div>
            <div class="col-md-3">
              <label class="form-label">Boton secundario</label>
              <input name="cta_secundario_texto" class="form-control" value="Ver mas">
            </div>
            <div class="col-md-3">
              <label class="form-label">Link secundario</label>
              <input name="cta_secundario_href" class="form-control" value="#servicios">
            </div>

            <div class="col-md-6">
              <label class="form-label">Titulo tarjeta derecha</label>
              <input name="card_titulo" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Icono tarjeta (Bootstrap)</label>
              <input name="card_icono" class="form-control" value="bi-shield-check">
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="activo" id="new_slide_activo" checked>
                <label class="form-check-label" for="new_slide_activo">Activo en web</label>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Item 1</label>
              <input name="card_item_1" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Item 2</label>
              <input name="card_item_2" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Item 3</label>
              <input name="card_item_3" class="form-control">
            </div>

            <div class="col-md-6">
              <label class="form-label">Footer tarjeta titulo</label>
              <input name="card_footer_titulo" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Footer tarjeta descripcion</label>
              <input name="card_footer_descripcion" class="form-control">
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (!$slides): ?>
<section class="admin-card p-3 p-lg-4">
  <p class="text-secondary mb-0">No hay slides. Crea uno para activar el carrusel dinamico.</p>
</section>
<?php else: ?>
<section class="admin-card p-3 p-lg-4 mb-3">
  <h2 class="h5 mb-3">Slides registrados</h2>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Orden</th>
          <th>Titulo</th>
          <th>Imagen</th>
          <th>Botones</th>
          <th>Icono</th>
          <th>Estado</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($slides as $slide): ?>
        <tr>
          <td>#<?= (int) $slide['id'] ?></td>
          <td><?= (int) $slide['orden'] ?></td>
          <td>
            <div class="fw-semibold"><?= esc((string) $slide['titulo']) ?></div>
            <small class="text-secondary"><?= esc((string) $slide['badge']) ?></small>
          </td>
          <td>
            <?php $heroPreview = hero_image_preview_src((string) ($slide['imagen'] ?? '')); ?>
            <?php if ($heroPreview !== ''): ?>
            <img src="<?= esc($heroPreview) ?>" alt="<?= esc('Imagen slide ' . (string) $slide['titulo']) ?>" style="width:70px;height:46px;object-fit:cover;border-radius:8px;border:1px solid #d7e3f2;">
            <?php else: ?>
            <span class="text-secondary small">Sin imagen</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="small">
              <span class="fw-semibold"><?= esc((string) $slide['cta_principal_texto']) ?></span>
              <span class="text-secondary"> | </span>
              <span><?= esc((string) $slide['cta_secundario_texto']) ?></span>
            </div>
          </td>
          <td><code><?= esc((string) $slide['card_icono']) ?></code></td>
          <td>
            <span class="badge <?= (int) $slide['activo'] === 1 ? 'badge-soft' : 'text-bg-secondary' ?>">
              <?= (int) $slide['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalSlide<?= (int) $slide['id'] ?>">
              <i class="bi bi-pencil-square me-1"></i>Editar
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($slides as $slide): ?>
<div class="modal fade" id="modalSlide<?= (int) $slide['id'] ?>" tabindex="-1" aria-labelledby="modalSlideLabel<?= (int) $slide['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalSlideLabel<?= (int) $slide['id'] ?>">Editar slide #<?= (int) $slide['id'] ?></h2>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formSlide<?= (int) $slide['id'] ?>">
            <i class="bi bi-save me-1"></i>Guardar cambios
          </button>
        </div>
      </div>
      <form method="POST" enctype="multipart/form-data" id="formSlide<?= (int) $slide['id'] ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="actualizar_slide">
        <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Badge</label>
              <input name="badge" class="form-control" value="<?= esc((string) $slide['badge']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Titulo</label>
              <input name="titulo" class="form-control" value="<?= esc((string) $slide['titulo']) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Orden</label>
              <input type="number" min="0" max="9999" name="orden" class="form-control" value="<?= (int) $slide['orden'] ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Descripcion</label>
              <textarea name="descripcion" class="form-control" rows="2" required><?= esc((string) $slide['descripcion']) ?></textarea>
            </div>
            <div class="col-md-8">
              <label class="form-label">Imagen hero (URL o assets/...)</label>
              <input name="imagen" class="form-control" value="<?= esc((string) ($slide['imagen'] ?? '')) ?>" placeholder="https://... o assets/uploads/...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Subir desde escritorio</label>
              <input type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,.webp,.gif" class="form-control">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remove_imagen" id="remove_slide_<?= (int) $slide['id'] ?>">
                <label class="form-check-label" for="remove_slide_<?= (int) $slide['id'] ?>">Quitar imagen actual</label>
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Boton principal</label>
              <input name="cta_principal_texto" class="form-control" value="<?= esc((string) $slide['cta_principal_texto']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Link principal</label>
              <input name="cta_principal_href" class="form-control" value="<?= esc((string) $slide['cta_principal_href']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Boton secundario</label>
              <input name="cta_secundario_texto" class="form-control" value="<?= esc((string) $slide['cta_secundario_texto']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Link secundario</label>
              <input name="cta_secundario_href" class="form-control" value="<?= esc((string) $slide['cta_secundario_href']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Titulo tarjeta derecha</label>
              <input name="card_titulo" class="form-control" value="<?= esc((string) $slide['card_titulo']) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Icono tarjeta</label>
              <input name="card_icono" class="form-control" value="<?= esc((string) $slide['card_icono']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Activo</label>
              <select name="activo" class="form-select">
                <option value="1" <?= (int) $slide['activo'] === 1 ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= (int) $slide['activo'] !== 1 ? 'selected' : '' ?>>No</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Item 1</label>
              <input name="card_item_1" class="form-control" value="<?= esc((string) $slide['card_item_1']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Item 2</label>
              <input name="card_item_2" class="form-control" value="<?= esc((string) $slide['card_item_2']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Item 3</label>
              <input name="card_item_3" class="form-control" value="<?= esc((string) $slide['card_item_3']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Footer titulo</label>
              <input name="card_footer_titulo" class="form-control" value="<?= esc((string) $slide['card_footer_titulo']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Footer descripcion</label>
              <input name="card_footer_descripcion" class="form-control" value="<?= esc((string) $slide['card_footer_descripcion']) ?>">
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php admin_footer(); ?>
