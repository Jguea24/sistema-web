<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/whatsapp_service.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este worker solo puede ejecutarse por CLI.');
}

$limit = 50;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $limit = max(1, (int) $argv[1]);
}

$result = process_pending_whatsapp_queue($pdo, $limit);
echo '[WhatsApp Worker] provider=' . $result['provider']
    . ' processed=' . $result['processed']
    . ' sent=' . $result['sent']
    . ' failed=' . $result['failed']
    . PHP_EOL;
