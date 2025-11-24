<?php

namespace App\Configs;

use App\Helpers\ApiHelper;
use App\Helpers\CasbinHelper;
use App\Helpers\FlashMessage;
use App\Helpers\PdoDebuggerWrapper;
use App\Helpers\PdoWrapper;
use Flight;
use Overclokk\Cookie\Cookie;
use Rollbar\Rollbar;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../', null, true);
$dotenv->load();

$app = Flight::app();
$app->set('flight.base_url', '/');
$app->set('flight.log_errors', true);
$app->set('flight.handle_errors', true);
$app->set('flight.view.path', __DIR__ . '/../views');

try {
    $app->register('cookie', Cookie::class);

    if ($_ENV['APP_ENV'] === 'development') {
        $app->register('db', PdoDebuggerWrapper::class);
    } else {
        $app->register('db', PdoWrapper::class);
    }

    $app->register('casbin', CasbinHelper::class);
    $app->register('api', ApiHelper::class);
    $app->register('flash', FlashMessage::class);

    Rollbar::init([
        'access_token' => $_ENV['ROLLBAR_TOKEN'],
        'environment' => $_ENV['APP_ENV'],
        'root' => __DIR__ . '/../../',
    ]);
} catch (\Exception $e) {
    throw new \Exception('Flight register errors: ' . $e->getMessage());
}
