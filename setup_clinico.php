<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$isCli = PHP_SAPI === 'cli';
$setupKey = (string) (getenv('SETUP_KEY') ?: '');
$providedKey = (string) ($_GET['key'] ?? $_POST['key'] ?? '');

if (!$isCli) {
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $isLocalRequest = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    $authorizedByKey = $setupKey !== '' && hash_equals($setupKey, $providedKey);

    if (!$isLocalRequest && !$authorizedByKey) {
        http_response_code(403);
        exit('Acceso denegado al setup.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Setup Clinico</title>
          <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-100 text-slate-800">
          <main class="max-w-3xl mx-auto p-6">
            <h1 class="text-2xl font-bold mb-3">Setup Clinico</h1>
            <p class="mb-4">Este proceso inicializa/actualiza estructura y datos base del sistema.</p>
            <form method="POST">
              <?php if ($authorizedByKey): ?>
              <input type="hidden" name="key" value="<?= htmlspecialchars($providedKey, ENT_QUOTES, 'UTF-8') ?>">
              <?php endif; ?>
              <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded-lg">Ejecutar setup ahora</button>
            </form>
          </main>
        </body>
        </html>
        <?php
        exit;
    }
}

$sqlFile = __DIR__ . '/database.sql';
if (!is_file($sqlFile)) {
    exit('No existe database.sql');
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    exit('No se pudo leer database.sql');
}

// Ejecuta sentencias separadas por ";" en el archivo de esquema.
$statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
$executed = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    $pdo->exec($statement);
    $executed++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup Clinico</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
  <main class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-3">Setup Clinico completado</h1>
    <p class="mb-2">Sentencias ejecutadas: <strong><?= $executed ?></strong></p>
    <p class="mb-2">Login admin: <strong>admin@psicobienestar.com</strong></p>
    <p class="mb-6">Clave inicial: <strong>admin123</strong></p>
    <a href="admin/login.php" class="px-4 py-2 bg-blue-700 text-white rounded-lg">Ir al panel admin</a>
  </main>
</body>
</html>


