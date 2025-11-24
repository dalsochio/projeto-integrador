<?php

use App\Helpers\RollbarTracyLogger;
use flight\debug\tracy\TracyExtensionLoader;
use Tracy\Debugger;

$debuggerMode = Debugger::Production;

if (in_array($_ENV['APP_ENV'], ['development', 'staging'])) {
    $debuggerMode = Debugger::Development;
}

Debugger::enable($debuggerMode);

$logger = Debugger::getLogger();
$rollbarLogger = new RollbarTracyLogger($logger);
Debugger::setLogger($rollbarLogger);

Debugger::$logDirectory = __DIR__ . '/../storage/log';
Debugger::$strictMode = true;

if (Debugger::$showBar && php_sapi_name() !== 'cli') {
    Flight::set('flight.content_length', false);

    try {
        new TracyExtensionLoader(Flight::app(), ['session_data' => $_SESSION]);
    } catch (\Exception $e) {
        throw new \Exception('Tracy extension loader errors: ' . $e->getMessage());
    }
}
