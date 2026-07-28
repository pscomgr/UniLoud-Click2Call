#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/common.php';

$path = getenv('C2C_PUBLIC_CONFIG');
if (!is_string($path) || $path === '') {
    $path = '/etc/uniloud-click2call-public/config.php';
}
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Configuration is missing or unreadable: {$path}\n");
    exit(1);
}
$config = require $path;
if (!is_array($config)) {
    fwrite(STDERR, "Configuration did not return an array.\n");
    exit(1);
}
$errors = c2cpValidateConfig($config);
if ($errors !== array()) {
    foreach ($errors as $error) {
        fwrite(STDERR, "FAIL {$error}\n");
    }
    exit(1);
}
fwrite(STDOUT, "PASS public backend configuration\n");
