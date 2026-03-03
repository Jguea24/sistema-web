<?php

declare(strict_types=1);

$appTimezone = getenv('APP_TIMEZONE') ?: 'America/Guayaquil';
date_default_timezone_set($appTimezone);

// Configuracion unica de base de datos para servidor.
$host = 'localhost';
$db = 'sistema_web';
$user = 'root';
$pass = ''; // Coloca aqui la clave real de MySQL en cPanel.
$charset = 'utf8mb4';
$port = '3306';

$hostSegment = $port !== '' ? "host={$host};port={$port}" : "host={$host}";
$dsn = "mysql:{$hostSegment};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Verifica credenciales y permisos.');
}
