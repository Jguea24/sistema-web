# Sistema Clinico - Guia Rapida

## Estructura de carpetas

- `/admin`: panel administrativo.
- `/assets`: CSS, JS y archivos subidos (hero, servicios, equipo).
- `/config`: configuraciones (`whatsapp.php`).
- `/services`: logica de negocio (recordatorios/WhatsApp).
- `/workers`: procesos CLI (cola de WhatsApp).

## 1) Preparar base de datos

Opcion A (recomendada): abrir en navegador:

- `http://localhost/sistema-web/setup_clinico.php`

Opcion B (MySQL):

- ejecutar `database.sql` en la base `sistema_web`.

### Produccion (cPanel)

- crear base y usuario MySQL en cPanel.
- asignar todos los permisos del usuario a la base.
- editar `db.php` con los datos reales (`host`, `name`, `user`, `pass`).

## 2) Acceso administrativo

- URL: `http://localhost/sistema-web/admin/login.php`
- Usuario inicial: `admin@psicobienestar.com`
- Clave inicial: `admin123`
- La primera vez el sistema obliga a cambiar contrasena.
- Telefono oficial del consultorio: `+593 99 447 6914`

## 3) Modulos habilitados

- Dashboard: metricas y proximas citas.
- Pacientes: registro y listado de pacientes.
- Agenda: crear citas, validar cruces y generar recordatorios.
- Historial: notas psicologicas privadas por paciente.
- Pagos: registro de cobros y estado de pago.
- WhatsApp: cola de recordatorios (simulada/manual).
- Solicitudes Web: leads del formulario publico con opcion "Crear Paciente".

## 4) Integracion WhatsApp real (siguiente paso)

1) Copiar configuracion:
- copiar `config/whatsapp.php.example` a `config/whatsapp.php`
- completar credenciales del proveedor.
- verificar `clinic_phone` con el numero oficial del consultorio.

2) Proveedores soportados:
- `simulate` (modo prueba)
- `twilio`
- `meta` (Cloud API)
- `360dialog`

2.1) Configuracion recomendada (Meta Cloud API):
- en `config/whatsapp.php` usar `provider => 'meta'`
- completar `meta_token`
- completar `meta_phone_number_id`
- opcional: ajustar `meta_api_version` (ej. `v20.0`)

3) Ejecutar worker manual:
- `C:\xampp\php\php.exe C:\xampp\htdocs\sistema-web\workers\process_whatsapp_queue.php 50`

4) Cron recomendado (Task Scheduler en Windows):
- Ejecutar el comando anterior cada 1-5 minutos.
- El worker procesa recordatorios `pendiente` con `programado_para <= NOW()`.

Notas del flujo:
- Al crear una cita nueva, el sistema genera confirmacion inmediata y recordatorios automaticos.
- El identificador de mensaje de proveedor (`WAMID`) se guarda en `recordatorios_whatsapp.message_id_wamid`.

## 5) Roles del sistema

- `admin`: acceso total + gestion de usuarios.
- `terapeuta`: dashboard, pacientes, agenda, historial.
- `recepcion`: dashboard, pacientes, agenda, pagos, recordatorios, solicitudes.

Gestionar usuarios y roles:
- `http://localhost/sistema-web/admin/usuarios.php`
