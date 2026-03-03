<?php
require_once __DIR__ . '/db.php';

$ok = isset($_GET['ok']) && $_GET['ok'] === '1';
$error = $_GET['error'] ?? '';

$services = [];
$teamMembers = [];
$heroSlides = [];

try {
    try {
        $stmt = $pdo->query(
            "SELECT id, nombre, descripcion, icono, imagen
             FROM servicios
             WHERE activo = 1
             ORDER BY orden ASC, id ASC"
        );
        $services = $stmt->fetchAll();
    } catch (Throwable $e) {
        // Compatibilidad con bases antiguas sin la columna "imagen".
        $stmt = $pdo->query(
            "SELECT id, nombre, descripcion, icono
             FROM servicios
             WHERE activo = 1
             ORDER BY orden ASC, id ASC"
        );
        $services = $stmt->fetchAll();
        foreach ($services as &$service) {
            $service['imagen'] = '';
        }
        unset($service);
    }
} catch (Throwable $e) {
    $services = [];
}

if (!$services) {
    $services = [
        [
            'id' => 1,
            'nombre' => 'Terapia Individual',
            'descripcion' => 'Ansiedad, depresion, estres laboral y desarrollo personal.',
            'icono' => 'bi-person-heart',
            'imagen' => 'https://images.pexels.com/photos/7579309/pexels-photo-7579309.jpeg?auto=compress&cs=tinysrgb&w=1200',
        ],
        [
            'id' => 2,
            'nombre' => 'Terapia de Pareja',
            'descripcion' => 'Comunicacion asertiva, acuerdos y reparacion del vinculo.',
            'icono' => 'bi-people',
            'imagen' => 'https://images.pexels.com/photos/3958383/pexels-photo-3958383.jpeg?auto=compress&cs=tinysrgb&w=1200',
        ],
        [
            'id' => 3,
            'nombre' => 'Psicologia Infantil',
            'descripcion' => 'Evaluacion y acompanamiento emocional para ninos y familias.',
            'icono' => 'bi-balloon-heart',
            'imagen' => 'https://images.pexels.com/photos/8654102/pexels-photo-8654102.jpeg?auto=compress&cs=tinysrgb&w=1200',
        ],
        [
            'id' => 4,
            'nombre' => 'Orientacion Familiar',
            'descripcion' => 'Fortalecimiento sistemico y resolucion de conflictos familiares.',
            'icono' => 'bi-diagram-3',
            'imagen' => 'https://images.pexels.com/photos/5336930/pexels-photo-5336930.jpeg?auto=compress&cs=tinysrgb&w=1200',
        ],
    ];
}

try {
    $stmt = $pdo->query(
        "SELECT id, nombre, cargo, descripcion, iniciales, imagen
         FROM equipo_web
         WHERE activo = 1
         ORDER BY orden ASC, id ASC"
    );
    $teamMembers = $stmt->fetchAll();
} catch (Throwable $e) {
    $teamMembers = [];
}

if (!$teamMembers) {
    $teamMembers = [
        [
            'id' => 1,
            'nombre' => 'Dra. Maria Lopez',
            'cargo' => 'Psicologia Clinica',
            'descripcion' => 'Trauma, ansiedad y bienestar emocional',
            'iniciales' => 'ML',
            'imagen' => '',
        ],
        [
            'id' => 2,
            'nombre' => 'Mgtr. Carlos Perez',
            'cargo' => 'Terapia Familiar',
            'descripcion' => 'Pareja, limites y comunicacion efectiva',
            'iniciales' => 'CP',
            'imagen' => '',
        ],
        [
            'id' => 3,
            'nombre' => 'Lic. Ana Torres',
            'cargo' => 'Psicologia Infantil',
            'descripcion' => 'Intervencion emocional en primera infancia',
            'iniciales' => 'AT',
            'imagen' => '',
        ],
    ];
}

try {
    try {
        $stmt = $pdo->query(
            "SELECT id, badge, titulo, descripcion, imagen, cta_principal_texto, cta_principal_href,
                    cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1,
                    card_item_2, card_item_3, card_icono, card_footer_titulo, card_footer_descripcion
             FROM hero_slides
             WHERE activo = 1
             ORDER BY orden ASC, id ASC"
        );
        $heroSlides = $stmt->fetchAll();
    } catch (Throwable $e) {
        // Compatibilidad con bases antiguas sin la columna "imagen".
        $stmt = $pdo->query(
            "SELECT id, badge, titulo, descripcion, cta_principal_texto, cta_principal_href,
                    cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1,
                    card_item_2, card_item_3, card_icono, card_footer_titulo, card_footer_descripcion
             FROM hero_slides
             WHERE activo = 1
             ORDER BY orden ASC, id ASC"
        );
        $heroSlides = $stmt->fetchAll();
        foreach ($heroSlides as &$slide) {
            $slide['imagen'] = '';
        }
        unset($slide);
    }
} catch (Throwable $e) {
    $heroSlides = [];
}

if (!$heroSlides) {
    $heroSlides = [
        [
            'id' => 1,
            'badge' => 'Salud mental con enfoque clinico',
            'titulo' => 'Centro Psicologico Integral para ninos, jovenes y adultos',
            'descripcion' => 'Acompanamiento terapeutico profesional, evaluacion clinica y seguimiento continuo para tu bienestar emocional.',
            'imagen' => 'https://images.pexels.com/photos/4101143/pexels-photo-4101143.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'cta_principal_texto' => 'Reservar valoracion',
            'cta_principal_href' => '#contacto',
            'cta_secundario_texto' => 'Conocer servicios',
            'cta_secundario_href' => '#servicios',
            'card_titulo' => 'Atencion profesional y confidencial',
            'card_item_1' => 'Especialistas certificados',
            'card_item_2' => 'Modalidad presencial y online',
            'card_item_3' => 'Planes para acompanamiento continuo',
            'card_icono' => 'bi-shield-check',
            'card_footer_titulo' => 'Confidencialidad garantizada',
            'card_footer_descripcion' => 'Protocolos eticos y clinicos vigentes',
        ],
        [
            'id' => 2,
            'badge' => 'Agenda flexible y seguimiento',
            'titulo' => 'Terapia presencial y online adaptada a tu ritmo',
            'descripcion' => 'Sesiones personalizadas, objetivos medibles y acompanamiento continuo para fortalecer tu bienestar integral.',
            'imagen' => 'https://images.pexels.com/photos/7176319/pexels-photo-7176319.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'cta_principal_texto' => 'Agendar primera cita',
            'cta_principal_href' => '#contacto',
            'cta_secundario_texto' => 'Ver planes',
            'cta_secundario_href' => '#planes',
            'card_titulo' => 'Acompanamiento estructurado',
            'card_item_1' => 'Objetivos terapeuticos por etapa',
            'card_item_2' => 'Evaluaciones periodicas de avance',
            'card_item_3' => 'Plan de accion para casa',
            'card_icono' => 'bi-clipboard2-pulse',
            'card_footer_titulo' => 'Metodo basado en evidencia',
            'card_footer_descripcion' => 'Intervenciones con respaldo clinico',
        ],
        [
            'id' => 3,
            'badge' => 'Atencion familiar e infantil',
            'titulo' => 'Espacios seguros para ninos, parejas y familias',
            'descripcion' => 'Fortalece la comunicacion, regula emociones y construye relaciones saludables con apoyo profesional especializado.',
            'imagen' => 'https://images.pexels.com/photos/5699479/pexels-photo-5699479.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'cta_principal_texto' => 'Conocer equipo',
            'cta_principal_href' => '#equipo',
            'cta_secundario_texto' => 'Solicitar orientacion',
            'cta_secundario_href' => '#contacto',
            'card_titulo' => 'Intervencion integral',
            'card_item_1' => 'Terapia individual y de pareja',
            'card_item_2' => 'Psicologia infantil y familiar',
            'card_item_3' => 'Red de apoyo y coordinacion',
            'card_icono' => 'bi-people',
            'card_footer_titulo' => 'Enfoque humano y etico',
            'card_footer_descripcion' => 'Atencion cercana y profesional',
        ],
    ];
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalize_icon_class(string $icon): string
{
    return preg_match('/^bi-[a-z0-9-]+$/i', $icon) === 1 ? $icon : 'bi-stars';
}

function normalize_image_src(string $image): string
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
        return $image;
    }

    return '';
}

function cloud_service_image_by_name(string $serviceName): string
{
    $key = strtolower(trim($serviceName));

    $map = [
        'terapia individual' => 'https://images.pexels.com/photos/7579309/pexels-photo-7579309.jpeg?auto=compress&cs=tinysrgb&w=1200',
        'terapia de pareja' => 'https://images.pexels.com/photos/3958383/pexels-photo-3958383.jpeg?auto=compress&cs=tinysrgb&w=1200',
        'psicologia infantil' => 'https://images.pexels.com/photos/8654102/pexels-photo-8654102.jpeg?auto=compress&cs=tinysrgb&w=1200',
        'orientacion familiar' => 'https://images.pexels.com/photos/5336930/pexels-photo-5336930.jpeg?auto=compress&cs=tinysrgb&w=1200',
    ];

    return $map[$key] ?? '';
}

function team_initials(string $name, string $fallback = 'PB'): string
{
    $name = trim($name);
    if ($name === '') {
        return strtoupper($fallback);
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    if ($initials === '') {
        return strtoupper($fallback);
    }

    return substr($initials, 0, 2);
}

function normalize_link_href(string $href, string $default = '#contacto'): string
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

function hero_slide_cloud_image(int $index): string
{
    $images = [
        'https://images.pexels.com/photos/4101143/pexels-photo-4101143.jpeg?auto=compress&cs=tinysrgb&w=1200',
        'https://images.pexels.com/photos/7176319/pexels-photo-7176319.jpeg?auto=compress&cs=tinysrgb&w=1200',
        'https://images.pexels.com/photos/5699479/pexels-photo-5699479.jpeg?auto=compress&cs=tinysrgb&w=1200',
    ];

    $idx = $index % count($images);
    if ($idx < 0) {
        $idx = 0;
    }
    return $images[$idx];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PsicoBienestar | Centro Psicologico Integral</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/site.css" rel="stylesheet">
</head>
<body>
  <aside class="floating-social" aria-label="Redes sociales">
    <a href="https://www.facebook.com/mariafernanda.gutierres.564?locale=es_LA" target="_blank" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
    <a href="https://www.instagram.com/mafercitagutierres/" target="_blank" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
    <a href="#" target="_blank" aria-label="Twitter"><i class="bi bi-twitter"></i></a>
    <a href="#" target="_blank" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
    <a href="#" target="_blank" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
  </aside>

  <div class="topbar text-white py-2 small">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
      <span><i class="bi bi-clock me-1"></i>Lun-Sab 08:00 - 20:00 | Atencion presencial y online</span>
      <span><i class="bi bi-telephone me-1"></i>+593 98 703 6924 | <i class="bi bi-envelope me-1"></i>contacto@psicobienestar.com</span>
    </div>
  </div>

  <nav class="navbar navbar-expand-lg navbar-light sticky-top navbar-shell">
    <div class="container py-1">
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#">
        <span class="brand-mark">PB</span>
        <span>PsicoBienestar</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
          <li class="nav-item"><a class="nav-link js-scroll" href="#servicios">Servicios</a></li>
          <li class="nav-item"><a class="nav-link js-scroll" href="#equipo">Equipo</a></li>
          <li class="nav-item"><a class="nav-link js-scroll" href="#planes">Planes</a></li>
          <li class="nav-item"><a class="nav-link js-scroll" href="#testimonios">Testimonios</a></li>
          <li class="nav-item"><a class="nav-link js-scroll" href="#contacto">Contacto</a></li>
          <li class="nav-item"><a class="btn btn-outline-primary btn-sm ms-lg-2" href="admin/login.php">Panel</a></li>
          <li class="nav-item"><a class="btn btn-primary btn-sm ms-lg-2 js-scroll" href="#contacto">Agendar cita</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <section class="hero py-3 py-lg-4">
    <div class="container py-2 py-lg-3">
      <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-interval="6500">
        <div class="carousel-indicators">
          <?php foreach ($heroSlides as $idx => $slide): ?>
          <button
            type="button"
            data-bs-target="#heroCarousel"
            data-bs-slide-to="<?= (int) $idx ?>"
            class="<?= $idx === 0 ? 'active' : '' ?>"
            <?= $idx === 0 ? 'aria-current="true"' : '' ?>
            aria-label="Slide <?= (int) ($idx + 1) ?>"></button>
          <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
          <?php foreach ($heroSlides as $idx => $slide): ?>
          <?php
          $primaryHref = normalize_link_href((string) ($slide['cta_principal_href'] ?? ''), '#contacto');
          $secondaryHref = normalize_link_href((string) ($slide['cta_secundario_href'] ?? ''), '#servicios');
          $cardIcon = normalize_icon_class((string) ($slide['card_icono'] ?? 'bi-shield-check'));
          $heroImage = normalize_image_src((string) ($slide['imagen'] ?? ''));
          if ($heroImage === '') {
              $heroImage = hero_slide_cloud_image((int) $idx);
          }
          $cardItems = [];
          foreach (['card_item_1', 'card_item_2', 'card_item_3'] as $itemKey) {
              $itemValue = trim((string) ($slide[$itemKey] ?? ''));
              if ($itemValue !== '') {
                  $cardItems[] = $itemValue;
              }
          }
          ?>
          <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
            <div class="row align-items-center g-4">
              <div class="col-lg-7">
                <?php if (trim((string) ($slide['badge'] ?? '')) !== ''): ?>
                <span class="badge rounded-pill text-bg-light border border-primary-subtle text-primary mb-3"><?= esc_html((string) $slide['badge']) ?></span>
                <?php endif; ?>
                <?php if ($idx === 0): ?>
                <h1 class="display-5 fw-bold mb-3 section-title"><?= esc_html((string) ($slide['titulo'] ?? '')) ?></h1>
                <?php else: ?>
                <h2 class="display-5 fw-bold mb-3 section-title"><?= esc_html((string) ($slide['titulo'] ?? '')) ?></h2>
                <?php endif; ?>
                <p class="lead text-secondary mb-4"><?= esc_html((string) ($slide['descripcion'] ?? '')) ?></p>
                <div class="d-flex flex-wrap gap-2">
                  <a href="<?= esc_html($primaryHref) ?>" class="btn btn-primary btn-lg js-scroll"><?= esc_html((string) ($slide['cta_principal_texto'] ?? 'Agendar')) ?></a>
                  <a href="<?= esc_html($secondaryHref) ?>" class="btn btn-outline-primary btn-lg js-scroll"><?= esc_html((string) ($slide['cta_secundario_texto'] ?? 'Ver mas')) ?></a>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="hero-visual mb-3">
                  <img
                    class="hero-visual-image"
                    src="<?= esc_html($heroImage) ?>"
                    alt="<?= esc_html('Imagen referencial: ' . (string) ($slide['titulo'] ?? 'Atencion psicologica')) ?>"
                    loading="lazy">
                </div>
                <div class="hero-card bg-white rounded-4 p-4 p-lg-5">
                  <h2 class="h4 fw-bold mb-3"><?= esc_html((string) ($slide['card_titulo'] ?? '')) ?></h2>
                  <?php if ($cardItems): ?>
                  <ul class="list-unstyled mb-4">
                    <?php foreach ($cardItems as $cardItem): ?>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i><?= esc_html($cardItem) ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                  <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-subtle text-primary d-grid place-content-center p-3">
                      <i class="bi <?= esc_html($cardIcon) ?> fs-4"></i>
                    </div>
                    <div>
                      <div class="fw-semibold"><?= esc_html((string) ($slide['card_footer_titulo'] ?? '')) ?></div>
                      <small class="text-secondary"><?= esc_html((string) ($slide['card_footer_descripcion'] ?? '')) ?></small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Siguiente</span>
        </button>
      </div>

      <div class="row g-3 mt-2">
        <div class="col-6 col-lg-3 reveal">
          <div class="stat-card p-3">
            <div class="fw-bold fs-3">+1200</div>
            <small class="text-secondary">Sesiones realizadas</small>
          </div>
        </div>
        <div class="col-6 col-lg-3 reveal">
          <div class="stat-card p-3">
            <div class="fw-bold fs-3">97%</div>
            <small class="text-secondary">Satisfaccion de pacientes</small>
          </div>
        </div>
        <div class="col-6 col-lg-3 reveal">
          <div class="stat-card p-3">
            <div class="fw-bold fs-3">+8</div>
            <small class="text-secondary">Anos de experiencia</small>
          </div>
        </div>
        <div class="col-6 col-lg-3 reveal">
          <div class="stat-card p-3">
            <div class="fw-bold fs-3">24h</div>
            <small class="text-secondary">Confirmacion de citas</small>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="servicios" class="py-5 py-lg-6">
    <div class="container">
      <div class="text-center mb-5 reveal">
        <h2 class="display-6 fw-bold section-title">Servicios especializados</h2>
        <p class="text-secondary">Intervenciones basadas en evidencia para cada etapa de vida.</p>
      </div>
      <div class="row g-4">
        <?php foreach ($services as $service): ?>
        <?php
        $serviceImage = normalize_image_src((string) ($service['imagen'] ?? ''));
        if ($serviceImage === '') {
            $serviceImage = cloud_service_image_by_name((string) ($service['nombre'] ?? ''));
        }
        ?>
        <div class="col-md-6 col-xl-3 reveal">
          <article class="service-card p-4 h-100 d-flex flex-column">
            <?php if ($serviceImage !== ''): ?>
            <img
              class="service-image mb-3"
              src="<?= esc_html($serviceImage) ?>"
              alt="<?= esc_html('Imagen de ' . (string) $service['nombre']) ?>"
              loading="lazy">
            <?php else: ?>
            <div class="service-icon mb-3"><i class="bi <?= esc_html(normalize_icon_class((string) $service['icono'])) ?>"></i></div>
            <?php endif; ?>
            <h3 class="h5 fw-bold"><?= esc_html((string) $service['nombre']) ?></h3>
            <p class="text-secondary mb-3"><?= esc_html((string) $service['descripcion']) ?></p>
            <a href="#contacto" class="btn btn-outline-primary w-100 mt-auto js-scroll">Reservar</a>
          </article>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="equipo" class="py-5 py-lg-6 bg-white">
    <div class="container">
      <div class="text-center mb-5 reveal">
        <h2 class="display-6 fw-bold section-title">Equipo profesional</h2>
        <p class="text-secondary">Especialistas con experiencia clinica y enfoque humano.</p>
      </div>
      <div class="row g-4">
        <?php foreach ($teamMembers as $member): ?>
        <?php
        $teamImage = normalize_image_src((string) ($member['imagen'] ?? ''));
        $teamInitials = trim((string) ($member['iniciales'] ?? ''));
        if ($teamInitials === '') {
            $teamInitials = team_initials((string) ($member['nombre'] ?? ''), 'PB');
        }
        ?>
        <div class="col-md-4 reveal">
          <article class="team-card p-4 text-center h-100">
            <?php if ($teamImage !== ''): ?>
            <img
              class="team-photo mx-auto mb-3"
              src="<?= esc_html($teamImage) ?>"
              alt="<?= esc_html('Foto de ' . (string) ($member['nombre'] ?? 'Profesional')) ?>"
              loading="lazy">
            <?php else: ?>
            <div class="team-avatar mx-auto mb-3"><?= esc_html($teamInitials) ?></div>
            <?php endif; ?>
            <h3 class="h5 fw-bold mb-1"><?= esc_html((string) ($member['nombre'] ?? 'Profesional')) ?></h3>
            <p class="text-secondary mb-2"><?= esc_html((string) ($member['cargo'] ?? 'Especialista')) ?></p>
            <small class="text-secondary"><?= esc_html((string) ($member['descripcion'] ?? '')) ?></small>
          </article>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="planes" class="py-5 py-lg-6">
    <div class="container">
      <div class="text-center mb-5 reveal">
        <h2 class="display-6 fw-bold section-title">Planes y tarifas</h2>
        <p class="text-secondary">Opciones flexibles para necesidades especificas.</p>
      </div>
      <div class="row g-4">
        <div class="col-lg-4 reveal">
          <article class="plan-card p-4 h-100">
            <h3 class="h5 fw-bold">Sesion Individual</h3>
            <div class="display-6 fw-bold my-3">$30</div>
            <ul class="list-unstyled mb-4">
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>45 minutos</li>
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>Online o presencial</li>
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>Seguimiento basico</li>
            </ul>
            <a href="#contacto" class="btn btn-outline-primary w-100 js-scroll">Reservar</a>
          </article>
        </div>
        <div class="col-lg-4 reveal">
          <article class="plan-card plan-highlight p-4 h-100">
            <span class="badge text-bg-primary rounded-pill mb-3">Mas elegido</span>
            <h3 class="h5 fw-bold">Plan Mensual</h3>
            <div class="display-6 fw-bold my-3">$100</div>
            <ul class="list-unstyled mb-4">
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>4 sesiones</li>
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>Prioridad en agenda</li>
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>Evaluacion psicologica</li>
            </ul>
            <a href="#contacto" class="btn btn-primary w-100 js-scroll">Reservar</a>
          </article>
        </div>
        <div class="col-lg-4 reveal">
          <article class="plan-card p-4 h-100">
            <h3 class="h5 fw-bold">Terapia de Pareja</h3>
            <div class="display-6 fw-bold my-3">$45</div>
            <ul class="list-unstyled mb-4">
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>60 minutos</li>
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>Sesion conjunta</li>
              <li class="plan-feature mb-2"><i class="bi bi-check2 text-success me-2"></i>Informe terapeutico</li>
            </ul>
            <a href="#contacto" class="btn btn-outline-primary w-100 js-scroll">Reservar</a>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section id="testimonios" class="py-5 py-lg-6 bg-white">
    <div class="container">
      <div class="text-center mb-4 reveal">
        <h2 class="display-6 fw-bold section-title">Testimonios</h2>
      </div>
      <div id="testimonialCarousel" class="carousel slide reveal" data-bs-ride="carousel">
        <div class="carousel-inner">
          <div class="carousel-item active">
            <article class="text-center p-4 p-lg-5 mx-auto" style="max-width: 800px;">
              <p class="lead">"El acompanamiento fue claro, humano y muy profesional. Mi calidad de vida mejoro notablemente."</p>
              <h3 class="h6 fw-bold mb-0">Paciente - Terapia Individual</h3>
            </article>
          </div>
          <div class="carousel-item">
            <article class="text-center p-4 p-lg-5 mx-auto" style="max-width: 800px;">
              <p class="lead">"La terapia de pareja nos ayudo a reconstruir acuerdos y mejorar nuestra comunicacion."</p>
              <h3 class="h6 fw-bold mb-0">Paciente - Terapia de Pareja</h3>
            </article>
          </div>
          <div class="carousel-item">
            <article class="text-center p-4 p-lg-5 mx-auto" style="max-width: 800px;">
              <p class="lead">"Excelente trato con mi hijo, avances visibles desde las primeras sesiones."</p>
              <h3 class="h6 fw-bold mb-0">Madre - Psicologia Infantil</h3>
            </article>
          </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon bg-dark rounded-circle"></span>
          <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon bg-dark rounded-circle"></span>
          <span class="visually-hidden">Siguiente</span>
        </button>
      </div>
    </div>
  </section>

  <section class="cta-band text-white py-5">
    <div class="container d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 reveal">
      <div>
        <h2 class="h2 fw-bold mb-2">Listo para iniciar tu proceso terapeutico?</h2>
        <p class="mb-0 text-white-50">Agenda una valoracion inicial y recibe orientacion profesional personalizada.</p>
      </div>
      <a href="#contacto" class="btn btn-light btn-lg js-scroll">Agendar ahora</a>
    </div>
  </section>

  <section id="contacto" class="py-5 py-lg-6">
    <div class="container">
      <div class="row g-4 align-items-start">
        <div class="col-lg-4 reveal">
          <div class="contact-card p-4 h-100">
            <h2 class="h3 fw-bold mb-3">Agenda tu consulta</h2>
            <p class="text-secondary">Completa el formulario y te contactaremos para confirmar fecha y modalidad.</p>
            <hr>
            <p class="mb-2"><i class="bi bi-geo-alt text-primary me-2"></i>Av. Principal 123, Centro</p>
            <p class="mb-2"><i class="bi bi-telephone text-primary me-2"></i>+593 98 703 6924</p>
            <p class="mb-0"><i class="bi bi-envelope text-primary me-2"></i>contacto@psicobienestar.com</p>
          </div>
        </div>

        <div class="col-lg-8 reveal">
          <div class="contact-panel p-4 p-lg-5">
            <?php if ($ok): ?>
            <div class="alert alert-success border-0 mb-4" role="alert">
              Solicitud enviada correctamente. Te contactaremos pronto.
            </div>
            <?php elseif ($error !== ''): ?>
            <div class="alert alert-danger border-0 mb-4" role="alert">
              No se pudo enviar la solicitud. Revisa los datos e intenta de nuevo.
            </div>
            <?php endif; ?>

            <form action="guardar_cita.php" method="POST" class="needs-validation" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="nombres" class="form-label">Nombres</label>
                  <input id="nombres" type="text" name="nombres" class="form-control form-control-lg" required>
                  <div class="invalid-feedback">Ingresa los nombres.</div>
                </div>
                <div class="col-md-6">
                  <label for="apellidos" class="form-label">Apellidos</label>
                  <input id="apellidos" type="text" name="apellidos" class="form-control form-control-lg" required>
                  <div class="invalid-feedback">Ingresa los apellidos.</div>
                </div>
                <div class="col-md-6">
                  <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label>
                  <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="form-control form-control-lg">
                </div>
                <div class="col-md-6">
                  <label for="genero" class="form-label">Genero</label>
                  <input id="genero" type="text" name="genero" class="form-control form-control-lg" placeholder="Femenino, Masculino, Otro">
                </div>
                <div class="col-md-6">
                  <label for="telefono" class="form-label">Telefono</label>
                  <input id="telefono" type="tel" name="telefono" class="form-control form-control-lg" placeholder="+593 98 703 6924">
                </div>
                <div class="col-md-6">
                  <label for="email" class="form-label">Correo electronico</label>
                  <input id="email" type="email" name="email" class="form-control form-control-lg" required>
                  <div class="invalid-feedback">Ingresa un correo valido.</div>
                </div>
                <div class="col-12">
                  <label for="direccion" class="form-label">Direccion</label>
                  <input id="direccion" type="text" name="direccion" class="form-control form-control-lg">
                </div>
                <div class="col-12">
                  <label for="contacto_emergencia" class="form-label">Contacto de emergencia</label>
                  <input id="contacto_emergencia" type="text" name="contacto_emergencia" class="form-control form-control-lg" placeholder="Nombre y telefono">
                </div>
                <div class="col-md-6">
                  <label for="servicio" class="form-label">Servicio</label>
                  <select id="servicio" name="servicio" class="form-select form-select-lg" required>
                    <option value="" selected disabled>Seleccionar servicio</option>
                    <?php foreach ($services as $service): ?>
                    <option value="<?= esc_html((string) $service['nombre']) ?>"><?= esc_html((string) $service['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback">Selecciona un servicio.</div>
                </div>
                <div class="col-md-6">
                  <label for="notas_iniciales" class="form-label">Notas iniciales</label>
                  <textarea id="notas_iniciales" name="notas_iniciales" rows="3" class="form-control" placeholder="Motivo de consulta, antecedentes relevantes"></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary btn-lg w-100">Enviar solicitud</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="footer-shell text-white py-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-5">
          <h3 class="h5 fw-bold">PsicoBienestar</h3>
          <p class="text-white-50 mb-0">Centro psicologico integral enfocado en salud mental, desarrollo personal y bienestar emocional.</p>
        </div>
        <div class="col-6 col-lg-3">
          <h4 class="h6 fw-semibold">Enlaces</h4>
          <ul class="list-unstyled small">
            <li><a class="link-light link-underline-opacity-0 js-scroll" href="#servicios">Servicios</a></li>
            <li><a class="link-light link-underline-opacity-0 js-scroll" href="#planes">Planes</a></li>
            <li><a class="link-light link-underline-opacity-0 js-scroll" href="#contacto">Contacto</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-4">
          <h4 class="h6 fw-semibold">Administracion</h4>
          <ul class="list-unstyled small mb-0">
            <li><a class="link-light link-underline-opacity-0" href="admin/login.php">Panel administrativo</a></li>
            <li><a class="link-light link-underline-opacity-0" href="README_CLINICO.md">Guia del sistema</a></li>
          </ul>
        </div>
      </div>
      <hr class="border-secondary my-4">
      <p class="small text-white-50 mb-0">&copy; <span data-year></span> PsicoBienestar. Todos los derechos reservados.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/site.js"></script>
</body>
</html>
