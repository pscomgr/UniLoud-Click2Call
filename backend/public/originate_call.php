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
$configErrors = c2cpValidateConfig($config);
if ($configErrors !== array()) {
    error_log("Click2Call public [{$requestId}] configuration validation failed.");
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
        'preauth|' . $remoteIp,
        (int) c2cpNestedCfg($config, 'rate_limit', 'preauth_max', 20),
        (int) c2cpNestedCfg($config, 'rate_limit', 'preauth_window_seconds', 60)
    );
} catch (Throwable) {
    c2cpRespond(503, array(
        'success' => false,
        'error' => 'Request protection is temporarily unavailable.',
        'requestId' => $requestId,
    ));
}
if (!$allowed) {
    c2cpWriteAudit($config, array(
        'request_id' => $requestId,
        'remote_ip' => $remoteIp,
        'result' => 'preauth_rate_limited',
    ));
    c2cpRespond(429, array(
        'success' => false,
        'error' => 'Too many requests. Try again shortly.',
        'requestId' => $requestId,
    ));
}

$input = c2cpRequireJsonPost($requestId);
foreach (array('apiId', 'apiSecret', 'extension', 'protocol', 'destination') as $key) {
    if (!isset($input[$key]) || !is_string($input[$key])) {
        c2cpRespond(400, array(
            'success' => false,
            'error' => "Missing or invalid {$key}.",
            'requestId' => $requestId,
        ));
    }
}

$client = c2cpAuthenticateClient($config, $input);
if ($client === null) {
    c2cpWriteAudit($config, array(
        'request_id' => $requestId,
        'remote_ip' => $remoteIp,
        'result' => 'unauthorized',
    ));
    c2cpRespond(401, array(
        'success' => false,
        'error' => 'Unauthorized.',
        'requestId' => $requestId,
    ));
}

try {
    $allowed = c2cpRateLimit(
        $config,
        'client|' . $client['id'] . '|' . $remoteIp,
        (int) c2cpNestedCfg($config, 'rate_limit', 'client_max', 30),
        (int) c2cpNestedCfg($config, 'rate_limit', 'client_window_seconds', 60)
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
        'error' => 'Too many call requests. Try again shortly.',
        'requestId' => $requestId,
    ));
}

$extension = trim($input['extension']);
$protocol = c2cpNormalizeProtocol($input['protocol']);
$extensionRecord = $protocol === null
    ? null
    : c2cpExtensionRecord($client, $extension, $protocol);
$destination = c2cpNormalizeDestination($config, $input['destination']);
if ($extensionRecord === null) {
    c2cpRespond(403, array(
        'success' => false,
        'error' => 'The extension or protocol is not authorized for this API client.',
        'requestId' => $requestId,
    ));
}
if ($destination === null) {
    c2cpRespond(400, array(
        'success' => false,
        'error' => 'The destination is not permitted by the server dial policy.',
        'requestId' => $requestId,
    ));
}

$rawPageUrl = $input['pageUrl'] ?? null;
$pageUrl = c2cpSanitizePageUrl($rawPageUrl);
$pageStatus = $rawPageUrl === null
    ? 'not_provided'
    : ($pageUrl === null ? 'rejected' : 'accepted');
$clientVersion = c2cpSafeLogString($input['clientVersion'] ?? '', 32);
$ringAll = $protocol === 'pjsip'
    && (bool) c2cpNestedCfg($config, 'telephony', 'ring_all_contacts', true);
$ringContext = (string) c2cpNestedCfg(
    $config,
    'telephony',
    'pjsip_ring_context',
    'custom-c2c-public-ring'
);
$outboundContext = (string) c2cpNestedCfg(
    $config,
    'telephony',
    'outbound_context',
    'from-internal'
);
$ringSeconds = max(
    5,
    min(120, (int) c2cpNestedCfg($config, 'telephony', 'ring_timeout_seconds', 30))
);
$channel = $ringAll
    ? 'Local/' . $extension . '@' . $ringContext . '/n'
    : strtoupper($protocol) . '/' . $extensionRecord['endpoint'];
$accountCode = 'c2p-' . substr($requestId, 0, 16);

$audit = array(
    'request_id' => $requestId,
    'remote_ip' => $remoteIp,
    'api_client' => $client['id'],
    'extension' => $extension,
    'protocol' => $protocol,
    'ring_all_contacts' => $ringAll,
    'client_version' => $clientVersion,
    'page_context_status' => $pageStatus,
);
if ((bool) c2cpNestedCfg($config, 'audit', 'log_destination', true)) {
    $audit['destination'] = $destination;
}
if ((bool) c2cpNestedCfg($config, 'audit', 'log_page_url', false)) {
    $audit['page_url'] = $pageUrl;
}

try {
    c2cpAmiOriginate($config, array(
        'Channel' => $channel,
        'Context' => $outboundContext,
        'Exten' => $destination,
        'Priority' => 1,
        'CallerID' => $extension . ' <' . $extension . '>',
        'Account' => $accountCode,
        'Variable' => array(
            '__AMPUSER=' . $extension,
            '__REALCALLERIDNUM=' . $extension,
            '__C2C_PUBLIC_REQUEST_ID=' . $requestId,
            '__C2C_PUBLIC_EXTENSION=' . $extension,
            '__C2C_PUBLIC_ENDPOINT=' . $extensionRecord['endpoint'],
            '__C2C_PUBLIC_RING_TIMEOUT=' . $ringSeconds,
        ),
        'Timeout' => $ringSeconds * 1000,
    ), $requestId);
} catch (Throwable $exception) {
    c2cpWriteAudit($config, array_merge($audit, array(
        'result' => 'originate_failed',
        'error' => c2cpSafeLogString($exception->getMessage(), 160),
    )));
    c2cpRespond(502, array(
        'success' => false,
        'error' => 'The PBX could not place the call.',
        'requestId' => $requestId,
    ));
}

c2cpWriteAudit($config, array_merge($audit, array('result' => 'queued')));
c2cpRespond(200, array(
    'success' => true,
    'message' => "Call queued: {$extension} -> {$destination}",
    'requestId' => $requestId,
    'backendVersion' => C2C_PUBLIC_BACKEND_VERSION,
    'protocol' => $protocol,
    'ringAllContacts' => $ringAll,
    'contextAccepted' => $pageUrl !== null,
    'pageContextStatus' => $pageStatus,
));
