CREATE DATABASE IF NOT EXISTS sistema_web
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sistema_web;

CREATE TABLE IF NOT EXISTS citas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  nombres VARCHAR(120) DEFAULT '',
  apellidos VARCHAR(120) DEFAULT '',
  fecha_nacimiento DATE DEFAULT NULL,
  genero VARCHAR(30) DEFAULT '',
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) DEFAULT '',
  direccion VARCHAR(255) DEFAULT '',
  contacto_emergencia VARCHAR(255) DEFAULT '',
  notas_iniciales TEXT,
  servicio VARCHAR(100) DEFAULT '',
  mensaje TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE citas
  ADD COLUMN IF NOT EXISTS nombres VARCHAR(120) DEFAULT '' AFTER nombre,
  ADD COLUMN IF NOT EXISTS apellidos VARCHAR(120) DEFAULT '' AFTER nombres,
  ADD COLUMN IF NOT EXISTS fecha_nacimiento DATE DEFAULT NULL AFTER apellidos,
  ADD COLUMN IF NOT EXISTS genero VARCHAR(30) DEFAULT '' AFTER fecha_nacimiento,
  ADD COLUMN IF NOT EXISTS direccion VARCHAR(255) DEFAULT '' AFTER telefono,
  ADD COLUMN IF NOT EXISTS contacto_emergencia VARCHAR(255) DEFAULT '' AFTER direccion,
  ADD COLUMN IF NOT EXISTS notas_iniciales TEXT AFTER contacto_emergencia;

CREATE TABLE IF NOT EXISTS servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion TEXT NOT NULL,
  icono VARCHAR(60) NOT NULL DEFAULT 'bi-stars',
  imagen VARCHAR(255) DEFAULT '',
  orden INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE servicios
  ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT '' AFTER icono;

CREATE TABLE IF NOT EXISTS equipo_web (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(140) NOT NULL,
  cargo VARCHAR(140) NOT NULL DEFAULT '',
  descripcion VARCHAR(255) NOT NULL DEFAULT '',
  iniciales VARCHAR(8) NOT NULL DEFAULT '',
  imagen VARCHAR(255) DEFAULT '',
  orden INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE equipo_web
  ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT '' AFTER iniciales;

CREATE TABLE IF NOT EXISTS hero_slides (
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
);

ALTER TABLE hero_slides
  ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) DEFAULT '' AFTER descripcion;

CREATE TABLE IF NOT EXISTS usuarios_admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol VARCHAR(20) NOT NULL DEFAULT 'admin',
  debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login_en DATETIME DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_passkeys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_admin_id INT NOT NULL,
  credential_id VARCHAR(255) NOT NULL UNIQUE,
  public_key_pem TEXT NOT NULL,
  sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  transports VARCHAR(255) DEFAULT '',
  label VARCHAR(140) DEFAULT '',
  ultimo_uso_en DATETIME DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_passkeys_usuario (usuario_admin_id),
  CONSTRAINT fk_passkeys_usuario
    FOREIGN KEY (usuario_admin_id) REFERENCES usuarios_admin(id)
    ON DELETE CASCADE
);

ALTER TABLE usuarios_admin
  ADD COLUMN IF NOT EXISTS rol VARCHAR(20) NOT NULL DEFAULT 'admin',
  ADD COLUMN IF NOT EXISTS debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS ultimo_login_en DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS pacientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(120) NOT NULL,
  apellidos VARCHAR(120) NOT NULL,
  fecha_nacimiento DATE DEFAULT NULL,
  genero VARCHAR(30) DEFAULT '',
  telefono VARCHAR(30) DEFAULT '',
  email VARCHAR(120) DEFAULT '',
  direccion VARCHAR(255) DEFAULT '',
  contacto_emergencia VARCHAR(255) DEFAULT '',
  notas_iniciales TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS citas_clinicas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT NOT NULL,
  profesional VARCHAR(120) NOT NULL,
  servicio VARCHAR(120) NOT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  duracion_minutos INT NOT NULL DEFAULT 45,
  modalidad VARCHAR(30) NOT NULL DEFAULT 'presencial',
  estado VARCHAR(30) NOT NULL DEFAULT 'programada',
  observaciones TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_citas_clinicas_fecha_hora (fecha, hora),
  KEY idx_citas_clinicas_profesional_fecha (profesional, fecha),
  CONSTRAINT fk_citas_clinicas_paciente
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS historial_psicologico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT NOT NULL,
  cita_clinica_id INT DEFAULT NULL,
  tipo_nota VARCHAR(80) NOT NULL DEFAULT 'evolucion',
  contenido TEXT NOT NULL,
  confidencial TINYINT(1) NOT NULL DEFAULT 1,
  creado_por VARCHAR(120) NOT NULL DEFAULT 'sistema',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_historial_paciente
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_historial_cita
    FOREIGN KEY (cita_clinica_id) REFERENCES citas_clinicas(id)
    ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cita_clinica_id INT DEFAULT NULL,
  paciente_id INT NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  moneda VARCHAR(10) NOT NULL DEFAULT 'USD',
  metodo_pago VARCHAR(50) NOT NULL DEFAULT 'efectivo',
  estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  referencia_externa VARCHAR(120) DEFAULT '',
  fecha_pago DATETIME DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pagos_cita
    FOREIGN KEY (cita_clinica_id) REFERENCES citas_clinicas(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_pagos_paciente
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cita_clinica_id INT NOT NULL,
  paciente_id INT NOT NULL,
  tipo VARCHAR(40) NOT NULL DEFAULT 'recordatorio',
  telefono_destino VARCHAR(30) NOT NULL,
  mensaje TEXT NOT NULL,
  programado_para DATETIME NOT NULL,
  estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  enviado_en DATETIME DEFAULT NULL,
  message_id_wamid VARCHAR(255) DEFAULT NULL,
  respuesta_api TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recordatorios_estado_fecha (estado, programado_para),
  CONSTRAINT fk_recordatorios_cita
    FOREIGN KEY (cita_clinica_id) REFERENCES citas_clinicas(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_recordatorios_paciente
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
    ON DELETE CASCADE
);

ALTER TABLE recordatorios_whatsapp
  ADD COLUMN IF NOT EXISTS tipo VARCHAR(40) NOT NULL DEFAULT 'recordatorio' AFTER paciente_id,
  ADD COLUMN IF NOT EXISTS message_id_wamid VARCHAR(255) DEFAULT NULL AFTER enviado_en;

INSERT INTO usuarios_admin (nombre, email, password_hash, rol, debe_cambiar_password, activo)
SELECT 'Administrador', 'admin@psicobienestar.com', '$2y$10$Upb1OIRDhGUpeJA8vW6dnO7dQrv6c8WIrLqTMNeYQGXEuIUT4TVoa', 'admin', 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM usuarios_admin WHERE email = 'admin@psicobienestar.com'
);

INSERT INTO usuarios_admin (nombre, email, password_hash, rol, debe_cambiar_password, activo)
SELECT 'Fernanda Gutierres', 'fernanda.gutierres@psicobienestar.com', '$2y$10$Upb1OIRDhGUpeJA8vW6dnO7dQrv6c8WIrLqTMNeYQGXEuIUT4TVoa', 'terapeuta', 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM usuarios_admin WHERE email = 'fernanda.gutierres@psicobienestar.com'
);

INSERT INTO usuarios_admin (nombre, email, password_hash, rol, debe_cambiar_password, activo)
SELECT 'Luis Andrade', 'luis.andrade@psicobienestar.com', '$2y$10$Upb1OIRDhGUpeJA8vW6dnO7dQrv6c8WIrLqTMNeYQGXEuIUT4TVoa', 'terapeuta', 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM usuarios_admin WHERE email = 'luis.andrade@psicobienestar.com'
);

UPDATE usuarios_admin
SET rol = COALESCE(NULLIF(rol, ''), 'admin'),
    activo = 1
WHERE email = 'admin@psicobienestar.com';

INSERT INTO servicios (nombre, descripcion, icono, imagen, orden, activo)
SELECT 'Terapia Individual', 'Ansiedad, depresion, estres laboral y desarrollo personal.', 'bi-person-heart', 'https://images.pexels.com/photos/7579309/pexels-photo-7579309.jpeg?auto=compress&cs=tinysrgb&w=1200', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM servicios WHERE nombre = 'Terapia Individual');

INSERT INTO servicios (nombre, descripcion, icono, imagen, orden, activo)
SELECT 'Terapia de Pareja', 'Comunicacion asertiva, acuerdos y reparacion del vinculo.', 'bi-people', 'https://images.pexels.com/photos/3958383/pexels-photo-3958383.jpeg?auto=compress&cs=tinysrgb&w=1200', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM servicios WHERE nombre = 'Terapia de Pareja');

INSERT INTO servicios (nombre, descripcion, icono, imagen, orden, activo)
SELECT 'Psicologia Infantil', 'Evaluacion y acompanamiento emocional para ninos y familias.', 'bi-balloon-heart', 'https://images.pexels.com/photos/8654102/pexels-photo-8654102.jpeg?auto=compress&cs=tinysrgb&w=1200', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM servicios WHERE nombre = 'Psicologia Infantil');

INSERT INTO servicios (nombre, descripcion, icono, imagen, orden, activo)
SELECT 'Orientacion Familiar', 'Fortalecimiento sistemico y resolucion de conflictos familiares.', 'bi-diagram-3', 'https://images.pexels.com/photos/5336930/pexels-photo-5336930.jpeg?auto=compress&cs=tinysrgb&w=1200', 4, 1
WHERE NOT EXISTS (SELECT 1 FROM servicios WHERE nombre = 'Orientacion Familiar');

UPDATE servicios
SET imagen = 'https://images.pexels.com/photos/7579309/pexels-photo-7579309.jpeg?auto=compress&cs=tinysrgb&w=1200'
WHERE nombre = 'Terapia Individual';

UPDATE servicios
SET imagen = 'https://images.pexels.com/photos/3958383/pexels-photo-3958383.jpeg?auto=compress&cs=tinysrgb&w=1200'
WHERE nombre = 'Terapia de Pareja';

UPDATE servicios
SET imagen = 'https://images.pexels.com/photos/8654102/pexels-photo-8654102.jpeg?auto=compress&cs=tinysrgb&w=1200'
WHERE nombre = 'Psicologia Infantil';

UPDATE servicios
SET imagen = 'https://images.pexels.com/photos/5336930/pexels-photo-5336930.jpeg?auto=compress&cs=tinysrgb&w=1200'
WHERE nombre = 'Orientacion Familiar';

INSERT INTO equipo_web (nombre, cargo, descripcion, iniciales, imagen, orden, activo)
SELECT 'Dra. Maria Lopez', 'Psicologia Clinica', 'Trauma, ansiedad y bienestar emocional', 'ML', '', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM equipo_web WHERE nombre = 'Dra. Maria Lopez');

INSERT INTO equipo_web (nombre, cargo, descripcion, iniciales, imagen, orden, activo)
SELECT 'Mgtr. Carlos Perez', 'Terapia Familiar', 'Pareja, limites y comunicacion efectiva', 'CP', '', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM equipo_web WHERE nombre = 'Mgtr. Carlos Perez');

INSERT INTO equipo_web (nombre, cargo, descripcion, iniciales, imagen, orden, activo)
SELECT 'Lic. Ana Torres', 'Psicologia Infantil', 'Intervencion emocional en primera infancia', 'AT', '', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM equipo_web WHERE nombre = 'Lic. Ana Torres');

INSERT INTO hero_slides (
  badge, titulo, descripcion, imagen, cta_principal_texto, cta_principal_href,
  cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1, card_item_2, card_item_3,
  card_icono, card_footer_titulo, card_footer_descripcion, orden, activo
)
SELECT
  'Salud mental con enfoque clinico',
  'Centro Psicologico Integral para ninos, jovenes y adultos',
  'Acompanamiento terapeutico profesional, evaluacion clinica y seguimiento continuo para tu bienestar emocional.',
  'https://images.pexels.com/photos/4101143/pexels-photo-4101143.jpeg?auto=compress&cs=tinysrgb&w=1200',
  'Reservar valoracion',
  '#contacto',
  'Conocer servicios',
  '#servicios',
  'Atencion profesional y confidencial',
  'Especialistas certificados',
  'Modalidad presencial y online',
  'Planes para acompanamiento continuo',
  'bi-shield-check',
  'Confidencialidad garantizada',
  'Protocolos eticos y clinicos vigentes',
  1,
  1
WHERE NOT EXISTS (SELECT 1 FROM hero_slides WHERE orden = 1);

INSERT INTO hero_slides (
  badge, titulo, descripcion, imagen, cta_principal_texto, cta_principal_href,
  cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1, card_item_2, card_item_3,
  card_icono, card_footer_titulo, card_footer_descripcion, orden, activo
)
SELECT
  'Agenda flexible y seguimiento',
  'Terapia presencial y online adaptada a tu ritmo',
  'Sesiones personalizadas, objetivos medibles y acompanamiento continuo para fortalecer tu bienestar integral.',
  'https://images.pexels.com/photos/7176319/pexels-photo-7176319.jpeg?auto=compress&cs=tinysrgb&w=1200',
  'Agendar primera cita',
  '#contacto',
  'Ver planes',
  '#planes',
  'Acompanamiento estructurado',
  'Objetivos terapeuticos por etapa',
  'Evaluaciones periodicas de avance',
  'Plan de accion para casa',
  'bi-clipboard2-pulse',
  'Metodo basado en evidencia',
  'Intervenciones con respaldo clinico',
  2,
  1
WHERE NOT EXISTS (SELECT 1 FROM hero_slides WHERE orden = 2);

INSERT INTO hero_slides (
  badge, titulo, descripcion, imagen, cta_principal_texto, cta_principal_href,
  cta_secundario_texto, cta_secundario_href, card_titulo, card_item_1, card_item_2, card_item_3,
  card_icono, card_footer_titulo, card_footer_descripcion, orden, activo
)
SELECT
  'Atencion familiar e infantil',
  'Espacios seguros para ninos, parejas y familias',
  'Fortalece la comunicacion, regula emociones y construye relaciones saludables con apoyo profesional especializado.',
  'https://images.pexels.com/photos/5699479/pexels-photo-5699479.jpeg?auto=compress&cs=tinysrgb&w=1200',
  'Conocer equipo',
  '#equipo',
  'Solicitar orientacion',
  '#contacto',
  'Intervencion integral',
  'Terapia individual y de pareja',
  'Psicologia infantil y familiar',
  'Red de apoyo y coordinacion',
  'bi-people',
  'Enfoque humano y etico',
  'Atencion cercana y profesional',
  3,
  1
WHERE NOT EXISTS (SELECT 1 FROM hero_slides WHERE orden = 3);
