<?php
declare(strict_types=1);

$autoloadCandidates = [
    __DIR__.'/vendor/autoload.php',
    dirname(__DIR__).'/vendor/autoload.php',
];
$autoload = null;
foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    fwrite(STDERR, "Missing Composer dependencies. Reinstall the Local Agent package.\n");
    exit(1);
}
require $autoload;

foreach ([
    'AgentState',
    'ApiException',
    'ApiClient',
    'ZKTecoReader',
    'Logger',
    'AttendanceLogNormalizer',
    'DeviceUserNormalizer',
    'DeviceAttendanceIdentity',
    'AgentRunner',
] as $f) {
    require __DIR__.'/src/'.$f.'.php';
}

use LocalAttendanceAgent\AgentRunner;
use LocalAttendanceAgent\AgentState;
use LocalAttendanceAgent\ApiClient;
use LocalAttendanceAgent\AttendanceLogNormalizer;
use LocalAttendanceAgent\DeviceAttendanceIdentity;
use LocalAttendanceAgent\DeviceUserNormalizer;
use LocalAttendanceAgent\Logger;
use LocalAttendanceAgent\ZKTecoReader;

$configPath = __DIR__.'/config.json';
if (! is_file($configPath)) {
    fwrite(STDERR, "Missing Local Agent provisioning config. Run the installer/provisioning step first.\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
foreach (['api_base_url', 'api_token'] as $required) {
    if (empty($config[$required])) {
        throw new RuntimeException("Missing config: {$required}");
    }
}

$deviceTimezoneName = (string) ($config['device_timezone'] ?? 'Asia/Karachi');
try {
    $deviceTimezone = new DateTimeZone($deviceTimezoneName);
} catch (Throwable $e) {
    throw new RuntimeException('Invalid device timezone. Use an IANA timezone such as Asia/Karachi.');
}

$state = new AgentState(__DIR__.'/state.sqlite');
$api = new ApiClient($config);
$reader = new ZKTecoReader($config);
$log = new Logger(__DIR__.'/logs');
$runner = new AgentRunner(
    $config,
    $state,
    $api,
    $reader,
    $log,
    new AttendanceLogNormalizer($log, (int) ($config['future_skew_seconds'] ?? 300), $deviceTimezone),
    new DeviceUserNormalizer($log),
    new DeviceAttendanceIdentity(),
);

$mode = $argv[1] ?? '--once';

try {
    if ($mode === '--run') {
        $runner->runContinuously();
        exit(0);
    }

    if ($mode === '--cleanup-dry-run') {
        echo json_encode($runner->cleanupDryRun(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit(0);
    }

    if ($mode !== '--once') {
        fwrite(STDERR, "Usage: php agent.php [--once|--run|--cleanup-dry-run]\n");
        exit(1);
    }

    $runner->runOnce();
    exit(0);
} catch (Throwable $e) {
    $log->error($e->getMessage());
    fwrite(STDERR, '['.date('c').'] '.$e->getMessage().PHP_EOL);
    exit(1);
}
