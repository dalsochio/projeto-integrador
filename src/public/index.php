<?php

$days = 1;
$duration = $days * 24 * 60 * 60;

session_set_cookie_params([
    'lifetime' => $duration,
    'path' => '/',
    'secure' => true,
    'httponly' => true
]);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

Flight::start();
