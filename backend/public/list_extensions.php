<?php
declare(strict_types=1);

$common = getenv('C2C_PUBLIC_COMMON');
if (!is_string($common) || $common === '') {
    $common = '/usr/local/lib/uniloud-click2call-public/lib/common.php';
}
require $common;

c2cpSecurityHeaders();
$requestId = c2cpRequestId();
$config = c2cpLoadConfig($requestId);
$remoteIp = c2cpTrustedClientIp($config);
if (c2cpValidateConfig($config) !== array()) {
    c2cpRespond(500, array(
        'success' => false,
        'error' => 'Server configuration is incomplete.',
        'requestId' => $requestId,
    ));
}
if ((bool) c2cpCfg($config, 'require_https', true) && !c2cpIsHttpsRequest($config)) {
    c2cpRespond(426, array(
        'success' => false,
        'error' => 'HTTPS is required.',
        'requestId' => $requestId,
    ));
}

try {
    $allowed = c2cpRateLimit(
        $config,
        'directory-preauth|' . $remoteIp,
        (int) c2cpNestedCfg($config, 'rate_limit', 'directory_preauth_max', 20),
        (int) c2cpNestedCfg($config, 'rate_limit', 'directory_preauth_window_seconds', 60)
    );
} catch (Throwable) {
    c2cpRespond(503, array(
        'success' => false,
        'error' => 'Request protection is temporarily unavailable.',
        'requestId' => $requestId,
    ));
}
if (!$allowed) {
    c2cpRespond(429, array(
        'success' => false,
        'error' => 'Too many requests. Try again shortly.',
        'requestId' => $requestId,
    ));
}

$input = c2cpRequireJsonPost($requestId);
$client = c2cpAuthenticateClient($config, $input);
if ($client === null) {
    c2cpWriteAudit($config, array(
        'request_id' => $requestId,
        'remote_ip' => $remoteIp,
        'result' => 'directory_unauthorized',
    ));
    c2cpRespond(401, array(
        'success' => false,
        'error' => 'Unauthorized.',
        'requestId' => $requestId,
    ));
}
$protocol = c2cpNormalizeProtocol($input['protocol'] ?? null);
if ($protocol === null || !c2cpClientAllowsProtocol($client, $protocol)) {
    c2cpRespond(400, array(
        'success' => false,
        'error' => 'Invalid or unauthorized protocol.',
        'requestId' => $requestId,
    ));
}

try {
    $allowed = c2cpRateLimit(
        $config,
        'directory-client|' . $client['id'] . '|' . $remoteIp,
        (int) c2cpNestedCfg($config, 'rate_limit', 'directory_client_max', 10),
        (int) c2cpNestedCfg($config, 'rate_limit', 'directory_client_window_seconds', 60)
    );
} catch (Throwable) {
    c2cpRespond(503, array(
        'success' => false,
        'error' => 'Request protection is temporarily unavailable.',
        'requestId' => $requestId,
    ));
}
if (!$allowed) {
    c2cpRespond(429, array(
        'success' => false,
        'error' => 'Too many directory requests. Try again shortly.',
        'requestId' => $requestId,
    ));
}

$result = array();
foreach (array_keys($client['extensions'] ?? array()) as $extension) {
    $record = c2cpExtensionRecord($client, (string) $extension, $protocol);
    if ($record === null) {
        continue;
    }
    $result[] = array(
        'extension' => $record['extension'],
        'name' => $record['name'],
        'protocol' => $protocol,
    );
}
usort(
    $result,
    static fn (array $left, array $right): int =>
        strnatcmp((string) $left['extension'], (string) $right['extension'])
);

c2cpWriteAudit($config, array(
    'request_id' => $requestId,
    'remote_ip' => $remoteIp,
    'api_client' => $client['id'],
    'protocol' => $protocol,
    'extension_count' => count($result),
    'result' => 'directory_returned',
));
c2cpRespond(200, array(
    'success' => true,
    'requestId' => $requestId,
    'backendVersion' => C2C_PUBLIC_BACKEND_VERSION,
    'phpRuntime' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    'protocol' => $protocol,
    'extensions' => $result,
));
