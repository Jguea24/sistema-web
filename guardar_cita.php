<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$nombreLegacy = trim($_POST['nombre'] ?? '');
$nombre = trim($nombres . ' ' . $apellidos);
if ($nombre === '') {
    $nombre = $nombreLegacy;
}

$fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
$genero = trim($_POST['genero'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$contactoEmergencia = trim($_POST['contacto_emergencia'] ?? '');
$notasIniciales = trim($_POST['notas_iniciales'] ?? '');
$servicio = trim($_POST['servicio'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if ($nombre === '' || $email === '') {
    header('Location: index.php?error=1');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?error=2');
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO citas (nombre, nombres, apellidos, fecha_nacimiento, genero, email, telefono, direccion, contacto_emergencia, notas_iniciales, servicio, mensaje)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$fechaNacimientoValue = null;
if ($fechaNacimiento !== '') {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
    if ($fechaObj !== false && $fechaObj->format('Y-m-d') === $fechaNacimiento) {
        $fechaNacimientoValue = $fechaNacimiento;
    }
}

$stmt->execute([
    $nombre,
    $nombres,
    $apellidos,
    $fechaNacimientoValue,
    $genero,
    $email,
    $telefono,
    $direccion,
    $contactoEmergencia,
    $notasIniciales,
    $servicio,
    $mensaje,
]);

header('Location: index.php?ok=1');
exit;
