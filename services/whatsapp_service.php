<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function load_whatsapp_config(): array
{
    $defaults = [
        'provider' => 'simulate',
        'default_country_code' => '',
        'twilio_account_sid' => '',
        'twilio_auth_token' => '',
        'twilio_from' => '',
        'meta_token' => '',
        'meta_phone_number_id' => '',
        'meta_api_version' => 'v20.0',
        'd360_api_key' => '',
        'd360_base_url' => 'https://waba-v2.360dialog.io',
        'request_timeout' => 20,
    ];

    $fileConfig = [];
    $configFile = __DIR__ . '/../config/whatsapp.php';
    if (is_file($configFile)) {
        $loaded = require $configFile;
        if (is_array($loaded)) {
            $fileConfig = $loaded;
        }
    }

    $envMap = [
        'provider' => getenv('WHATSAPP_PROVIDER') ?: null,
        'default_country_code' => getenv('WHATSAPP_DEFAULT_COUNTRY_CODE') ?: null,
        'twilio_account_sid' => getenv('TWILIO_ACCOUNT_SID') ?: null,
        'twilio_auth_token' => getenv('TWILIO_AUTH_TOKEN') ?: null,
        'twilio_from' => getenv('TWILIO_WHATSAPP_FROM') ?: null,
        'meta_token' => getenv('META_WHATSAPP_TOKEN') ?: null,
        'meta_phone_number_id' => getenv('META_PHONE_NUMBER_ID') ?: null,
        'meta_api_version' => getenv('META_API_VERSION') ?: null,
        'd360_api_key' => getenv('D360_API_KEY') ?: null,
        'd360_base_url' => getenv('D360_BASE_URL') ?: null,
        'request_timeout' => getenv('WHATSAPP_REQUEST_TIMEOUT') ?: null,
    ];

    $envConfig = [];
    foreach ($envMap as $key => $value) {
        if ($value !== null && $value !== '') {
            $envConfig[$key] = $value;
        }
    }

    $config = array_merge($defaults, $fileConfig, $envConfig);
    $config['provider'] = strtolower((string) $config['provider']);
    $config['default_country_code'] = preg_replace('/\D+/', '', (string) ($config['default_country_code'] ?? '')) ?? '';
    $config['request_timeout'] = (int) $config['request_timeout'];

    return $config;
}

function whatsapp_normalize_phone(string $phone, array $config = []): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    $countryCode = preg_replace('/\D+/', '', (string) ($config['default_country_code'] ?? '')) ?? '';
    if ($digits !== '' && $countryCode !== '') {
        if (str_starts_with($digits, '0')) {
            $digits = $countryCode . ltrim($digits, '0');
        } elseif (!str_starts_with($digits, $countryCode) && strlen($digits) <= 10) {
            $digits = $countryCode . $digits;
        }
    }

    return $digits;
}

function extract_message_provider_id(string $rawResponse): ?string
{
    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return null;
    }

    if (isset($decoded['messages'][0]['id']) && is_string($decoded['messages'][0]['id'])) {
        return $decoded['messages'][0]['id'];
    }

    if (isset($decoded['sid']) && is_string($decoded['sid'])) {
        return $decoded['sid'];
    }

    if (isset($decoded['message_id']) && is_string($decoded['message_id'])) {
        return $decoded['message_id'];
    }

    return null;
}

/**
 * @return array{success:bool,http_status:int,response:string,error:string}
 */
function whatsapp_curl_request(
    string $url,
    array $headers,
    string $body,
    int $timeout,
    ?string $basicAuth = null
): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
    ]);

    if ($basicAuth !== null) {
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'http_status' => $httpCode,
            'response' => '',
            'error' => $curlError !== '' ? $curlError : 'Error cURL desconocido',
        ];
    }

    $success = $httpCode >= 200 && $httpCode < 300;
    return [
        'success' => $success,
        'http_status' => $httpCode,
        'response' => (string) $response,
        'error' => $success ? '' : 'HTTP ' . $httpCode,
    ];
}

/**
 * @param array<string, mixed> $config
 * @return array{success:bool,response:string,error:string}
 */
function send_whatsapp_message(string $toPhone, string $message, array $config): array
{
    $provider = (string) ($config['provider'] ?? 'simulate');
    $timeout = (int) ($config['request_timeout'] ?? 20);
    $normalized = whatsapp_normalize_phone($toPhone, $config);

    if ($normalized === '') {
        return ['success' => false, 'response' => '', 'error' => 'Telefono destino invalido'];
    }

    if ($provider === 'simulate') {
        return ['success' => true, 'response' => 'simulado_ok', 'error' => ''];
    }

    if ($provider === 'twilio') {
        $sid = (string) ($config['twilio_account_sid'] ?? '');
        $token = (string) ($config['twilio_auth_token'] ?? '');
        $from = (string) ($config['twilio_from'] ?? '');
        if ($sid === '' || $token === '' || $from === '') {
            return ['success' => false, 'response' => '', 'error' => 'Faltan credenciales Twilio'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $to = str_starts_with($normalized, '+') ? $normalized : '+' . $normalized;
        $fromValue = str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:' . $from;
        $body = http_build_query([
            'To' => 'whatsapp:' . $to,
            'From' => $fromValue,
            'Body' => $message,
        ]);

        $res = whatsapp_curl_request(
            $url,
            ['Content-Type: application/x-www-form-urlencoded'],
            $body,
            $timeout,
            $sid . ':' . $token
        );

        return [
            'success' => $res['success'],
            'response' => $res['response'],
            'error' => $res['success'] ? '' : $res['error'],
        ];
    }

    if ($provider === 'meta') {
        $token = (string) ($config['meta_token'] ?? '');
        $phoneId = (string) ($config['meta_phone_number_id'] ?? '');
        $version = (string) ($config['meta_api_version'] ?? 'v20.0');
        if ($token === '' || $phoneId === '') {
            return ['success' => false, 'response' => '', 'error' => 'Faltan credenciales Meta'];
        }

        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $normalized,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $res = whatsapp_curl_request(
            $url,
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            $payload !== false ? $payload : '{}',
            $timeout
        );

        return [
            'success' => $res['success'],
            'response' => $res['response'],
            'error' => $res['success'] ? '' : $res['error'],
        ];
    }

    if ($provider === '360dialog') {
        $apiKey = (string) ($config['d360_api_key'] ?? '');
        $base = rtrim((string) ($config['d360_base_url'] ?? 'https://waba-v2.360dialog.io'), '/');
        if ($apiKey === '') {
            return ['success' => false, 'response' => '', 'error' => 'Falta API key de 360dialog'];
        }

        $url = $base . '/messages';
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalized,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $res = whatsapp_curl_request(
            $url,
            [
                'D360-API-KEY: ' . $apiKey,
                'Content-Type: application/json',
            ],
            $payload !== false ? $payload : '{}',
            $timeout
        );

        return [
            'success' => $res['success'],
            'response' => $res['response'],
            'error' => $res['success'] ? '' : $res['error'],
        ];
    }

    return ['success' => false, 'response' => '', 'error' => 'Proveedor no soportado: ' . $provider];
}

/**
 * @return array{processed:int,sent:int,failed:int,provider:string}
 */
function process_pending_whatsapp_queue(PDO $pdo, int $limit = 50, ?int $specificId = null): array
{
    $config = load_whatsapp_config();
    $provider = (string) ($config['provider'] ?? 'simulate');
    $lockName = 'sistema_web_whatsapp_queue_lock';

    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lockStmt->execute([$lockName]);
    $lockAcquired = (int) $lockStmt->fetchColumn() === 1;
    if (!$lockAcquired) {
        return [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'provider' => $provider,
        ];
    }

    try {
        if ($specificId !== null && $specificId > 0) {
            $stmt = $pdo->prepare(
                "SELECT id, telefono_destino, mensaje
                 FROM recordatorios_whatsapp
                 WHERE id = ? AND estado IN ('pendiente', 'error')
                 LIMIT 1"
            );
            $stmt->execute([$specificId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, telefono_destino, mensaje
                 FROM recordatorios_whatsapp
                 WHERE estado = 'pendiente'
                   AND programado_para <= NOW()
                 ORDER BY programado_para ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $rows = $stmt->fetchAll();
        $processed = 0;
        $sent = 0;
        $failed = 0;

        $successStmt = $pdo->prepare(
            "UPDATE recordatorios_whatsapp
             SET estado = 'enviado', enviado_en = NOW(), message_id_wamid = ?, respuesta_api = ?
             WHERE id = ?"
        );
        $errorStmt = $pdo->prepare(
            "UPDATE recordatorios_whatsapp
             SET estado = 'error', message_id_wamid = NULL, respuesta_api = ?
             WHERE id = ?"
        );

        foreach ($rows as $row) {
            $processed++;
            $send = send_whatsapp_message((string) $row['telefono_destino'], (string) $row['mensaje'], $config);
            if ($send['success']) {
                $sent++;
                $providerId = extract_message_provider_id((string) $send['response']);
                $successStmt->execute([
                    $providerId !== null ? substr($providerId, 0, 255) : null,
                    substr((string) $send['response'], 0, 65000),
                    (int) $row['id'],
                ]);
            } else {
                $failed++;
                if ($send['error'] !== '' && $send['response'] !== '') {
                    $errorMessage = $send['error'] . ' | ' . $send['response'];
                } elseif ($send['error'] !== '') {
                    $errorMessage = $send['error'];
                } else {
                    $errorMessage = $send['response'];
                }
                $errorStmt->execute([substr((string) $errorMessage, 0, 65000), (int) $row['id']]);
            }
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'provider' => $provider,
        ];
    } finally {
        $unlockStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $unlockStmt->execute([$lockName]);
    }
}
