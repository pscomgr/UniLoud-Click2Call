<?php
declare(strict_types=1);

/**
 * UniLoud Click-to-Call public backend primitives.
 *
 * Compatible with PHP 8.0+. No CRM, CDR database, recording or vendor-specific
 * integration code belongs in this public core.
 */

const C2C_PUBLIC_BACKEND_VERSION = '1.4.0';
const C2C_PUBLIC_MAX_REQUEST_BYTES = 16384;
const C2C_PUBLIC_MAX_PAGE_URL_LENGTH = 2048;

function c2cpCfg(array $config, string $key, mixed $default = null): mixed
{
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

function c2cpNestedCfg(
    array $config,
    string $section,
    string $key,
    mixed $default = null
): mixed {
    $value = c2cpCfg($config, $section, array());
    if (!is_array($value)) {
        return $default;
    }
    return array_key_exists($key, $value) ? $value[$key] : $default;
}

function c2cpRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function c2cpSecurityHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
}

function c2cpRequestId(): string
{
    return bin2hex(random_bytes(16));
}

function c2cpSafeLogString(mixed $value, int $maxLength = 512): string
{
    $string = is_scalar($value) ? (string) $value : '';
    $string = str_replace(array("\r", "\n", "\0"), ' ', $string);
    return substr($string, 0, $maxLength);
}

function c2cpLoadConfig(string $requestId): array
{
    $path = getenv('C2C_PUBLIC_CONFIG');
    if (!is_string($path) || $path === '') {
        $path = '/etc/uniloud-click2call-public/config.php';
    }
    if (!is_file($path) || !is_readable($path)) {
        error_log("Click2Call public [{$requestId}] configuration is unavailable.");
        c2cpRespond(500, array(
            'success' => false,
            'error' => 'Server configuration is unavailable.',
            'requestId' => $requestId,
        ));
    }
    $config = require $path;
    if (!is_array($config)) {
        error_log("Click2Call public [{$requestId}] configuration is invalid.");
        c2cpRespond(500, array(
            'success' => false,
            'error' => 'Server configuration is invalid.',
            'requestId' => $requestId,
        ));
    }
    return $config;
}

function c2cpTrustedClientIp(array $config): string
{
    $remote = c2cpSafeLogString($_SERVER['REMOTE_ADDR'] ?? 'unknown', 64);
    $trusted = c2cpCfg($config, 'trusted_proxy_ips', array());
    if (!is_array($trusted) || !in_array($remote, array_map('strval', $trusted), true)) {
        return $remote;
    }
    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    $candidate = trim(explode(',', $forwarded, 2)[0] ?? '');
    return filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $remote;
}

function c2cpIsHttpsRequest(array $config): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https === 'on' || $https === '1') {
        return true;
    }
    $remote = c2cpSafeLogString($_SERVER['REMOTE_ADDR'] ?? '', 64);
    $trusted = c2cpCfg($config, 'trusted_proxy_ips', array());
    if (!is_array($trusted) || !in_array($remote, array_map('strval', $trusted), true)) {
        return false;
    }
    $proto = strtolower(trim(explode(
        ',',
        (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''),
        2
    )[0] ?? ''));
    return $proto === 'https';
}

function c2cpRequireJsonPost(string $requestId): array
{
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
    if ($method !== 'POST') {
        header('Allow: POST');
        c2cpRespond(405, array(
            'success' => false,
            'error' => 'Method not allowed.',
            'requestId' => $requestId,
        ));
    }
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > C2C_PUBLIC_MAX_REQUEST_BYTES) {
        c2cpRespond(413, array(
            'success' => false,
            'error' => 'Request body is too large.',
            'requestId' => $requestId,
        ));
    }
    $contentType = strtolower(trim(explode(
        ';',
        (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
        2
    )[0]));
    if ($contentType !== 'application/json') {
        c2cpRespond(415, array(
            'success' => false,
            'error' => 'Content-Type must be application/json.',
            'requestId' => $requestId,
        ));
    }
    $body = file_get_contents(
        'php://input',
        false,
        null,
        0,
        C2C_PUBLIC_MAX_REQUEST_BYTES + 1
    );
    if (!is_string($body)
        || $body === ''
        || strlen($body) > C2C_PUBLIC_MAX_REQUEST_BYTES) {
        c2cpRespond(400, array(
            'success' => false,
            'error' => 'Invalid or empty request body.',
            'requestId' => $requestId,
        ));
    }
    try {
        $input = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        c2cpRespond(400, array(
            'success' => false,
            'error' => 'Malformed JSON body.',
            'requestId' => $requestId,
        ));
    }
    $isList = is_array($input)
        && ($input === array() || array_keys($input) === range(0, count($input) - 1));
    if (!is_array($input) || $isList) {
        c2cpRespond(400, array(
            'success' => false,
            'error' => 'JSON body must be an object.',
            'requestId' => $requestId,
        ));
    }
    return $input;
}

function c2cpWriteAudit(array $config, array $entry): void
{
    $path = (string) c2cpNestedCfg($config, 'audit', 'log_file', '');
    if ($path === '') {
        return;
    }
    unset(
        $entry['apiSecret'],
        $entry['api_secret'],
        $entry['ami_secret'],
        $entry['authorization']
    );
    $entry = array_merge(array(
        'timestamp' => gmdate('c'),
        'service' => 'uniloud-click2call-public',
        'backend_version' => C2C_PUBLIC_BACKEND_VERSION,
    ), $entry);
    $json = json_encode(
        $entry,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if (is_string($json)) {
        @file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function c2cpRateLimit(
    array $config,
    string $key,
    int $maximum,
    int $windowSeconds
): bool {
    if ($maximum <= 0 || $windowSeconds <= 0) {
        return false;
    }
    $directory = (string) c2cpNestedCfg($config, 'rate_limit', 'directory', '');
    if ($directory === '' || !is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Rate-limit storage is unavailable.');
    }
    $path = rtrim($directory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . hash('sha256', $key)
        . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Rate-limit state cannot be locked.');
    }
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : array();
        $events = is_array($decoded) ? $decoded : array();
        $threshold = microtime(true) - $windowSeconds;
        $events = array_values(array_filter(
            $events,
            static fn (mixed $value): bool => is_numeric($value) && (float) $value >= $threshold
        ));
        if (count($events) >= $maximum) {
            return false;
        }
        $events[] = microtime(true);
        $json = json_encode($events);
        if (!is_string($json)) {
            throw new RuntimeException('Rate-limit state cannot be encoded.');
        }
        ftruncate($handle, 0);
        rewind($handle);
        if (fwrite($handle, $json) === false) {
            throw new RuntimeException('Rate-limit state cannot be written.');
        }
        fflush($handle);
        @chmod($path, 0640);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function c2cpNormalizeProtocol(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $protocol = strtolower(trim($value));
    return in_array($protocol, array('pjsip', 'sip'), true) ? $protocol : null;
}

function c2cpAuthenticateClient(array $config, array $input): ?array
{
    $apiId = $input['apiId'] ?? null;
    $apiSecret = $input['apiSecret'] ?? null;
    if (!is_string($apiId) || !is_string($apiSecret)
        || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $apiId)
        || strlen($apiSecret) < 24
        || strlen($apiSecret) > 512) {
        return null;
    }
    $clients = c2cpCfg($config, 'api_clients', array());
    if (!is_array($clients) || !isset($clients[$apiId]) || !is_array($clients[$apiId])) {
        hash('sha256', $apiSecret);
        return null;
    }
    $client = $clients[$apiId];
    $expected = strtolower((string) ($client['secret_sha256'] ?? ''));
    $actual = hash('sha256', $apiSecret);
    if (!preg_match('/^[a-f0-9]{64}$/D', $expected) || !hash_equals($expected, $actual)) {
        return null;
    }
    $client['id'] = $apiId;
    return $client;
}

function c2cpClientAllowsProtocol(array $client, string $protocol): bool
{
    $allowed = $client['allowed_protocols'] ?? array();
    return is_array($allowed)
        && in_array($protocol, array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            $allowed
        ), true);
}

function c2cpExtensionRecord(
    array $client,
    string $extension,
    string $protocol
): ?array {
    if (!preg_match('/^\d{2,8}$/D', $extension)
        || !c2cpClientAllowsProtocol($client, $protocol)) {
        return null;
    }
    $extensions = $client['extensions'] ?? array();
    if (!is_array($extensions) || !array_key_exists($extension, $extensions)) {
        return null;
    }
    $item = $extensions[$extension];
    if (is_string($item)) {
        $item = array('name' => $item);
    } elseif (!is_array($item)) {
        $item = array();
    }
    $protocols = $item['protocols'] ?? ($client['allowed_protocols'] ?? array());
    if (!is_array($protocols)
        || !in_array($protocol, array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            $protocols
        ), true)) {
        return null;
    }
    $endpointKey = $protocol === 'pjsip' ? 'pjsip_endpoint' : 'sip_peer';
    $endpoint = trim((string) ($item[$endpointKey] ?? $extension));
    if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $endpoint)) {
        return null;
    }
    return array(
        'extension' => $extension,
        'name' => c2cpSafeLogString($item['name'] ?? '', 120),
        'protocol' => $protocol,
        'endpoint' => $endpoint,
    );
}

function c2cpNormalizeDestination(array $config, mixed $value): ?string
{
    if (!is_string($value) || strlen($value) > 96
        || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return null;
    }
    $normalized = preg_replace('/[\s().-]+/', '', trim($value));
    if (!is_string($normalized) || $normalized === '') {
        return null;
    }
    $prefixes = c2cpNestedCfg($config, 'numbering', 'strip_prefixes', array());
    if (is_array($prefixes)) {
        usort($prefixes, static fn (mixed $a, mixed $b): int =>
            strlen((string) $b) <=> strlen((string) $a)
        );
        foreach ($prefixes as $prefix) {
            $prefix = (string) $prefix;
            if ($prefix !== '' && str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }
    }
    if (!preg_match('/^\d{2,32}$/D', $normalized)) {
        return null;
    }
    $prepend = (string) c2cpNestedCfg($config, 'numbering', 'prepend_prefix', '');
    if ($prepend !== '' && !preg_match('/^\d{1,8}$/D', $prepend)) {
        return null;
    }
    $normalized = $prepend . $normalized;
    $patterns = c2cpNestedCfg($config, 'numbering', 'allowed_patterns', array());
    if (!is_array($patterns) || $patterns === array()) {
        return null;
    }
    foreach ($patterns as $pattern) {
        if (is_string($pattern) && @preg_match($pattern, $normalized) === 1) {
            return $normalized;
        }
    }
    return null;
}

function c2cpSanitizePageUrl(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > C2C_PUBLIC_MAX_PAGE_URL_LENGTH) {
        return null;
    }
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($value);
    if (!is_array($parts)) {
        return null;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, array('https', 'http'), true)
        || $host === ''
        || preg_match('/[\x00-\x20\x7F]/', $host)) {
        return null;
    }
    $port = isset($parts['port']) ? (int) $parts['port'] : 0;
    if ($port < 0 || $port > 65535) {
        return null;
    }
    $path = (string) ($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }
    if (!str_starts_with($path, '/')
        || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return null;
    }
    $result = $scheme . '://' . $host;
    if ($port > 0) {
        $result .= ':' . $port;
    }
    $result .= $path;
    return strlen($result) <= C2C_PUBLIC_MAX_PAGE_URL_LENGTH ? $result : null;
}

function c2cpAmiWriteAll($socket, string $payload): bool
{
    $length = strlen($payload);
    $offset = 0;
    while ($offset < $length) {
        $written = @fwrite($socket, substr($payload, $offset));
        if (!is_int($written) || $written <= 0) {
            return false;
        }
        $offset += $written;
    }
    return true;
}

function c2cpAmiSendAction($socket, array $fields): bool
{
    $lines = array();
    foreach ($fields as $name => $value) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9-]*$/D', (string) $name)) {
            throw new RuntimeException('Invalid AMI field name.');
        }
        $values = is_array($value) ? $value : array($value);
        foreach ($values as $item) {
            $safe = str_replace(array("\r", "\n", "\0"), '', (string) $item);
            $lines[] = $name . ': ' . $safe;
        }
    }
    return c2cpAmiWriteAll($socket, implode("\r\n", $lines) . "\r\n\r\n");
}

function c2cpAmiReadPacket($socket): string
{
    $packet = '';
    while (!feof($socket)) {
        $line = fgets($socket, 4096);
        if ($line === false) {
            break;
        }
        $packet .= $line;
        if ($line === "\r\n" || $line === "\n") {
            break;
        }
    }
    return $packet;
}

function c2cpAmiParsePacket(string $packet): array
{
    $fields = array();
    foreach (preg_split('/\r?\n/', trim($packet)) ?: array() as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$key, $value] = explode(':', $line, 2);
        $fields[trim($key)] = trim($value);
    }
    return $fields;
}

function c2cpAmiWaitForResponse($socket, string $actionId, float $seconds): array
{
    $deadline = microtime(true) + $seconds;
    while (microtime(true) < $deadline && !feof($socket)) {
        $packet = c2cpAmiReadPacket($socket);
        if ($packet === '') {
            $meta = stream_get_meta_data($socket);
            if (($meta['timed_out'] ?? false) === true) {
                continue;
            }
            break;
        }
        $fields = c2cpAmiParsePacket($packet);
        if (($fields['ActionID'] ?? '') === $actionId && isset($fields['Response'])) {
            return $fields;
        }
    }
    throw new RuntimeException('AMI response timeout.');
}

function c2cpAmiOriginate(array $config, array $fields, string $requestId): void
{
    $ami = c2cpCfg($config, 'ami', array());
    if (!is_array($ami)) {
        throw new RuntimeException('AMI configuration is missing.');
    }
    $host = (string) ($ami['host'] ?? '127.0.0.1');
    $port = (int) ($ami['port'] ?? 5038);
    $timeoutMs = max(1000, min(60000, (int) ($ami['timeout_ms'] ?? 10000)));
    $socket = @fsockopen(
        $host,
        $port,
        $errorNumber,
        $errorMessage,
        max(1.0, $timeoutMs / 1000)
    );
    if ($socket === false) {
        throw new RuntimeException('Unable to connect to AMI.');
    }
    stream_set_timeout($socket, 1, 0);
    try {
        c2cpAmiReadPacket($socket);
        $loginId = 'c2cp-login-' . $requestId;
        if (!c2cpAmiSendAction($socket, array(
            'Action' => 'Login',
            'Username' => (string) ($ami['username'] ?? ''),
            'Secret' => (string) ($ami['secret'] ?? ''),
            'Events' => 'off',
            'ActionID' => $loginId,
        ))) {
            throw new RuntimeException('Unable to write AMI login.');
        }
        $login = c2cpAmiWaitForResponse($socket, $loginId, $timeoutMs / 1000);
        if (($login['Response'] ?? '') !== 'Success') {
            throw new RuntimeException('AMI authentication failed.');
        }
        $originateId = 'c2cp-orig-' . $requestId;
        $fields = array_merge(array(
            'Action' => 'Originate',
            'Async' => 'true',
            'ActionID' => $originateId,
        ), $fields);
        if (!c2cpAmiSendAction($socket, $fields)) {
            throw new RuntimeException('Unable to write AMI originate.');
        }
        $response = c2cpAmiWaitForResponse($socket, $originateId, $timeoutMs / 1000);
        if (($response['Response'] ?? '') !== 'Success') {
            throw new RuntimeException('AMI originate rejected.');
        }
        @c2cpAmiSendAction($socket, array('Action' => 'Logoff'));
    } finally {
        fclose($socket);
    }
}

function c2cpValidateConfig(array $config): array
{
    $errors = array();
    $clients = c2cpCfg($config, 'api_clients', array());
    if (!is_array($clients) || $clients === array()) {
        $errors[] = 'api_clients must contain at least one client';
    } else {
        foreach ($clients as $id => $client) {
            if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', (string) $id)
                || !is_array($client)) {
                $errors[] = 'invalid API client identifier';
                continue;
            }
            if (!preg_match(
                '/^[a-f0-9]{64}$/D',
                strtolower((string) ($client['secret_sha256'] ?? ''))
            )) {
                $errors[] = "client {$id} has invalid secret_sha256";
            }
            $protocols = $client['allowed_protocols'] ?? array();
            if (!is_array($protocols)
                || $protocols === array()
                || array_diff($protocols, array('pjsip', 'sip')) !== array()) {
                $errors[] = "client {$id} has invalid allowed_protocols";
            }
            $extensions = $client['extensions'] ?? array();
            if (!is_array($extensions) || $extensions === array()) {
                $errors[] = "client {$id} must explicitly allow extensions";
                continue;
            }
            foreach ($extensions as $extension => $item) {
                $extension = (string) $extension;
                if (!preg_match('/^\d{2,8}$/D', $extension)) {
                    $errors[] = "client {$id} contains invalid extension {$extension}";
                    continue;
                }
                if (!is_array($item) && !is_string($item)) {
                    $errors[] = "client {$id} extension {$extension} has invalid settings";
                    continue;
                }
                if (is_string($item)) {
                    continue;
                }
                $extensionProtocols = $item['protocols'] ?? $protocols;
                if (!is_array($extensionProtocols)
                    || $extensionProtocols === array()
                    || array_diff($extensionProtocols, array('pjsip', 'sip')) !== array()) {
                    $errors[] = "client {$id} extension {$extension} has invalid protocols";
                }
                foreach (array('pjsip_endpoint', 'sip_peer') as $endpointKey) {
                    if (!array_key_exists($endpointKey, $item)) {
                        continue;
                    }
                    if (!preg_match(
                        '/^[A-Za-z0-9_.-]{1,64}$/D',
                        (string) $item[$endpointKey]
                    )) {
                        $errors[] = "client {$id} extension {$extension} has invalid {$endpointKey}";
                    }
                }
            }
        }
    }
    $ami = c2cpCfg($config, 'ami', array());
    if (!is_array($ami)
        || (string) ($ami['username'] ?? '') === ''
        || (string) ($ami['secret'] ?? '') === ''
        || str_contains((string) ($ami['secret'] ?? ''), 'CHANGE_ME')) {
        $errors[] = 'AMI credentials are incomplete';
    }
    $host = is_array($ami) ? (string) ($ami['host'] ?? '') : '';
    $port = is_array($ami) ? (int) ($ami['port'] ?? 0) : 0;
    if ($port < 1 || $port > 65535) {
        $errors[] = 'AMI port must be between 1 and 65535';
    }
    $allowRemote = is_array($ami) && ($ami['allow_remote'] ?? false) === true;
    if (!$allowRemote && !in_array($host, array('127.0.0.1', '::1', 'localhost'), true)) {
        $errors[] = 'remote AMI requires explicit allow_remote=true';
    }
    foreach (array('outbound_context', 'pjsip_ring_context') as $key) {
        $value = (string) c2cpNestedCfg($config, 'telephony', $key, '');
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $value)) {
            $errors[] = "invalid telephony {$key}";
        }
    }
    $patterns = c2cpNestedCfg($config, 'numbering', 'allowed_patterns', array());
    if (!is_array($patterns) || $patterns === array()) {
        $errors[] = 'numbering.allowed_patterns must fail closed with explicit patterns';
    } else {
        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || @preg_match($pattern, '') === false) {
                $errors[] = 'numbering.allowed_patterns contains invalid PCRE';
            }
        }
    }
    $stripPrefixes = c2cpNestedCfg($config, 'numbering', 'strip_prefixes', array());
    if (!is_array($stripPrefixes)) {
        $errors[] = 'numbering.strip_prefixes must be an array';
    } else {
        foreach ($stripPrefixes as $prefix) {
            if (!is_string($prefix)
                || !preg_match('/^(?:(?:\+|00)\d{1,8}|\d{1,8})$/D', $prefix)) {
                $errors[] = 'numbering.strip_prefixes contains an invalid prefix';
            }
        }
    }
    $prepend = (string) c2cpNestedCfg($config, 'numbering', 'prepend_prefix', '');
    if ($prepend !== '' && !preg_match('/^\d{1,8}$/D', $prepend)) {
        $errors[] = 'numbering.prepend_prefix is invalid';
    }
    $trustedProxies = c2cpCfg($config, 'trusted_proxy_ips', array());
    if (!is_array($trustedProxies)) {
        $errors[] = 'trusted_proxy_ips must be an array';
    } else {
        foreach ($trustedProxies as $proxy) {
            if (!is_string($proxy)
                || filter_var($proxy, FILTER_VALIDATE_IP) === false) {
                $errors[] = 'trusted_proxy_ips contains an invalid IP address';
            }
        }
    }
    $rateDirectory = (string) c2cpNestedCfg($config, 'rate_limit', 'directory', '');
    $auditFile = (string) c2cpNestedCfg($config, 'audit', 'log_file', '');
    if ($rateDirectory === '' || $rateDirectory[0] !== '/') {
        $errors[] = 'rate_limit.directory must be an absolute path';
    }
    if ($auditFile === '' || $auditFile[0] !== '/') {
        $errors[] = 'audit.log_file must be an absolute path';
    }
    foreach (array($rateDirectory, $auditFile === '' ? '' : dirname($auditFile)) as $directory) {
        if ($directory === '' || !is_dir($directory) || !is_writable($directory)) {
            $errors[] = "runtime directory is unavailable: {$directory}";
        }
    }
    return $errors;
}
