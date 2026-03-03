<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/webauthn_utils.php';

header('Content-Type: application/json; charset=UTF-8');

function webauthn_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function webauthn_parse_transports(string $csv): array
{
    $parts = array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== '');
    return array_values($parts);
}

function webauthn_clean_transports(array $transports): array
{
    $normalized = [];
    foreach ($transports as $transport) {
        $value = strtolower(trim((string) $transport));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        if ($value !== '' && !in_array($value, $normalized, true)) {
            $normalized[] = $value;
        }
    }
    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webauthn_json_response(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
}

try {
    start_admin_session();
    $rawInput = file_get_contents('php://input');
    $jsonInput = is_string($rawInput) ? json_decode($rawInput, true) : null;
    $input = is_array($jsonInput) ? $jsonInput : $_POST;

    $action = (string) ($input['action'] ?? '');
    if ($action === '') {
        webauthn_json_response(['ok' => false, 'error' => 'Accion requerida.'], 422);
    }

    if ($action === 'begin_registration') {
        require_admin_login();
        $csrf = (string) ($input['csrf'] ?? '');
        if (!is_valid_csrf_token($csrf)) {
            webauthn_json_response(['ok' => false, 'error' => 'CSRF invalido.'], 419);
        }

        $user = $_SESSION['admin_user'] ?? [];
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            webauthn_json_response(['ok' => false, 'error' => 'Sesion invalida.'], 401);
        }

        $challenge = webauthn_b64url_encode(random_bytes(32));
        $_SESSION['webauthn_reg'] = [
            'challenge' => $challenge,
            'user_id' => $userId,
            'expires_at' => time() + 300,
        ];

        $stmt = $pdo->prepare('SELECT credential_id, transports FROM admin_passkeys WHERE usuario_admin_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        $existing = $stmt->fetchAll();

        $exclude = [];
        foreach ($existing as $row) {
            $exclude[] = [
                'id' => (string) $row['credential_id'],
                'type' => 'public-key',
                'transports' => webauthn_parse_transports((string) ($row['transports'] ?? '')),
            ];
        }

        $userHandle = webauthn_b64url_encode(pack('N', $userId));

        webauthn_json_response([
            'ok' => true,
            'publicKey' => [
                'challenge' => $challenge,
                'rp' => [
                    'name' => 'Consultorio PsicoBienestar',
                    'id' => webauthn_get_rp_id(),
                ],
                'user' => [
                    'id' => $userHandle,
                    'name' => (string) ($user['email'] ?? ('admin-' . $userId . '@localhost')),
                    'displayName' => (string) ($user['nombre'] ?? 'Usuario admin'),
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7], // ES256
                ],
                'timeout' => 60000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'userVerification' => 'preferred',
                ],
                'excludeCredentials' => $exclude,
            ],
        ]);
    }

    if ($action === 'finish_registration') {
        require_admin_login();
        $csrf = (string) ($input['csrf'] ?? '');
        if (!is_valid_csrf_token($csrf)) {
            webauthn_json_response(['ok' => false, 'error' => 'CSRF invalido.'], 419);
        }

        $challengeData = $_SESSION['webauthn_reg'] ?? null;
        if (!is_array($challengeData)) {
            webauthn_json_response(['ok' => false, 'error' => 'No hay registro WebAuthn iniciado.'], 422);
        }
        if (time() > (int) ($challengeData['expires_at'] ?? 0)) {
            unset($_SESSION['webauthn_reg']);
            webauthn_json_response(['ok' => false, 'error' => 'La solicitud expiro. Intenta de nuevo.'], 422);
        }

        $currentUserId = (int) (($_SESSION['admin_user'] ?? [])['id'] ?? 0);
        if ($currentUserId <= 0 || $currentUserId !== (int) ($challengeData['user_id'] ?? 0)) {
            webauthn_json_response(['ok' => false, 'error' => 'Usuario de registro invalido.'], 422);
        }

        $credential = $input['credential'] ?? null;
        if (!is_array($credential)) {
            webauthn_json_response(['ok' => false, 'error' => 'Credential invalida.'], 422);
        }

        $verified = webauthn_verify_registration_response(
            $credential,
            (string) ($challengeData['challenge'] ?? ''),
            webauthn_get_origin(),
            webauthn_get_rp_id()
        );

        $credentialIdFromClient = (string) ($credential['id'] ?? '');
        if ($credentialIdFromClient !== '' && !hash_equals($verified['credential_id'], $credentialIdFromClient)) {
            webauthn_json_response(['ok' => false, 'error' => 'Credential ID no coincide.'], 422);
        }

        $transports = webauthn_clean_transports((array) ($verified['transports'] ?? []));
        $transportsCsv = implode(',', $transports);
        $label = trim((string) ($input['label'] ?? ''));

        $existsStmt = $pdo->prepare('SELECT id FROM admin_passkeys WHERE credential_id = ? LIMIT 1');
        $existsStmt->execute([$verified['credential_id']]);
        if ($existsStmt->fetch()) {
            webauthn_json_response(['ok' => false, 'error' => 'Esta passkey ya esta registrada.'], 409);
        }

        $insert = $pdo->prepare(
            'INSERT INTO admin_passkeys (usuario_admin_id, credential_id, public_key_pem, sign_count, transports, label)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $currentUserId,
            $verified['credential_id'],
            $verified['public_key_pem'],
            $verified['sign_count'],
            $transportsCsv,
            $label,
        ]);

        unset($_SESSION['webauthn_reg']);
        webauthn_json_response(['ok' => true, 'message' => 'Passkey registrada correctamente.']);
    }

    if ($action === 'begin_authentication') {
        $challenge = webauthn_b64url_encode(random_bytes(32));
        $_SESSION['webauthn_auth'] = [
            'challenge' => $challenge,
            'expires_at' => time() + 300,
        ];

        $stmt = $pdo->query(
            'SELECT p.credential_id, p.transports
             FROM admin_passkeys p
             INNER JOIN usuarios_admin u ON u.id = p.usuario_admin_id
             WHERE u.activo = 1
             ORDER BY p.id DESC'
        );
        $rows = $stmt->fetchAll();

        if (count($rows) === 0) {
            webauthn_json_response(['ok' => false, 'error' => 'No hay passkeys registradas en el sistema.'], 404);
        }

        $allowCredentials = [];
        foreach ($rows as $row) {
            $allowCredentials[] = [
                'id' => (string) $row['credential_id'],
                'type' => 'public-key',
                'transports' => webauthn_parse_transports((string) ($row['transports'] ?? '')),
            ];
        }

        webauthn_json_response([
            'ok' => true,
            'publicKey' => [
                'challenge' => $challenge,
                'rpId' => webauthn_get_rp_id(),
                'allowCredentials' => $allowCredentials,
                'timeout' => 60000,
                'userVerification' => 'preferred',
            ],
        ]);
    }

    if ($action === 'finish_authentication') {
        $challengeData = $_SESSION['webauthn_auth'] ?? null;
        if (!is_array($challengeData)) {
            webauthn_json_response(['ok' => false, 'error' => 'No hay autenticacion WebAuthn iniciada.'], 422);
        }
        if (time() > (int) ($challengeData['expires_at'] ?? 0)) {
            unset($_SESSION['webauthn_auth']);
            webauthn_json_response(['ok' => false, 'error' => 'La autenticacion expiro. Intenta de nuevo.'], 422);
        }

        $credential = $input['credential'] ?? null;
        if (!is_array($credential)) {
            webauthn_json_response(['ok' => false, 'error' => 'Credential invalida.'], 422);
        }

        $credentialId = (string) ($credential['id'] ?? '');
        if ($credentialId === '') {
            webauthn_json_response(['ok' => false, 'error' => 'Credential ID requerido.'], 422);
        }

        $stmt = $pdo->prepare(
            'SELECT p.id AS passkey_id, p.usuario_admin_id, p.public_key_pem, p.sign_count, u.activo
             FROM admin_passkeys p
             INNER JOIN usuarios_admin u ON u.id = p.usuario_admin_id
             WHERE p.credential_id = ?
             LIMIT 1'
        );
        $stmt->execute([$credentialId]);
        $passkey = $stmt->fetch();

        if (!$passkey || (int) $passkey['activo'] !== 1) {
            webauthn_json_response(['ok' => false, 'error' => 'Passkey no valida o usuario inactivo.'], 404);
        }

        $verified = webauthn_verify_authentication_response(
            $credential,
            (string) ($challengeData['challenge'] ?? ''),
            webauthn_get_origin(),
            webauthn_get_rp_id(),
            (string) $passkey['public_key_pem'],
            (int) $passkey['sign_count']
        );

        $updatePasskey = $pdo->prepare(
            'UPDATE admin_passkeys
             SET sign_count = ?, ultimo_uso_en = NOW()
             WHERE id = ?'
        );
        $updatePasskey->execute([(int) $verified['new_sign_count'], (int) $passkey['passkey_id']]);

        $logged = admin_login_by_id($pdo, (int) $passkey['usuario_admin_id']);
        if (!$logged) {
            webauthn_json_response(['ok' => false, 'error' => 'No se pudo iniciar sesion con passkey.'], 401);
        }

        unset($_SESSION['webauthn_auth']);
        webauthn_json_response([
            'ok' => true,
            'redirect' => admin_must_change_password() ? 'cambiar_password.php' : 'index.php',
        ]);
    }

    if ($action === 'list_my_passkeys') {
        require_admin_login();
        $currentUserId = (int) (($_SESSION['admin_user'] ?? [])['id'] ?? 0);
        $stmt = $pdo->prepare(
            'SELECT id, credential_id, label, transports, sign_count, ultimo_uso_en, creado_en
             FROM admin_passkeys
             WHERE usuario_admin_id = ?
             ORDER BY id DESC'
        );
        $stmt->execute([$currentUserId]);
        $rows = $stmt->fetchAll();
        webauthn_json_response(['ok' => true, 'passkeys' => $rows]);
    }

    if ($action === 'delete_my_passkey') {
        require_admin_login();
        $csrf = (string) ($input['csrf'] ?? '');
        if (!is_valid_csrf_token($csrf)) {
            webauthn_json_response(['ok' => false, 'error' => 'CSRF invalido.'], 419);
        }

        $passkeyId = (int) ($input['passkey_id'] ?? 0);
        if ($passkeyId <= 0) {
            webauthn_json_response(['ok' => false, 'error' => 'Passkey invalida.'], 422);
        }

        $currentUserId = (int) (($_SESSION['admin_user'] ?? [])['id'] ?? 0);
        $delete = $pdo->prepare('DELETE FROM admin_passkeys WHERE id = ? AND usuario_admin_id = ?');
        $delete->execute([$passkeyId, $currentUserId]);

        webauthn_json_response(['ok' => true, 'message' => 'Passkey eliminada.']);
    }

    webauthn_json_response(['ok' => false, 'error' => 'Accion no soportada.'], 422);
} catch (RuntimeException $e) {
    webauthn_json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    webauthn_json_response(['ok' => false, 'error' => 'Error interno al procesar WebAuthn.'], 500);
}
