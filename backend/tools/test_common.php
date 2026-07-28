#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/common.php';

function testAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL {$message}\n");
        exit(1);
    }
}

$root = sys_get_temp_dir() . '/c2cp-test-' . bin2hex(random_bytes(5));
mkdir($root . '/rate', 0700, true);
mkdir($root . '/log', 0700, true);
$config = array(
    'api_clients' => array(
        'browser-205' => array(
            'secret_sha256' => hash('sha256', 'fixture-secret-at-least-24-characters'),
            'allowed_protocols' => array('pjsip', 'sip'),
            'extensions' => array(
                '205' => array(
                    'name' => 'Fixture User',
                    'protocols' => array('pjsip'),
                    'pjsip_endpoint' => 'desk-205',
                ),
            ),
        ),
    ),
    'ami' => array(
        'host' => '127.0.0.1',
        'port' => 5038,
        'username' => 'fixture',
        'secret' => 'fixture-ami-secret',
        'allow_remote' => false,
    ),
    'telephony' => array(
        'outbound_context' => 'from-internal',
        'pjsip_ring_context' => 'custom-c2c-public-ring',
    ),
    'numbering' => array(
        'strip_prefixes' => array('+30', '0030'),
        'prepend_prefix' => '',
        'allowed_patterns' => array('/^(?:2\d{9}|69\d{8})$/D'),
    ),
    'rate_limit' => array('directory' => $root . '/rate'),
    'audit' => array('log_file' => $root . '/log/audit.log'),
);

testAssert(c2cpValidateConfig($config) === array(), 'valid fixture config was rejected');
$invalidPaths = $config;
$invalidPaths['audit']['log_file'] = '';
testAssert(
    in_array('audit.log_file must be an absolute path', c2cpValidateConfig($invalidPaths), true),
    'blank audit path was accepted'
);
$invalidEndpoint = $config;
$invalidEndpoint['api_clients']['browser-205']['extensions']['205']['pjsip_endpoint']
    = "desk-205\r\nAction: Command";
testAssert(
    c2cpValidateConfig($invalidEndpoint) !== array(),
    'unsafe endpoint mapping was accepted'
);
testAssert(
    c2cpNormalizeDestination($config, '+30 210 756 3001') === '2107563001',
    'Greek country prefix normalization failed'
);
testAssert(
    c2cpNormalizeDestination($config, '0030-694-123-4567') === '6941234567',
    'international mobile normalization failed'
);
testAssert(
    c2cpNormalizeDestination($config, '123') === null,
    'disallowed destination was accepted'
);
testAssert(
    c2cpSanitizePageUrl('https://user:pass@example.com/ticket/1?q=secret#x')
        === 'https://example.com/ticket/1',
    'page URL sanitization failed'
);
testAssert(
    c2cpSanitizePageUrl('javascript:alert(1)') === null,
    'unsafe page URL scheme was accepted'
);

$client = c2cpAuthenticateClient($config, array(
    'apiId' => 'browser-205',
    'apiSecret' => 'fixture-secret-at-least-24-characters',
));
testAssert(is_array($client), 'valid API client authentication failed');
$record = c2cpExtensionRecord($client, '205', 'pjsip');
testAssert(
    is_array($record) && $record['endpoint'] === 'desk-205',
    'authorized PJSIP endpoint mapping failed'
);
testAssert(
    c2cpExtensionRecord($client, '205', 'sip') === null,
    'per-extension protocol restriction failed'
);

testAssert(c2cpRateLimit($config, 'fixture', 1, 60), 'first rate-limit event failed');
testAssert(!c2cpRateLimit($config, 'fixture', 1, 60), 'rate limit did not block second event');

foreach (glob($root . '/rate/*') ?: array() as $path) {
    unlink($path);
}
@unlink($root . '/log/audit.log');
rmdir($root . '/rate');
rmdir($root . '/log');
rmdir($root);

fwrite(STDOUT, "PASS public backend common tests\n");
