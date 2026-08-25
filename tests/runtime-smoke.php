<?php

declare(strict_types=1);

use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;

$root = dirname(__DIR__, 3);
if (!defined('FS_FOLDER')) {
    define('FS_FOLDER', $root);
}

require FS_FOLDER . '/vendor/autoload.php';
require FS_FOLDER . '/config.php';

date_default_timezone_set('Europe/Madrid');
Kernel::init();

$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(in_array('DocumentacionAPI', Plugins::enabled(), true), 'DocumentacionAPI is not enabled');

$jsonFile = FS_FOLDER . '/MyFiles/swagger/openapi.json';
$assert(is_file($jsonFile), 'MyFiles/swagger/openapi.json was not generated');

$storedSpec = is_file($jsonFile)
    ? json_decode((string)file_get_contents($jsonFile), true)
    : null;
$assert(is_array($storedSpec), 'Stored OpenAPI document is not valid JSON');
$assert(($storedSpec['openapi'] ?? null) === '3.0.0', 'Stored OpenAPI version is not 3.0.0');
$assert(count($storedSpec['paths'] ?? []) > 100, 'Stored OpenAPI document has too few paths');
$assert(($storedSpec['components']['securitySchemes']['ApiKeyAuth']['name'] ?? null) === 'token', 'API token security scheme is missing');

$generator = new \FacturaScripts\Dinamic\Lib\APIDocGenerator();
$runtimeSpec = $generator->generate();
$assert(count($runtimeSpec['paths'] ?? []) > 100, 'Runtime generator has too few paths');
$assert(count($runtimeSpec['components']['schemas'] ?? []) > 50, 'Runtime generator has too few schemas');
$assert(count($runtimeSpec['tags'] ?? []) === 2, 'Runtime generator tags are incomplete');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Runtime smoke passed: %d paths, %d schemas\n",
    count($runtimeSpec['paths']),
    count($runtimeSpec['components']['schemas'])
));
