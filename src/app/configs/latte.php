<?php

namespace App\Configs;

use App\Helpers\MenuHelper;
use App\Helpers\Utility;
use Dom\HTMLDocument;
use Latte\Bridges\Tracy\TracyExtension as LatteTracyExtension;
use Latte\Engine as LatteEngine;
use Latte\Essential\RawPhpExtension;
use Latte\Loaders\FileLoader as LatteFileLoader;
use Flight;

Flight::app()->register('latte', LatteEngine::class, [], function (LatteEngine $latte) {
    $latte->setTempDirectory(__DIR__ . '/../storage/temp');
    $latte->setAutoRefresh(true);

    $latte->setLoader(new LatteFileLoader(Flight::get('flight.view.path')));

    $latte->addExtension(new LatteTracyExtension());

    $latte->addExtension(new RawPhpExtension());

    $latte->addFunction('Utility', function (string $method, ...$args) {
        if (method_exists(Utility::class, $method)) {
            return Utility::$method(...$args);
        }
        throw new \Exception("Method '{$method}' not exists!");
    });

    $latte->addFunction('generateMenu', function () {
        return MenuHelper::generateMenu();
    });

    $latte->addFilter('json', function ($value) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
});

Flight::app()->map('render', function ($templatePath, $templateData = [], $triggers = []) {
    $latte = Flight::latte();
    
    $env = $_ENV;
    $env['config'] = \App\Helpers\ConfigHelper::all();
    
    $templateData = array_merge([
        'env' => $env,
        'user' => $_SESSION['user'] ?? null
    ], $templateData);

    if (empty($templatePath) && isset($templateData['htmx'])) {
        $htmxData = $templateData['htmx'];

        if (isset($htmxData['triggers'])) {
            header('HX-Trigger: ' . json_encode($htmxData['triggers']));
        }

        if ($htmxData['oob'] ?? false) {
            echo '';
            return;
        }
    }

    echo $latte->renderToString($templatePath, $templateData);
});
