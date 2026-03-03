<?php

declare(strict_types=1);

$appTimezone = getenv('APP_TIMEZONE') ?: 'America/Guayaquil';
date_default_timezone_set($appTimezone);

$host = 'localhost';
$db = 'sistema_web';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$serverDsn = "mysql:host={$host};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    // Si la base no existe, la crea automaticamente para facilitar el despliegue inicial.
    if ((int) $e->getCode() === 1049) {
        try {
            $pdoServer = new PDO($serverDsn, $user, $pass, $options);
            $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $inner) {
            http_response_code(500);
            exit('No se pudo crear o conectar con la base de datos.');
        }
    } else {
        http_response_code(500);
        exit('No se pudo conectar con la base de datos.');
    }
}
