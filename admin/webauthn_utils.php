<?php

declare(strict_types=1);

/**
 * WebAuthn helpers (base64url, CBOR y verificacion de firmas ES256).
 * Nota: se valida challenge/origin/rpIdHash/UP y firma; no se valida atestacion CA.
 */

function webauthn_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function webauthn_b64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $padding = strlen($data) % 4;
    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($data, true);
    if (!is_string($decoded)) {
        throw new RuntimeException('Base64url invalido.');
    }
    return $decoded;
}

function webauthn_get_origin(): string
{
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function webauthn_get_rp_id(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $parts = explode(':', $host);
    return strtolower(trim((string) ($parts[0] ?? 'localhost')));
}

function webauthn_read_uint(string $data, int &$offset, int $size): int
{
    if ($offset + $size > strlen($data)) {
        throw new RuntimeException('CBOR truncado.');
    }
    $value = 0;
    for ($i = 0; $i < $size; $i++) {
        $value = ($value << 8) | ord($data[$offset + $i]);
    }
    $offset += $size;
    return $value;
}

function webauthn_read_length(string $data, int &$offset, int $additional): int
{
    if ($additional < 24) {
        return $additional;
    }
    return match ($additional) {
        24 => webauthn_read_uint($data, $offset, 1),
        25 => webauthn_read_uint($data, $offset, 2),
        26 => webauthn_read_uint($data, $offset, 4),
        default => throw new RuntimeException('CBOR no soporta longitudes indefinidas/64-bit en este contexto.'),
    };
}

function webauthn_cbor_decode(string $data, int &$offset = 0): mixed
{
    if ($offset >= strlen($data)) {
        throw new RuntimeException('CBOR vacio.');
    }

    $initial = ord($data[$offset++]);
    $major = $initial >> 5;
    $additional = $initial & 0x1f;

    switch ($major) {
        case 0:
            return webauthn_read_length($data, $offset, $additional);
        case 1:
            return -1 - webauthn_read_length($data, $offset, $additional);
        case 2:
            $len = webauthn_read_length($data, $offset, $additional);
            if ($offset + $len > strlen($data)) {
                throw new RuntimeException('CBOR bytes truncados.');
            }
            $v = substr($data, $offset, $len);
            $offset += $len;
            return $v;
        case 3:
            $len = webauthn_read_length($data, $offset, $additional);
            if ($offset + $len > strlen($data)) {
                throw new RuntimeException('CBOR texto truncado.');
            }
            $v = substr($data, $offset, $len);
            $offset += $len;
            return $v;
        case 4:
            $len = webauthn_read_length($data, $offset, $additional);
            $arr = [];
            for ($i = 0; $i < $len; $i++) {
                $arr[] = webauthn_cbor_decode($data, $offset);
            }
            return $arr;
        case 5:
            $len = webauthn_read_length($data, $offset, $additional);
            $map = [];
            for ($i = 0; $i < $len; $i++) {
                $k = webauthn_cbor_decode($data, $offset);
                $v = webauthn_cbor_decode($data, $offset);
                if (!is_int($k) && !is_string($k)) {
                    throw new RuntimeException('CBOR map key invalida.');
                }
                $map[$k] = $v;
            }
            return $map;
        case 6:
            webauthn_cbor_decode($data, $offset); // tag
            return webauthn_cbor_decode($data, $offset);
        case 7:
            return match ($additional) {
                20 => false,
                21 => true,
                22 => null,
                23 => null,
                default => throw new RuntimeException('CBOR simple value no soportado.'),
            };
        default:
            throw new RuntimeException('CBOR major type no soportado.');
    }
}

function webauthn_parse_auth_data(string $authData): array
{
    if (strlen($authData) < 37) {
        throw new RuntimeException('authenticatorData invalido.');
    }

    $rpIdHash = substr($authData, 0, 32);
    $flags = ord($authData[32]);
    $signCount = unpack('N', substr($authData, 33, 4))[1];

    $result = [
        'rpIdHash' => $rpIdHash,
        'flags' => $flags,
        'signCount' => (int) $signCount,
        'credentialId' => null,
        'credentialPublicKey' => null,
    ];

    $offset = 37;
    $hasAttestedData = ($flags & 0x40) !== 0;
    if ($hasAttestedData) {
        if (strlen($authData) < $offset + 18) {
            throw new RuntimeException('authenticatorData (attested) truncado.');
        }
        $offset += 16; // AAGUID
        $credLen = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        if (strlen($authData) < $offset + $credLen) {
            throw new RuntimeException('Credential ID truncado.');
        }
        $result['credentialId'] = substr($authData, $offset, $credLen);
        $offset += $credLen;
        $result['credentialPublicKey'] = substr($authData, $offset);
    }

    return $result;
}

function webauthn_asn1_len(int $len): string
{
    if ($len < 128) {
        return chr($len);
    }
    $bytes = '';
    while ($len > 0) {
        $bytes = chr($len & 0xff) . $bytes;
        $len >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function webauthn_asn1_seq(string $body): string
{
    return "\x30" . webauthn_asn1_len(strlen($body)) . $body;
}

function webauthn_asn1_bitstring(string $raw): string
{
    return "\x03" . webauthn_asn1_len(strlen($raw) + 1) . "\x00" . $raw;
}

function webauthn_cose_ec2_to_pem(array $coseKey): string
{
    $kty = (int) ($coseKey[1] ?? 0);
    $crv = (int) ($coseKey[-1] ?? 0);
    $x = $coseKey[-2] ?? null;
    $y = $coseKey[-3] ?? null;

    if ($kty !== 2 || $crv !== 1 || !is_string($x) || !is_string($y)) {
        throw new RuntimeException('Solo se soporta ES256 (P-256) para passkeys.');
    }

    $pub = "\x04" . $x . $y;

    // id-ecPublicKey + prime256v1
    $algId = hex2bin('301306072A8648CE3D020106082A8648CE3D030107');
    if (!is_string($algId)) {
        throw new RuntimeException('No se pudo construir AlgorithmIdentifier.');
    }

    $spki = webauthn_asn1_seq($algId . webauthn_asn1_bitstring($pub));
    $pem = "-----BEGIN PUBLIC KEY-----\n";
    $pem .= chunk_split(base64_encode($spki), 64, "\n");
    $pem .= "-----END PUBLIC KEY-----\n";
    return $pem;
}

function webauthn_verify_client_data(string $clientDataJSON, string $expectedType, string $expectedChallenge, string $expectedOrigin): array
{
    $client = json_decode($clientDataJSON, true);
    if (!is_array($client)) {
        throw new RuntimeException('clientDataJSON invalido.');
    }

    $type = (string) ($client['type'] ?? '');
    $challenge = (string) ($client['challenge'] ?? '');
    $origin = (string) ($client['origin'] ?? '');

    if ($type !== $expectedType) {
        throw new RuntimeException('Tipo WebAuthn invalido.');
    }
    if (!hash_equals($expectedChallenge, $challenge)) {
        throw new RuntimeException('Challenge invalido.');
    }
    if ($origin !== $expectedOrigin) {
        throw new RuntimeException('Origin invalido.');
    }

    return $client;
}

function webauthn_verify_registration_response(array $credential, string $expectedChallenge, string $expectedOrigin, string $expectedRpId): array
{
    $response = $credential['response'] ?? null;
    if (!is_array($response)) {
        throw new RuntimeException('Respuesta de registro invalida.');
    }

    $clientDataJSON = webauthn_b64url_decode((string) ($response['clientDataJSON'] ?? ''));
    $attestationObject = webauthn_b64url_decode((string) ($response['attestationObject'] ?? ''));

    webauthn_verify_client_data($clientDataJSON, 'webauthn.create', $expectedChallenge, $expectedOrigin);

    $offset = 0;
    $attObj = webauthn_cbor_decode($attestationObject, $offset);
    if (!is_array($attObj) || !isset($attObj['authData']) || !is_string($attObj['authData'])) {
        throw new RuntimeException('attestationObject invalido.');
    }

    $authData = $attObj['authData'];
    $parsedAuth = webauthn_parse_auth_data($authData);
    $rpIdHashExpected = hash('sha256', $expectedRpId, true);
    if (!hash_equals($rpIdHashExpected, (string) $parsedAuth['rpIdHash'])) {
        throw new RuntimeException('RP ID hash invalido.');
    }
    if ((((int) $parsedAuth['flags']) & 0x01) === 0) {
        throw new RuntimeException('El autenticador no confirma presencia de usuario.');
    }
    if (!is_string($parsedAuth['credentialId']) || !is_string($parsedAuth['credentialPublicKey'])) {
        throw new RuntimeException('No se encontro clave publica de la passkey.');
    }

    $coseOffset = 0;
    $coseKey = webauthn_cbor_decode($parsedAuth['credentialPublicKey'], $coseOffset);
    if (!is_array($coseKey)) {
        throw new RuntimeException('COSE key invalida.');
    }

    $publicKeyPem = webauthn_cose_ec2_to_pem($coseKey);

    return [
        'credential_id' => webauthn_b64url_encode($parsedAuth['credentialId']),
        'public_key_pem' => $publicKeyPem,
        'sign_count' => (int) $parsedAuth['signCount'],
        'transports' => is_array($response['transports'] ?? null) ? $response['transports'] : [],
    ];
}

function webauthn_verify_authentication_response(
    array $credential,
    string $expectedChallenge,
    string $expectedOrigin,
    string $expectedRpId,
    string $publicKeyPem,
    int $storedSignCount
): array {
    $response = $credential['response'] ?? null;
    if (!is_array($response)) {
        throw new RuntimeException('Respuesta de autenticacion invalida.');
    }

    $clientDataJSON = webauthn_b64url_decode((string) ($response['clientDataJSON'] ?? ''));
    $authenticatorData = webauthn_b64url_decode((string) ($response['authenticatorData'] ?? ''));
    $signature = webauthn_b64url_decode((string) ($response['signature'] ?? ''));

    webauthn_verify_client_data($clientDataJSON, 'webauthn.get', $expectedChallenge, $expectedOrigin);

    $parsedAuth = webauthn_parse_auth_data($authenticatorData);
    $rpIdHashExpected = hash('sha256', $expectedRpId, true);
    if (!hash_equals($rpIdHashExpected, (string) $parsedAuth['rpIdHash'])) {
        throw new RuntimeException('RP ID hash invalido.');
    }
    if ((((int) $parsedAuth['flags']) & 0x01) === 0) {
        throw new RuntimeException('El autenticador no confirma presencia de usuario.');
    }

    $dataToVerify = $authenticatorData . hash('sha256', $clientDataJSON, true);
    $ok = openssl_verify($dataToVerify, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new RuntimeException('Firma WebAuthn invalida.');
    }

    $newCount = (int) $parsedAuth['signCount'];
    if ($storedSignCount > 0 && $newCount > 0 && $newCount <= $storedSignCount) {
        throw new RuntimeException('Contador de firma invalido (posible clonacion).');
    }

    return [
        'new_sign_count' => $newCount,
    ];
}

