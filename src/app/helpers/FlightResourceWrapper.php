<?php

namespace App\Helpers;

use Flight;

class FlightResourceWrapper
{
    
    public static function register(string $pathPrefix = '/@category/@resource', string $controllerClass, array $middlewares = []): void
    {
        $route = Flight::route('GET ' . $pathPrefix, [$controllerClass, 'index']);
        self::applyMiddlewares($route, $middlewares);

        $route = Flight::route('GET ' . $pathPrefix . '/create', [$controllerClass, 'create']);
        self::applyMiddlewares($route, $middlewares);

        $route = Flight::route('POST ' . $pathPrefix, [$controllerClass, 'store']);
        self::applyMiddlewares($route, $middlewares);

        $route = Flight::route('GET ' . $pathPrefix . '/@id:[0-9]+', [$controllerClass, 'show']);
        self::applyMiddlewares($route, $middlewares);

        $route = Flight::route('GET ' . $pathPrefix . '/@id:[0-9]+/edit', [$controllerClass, 'edit']);
        self::applyMiddlewares($route, $middlewares);

        $route = Flight::route('POST ' . $pathPrefix . '/@id:[0-9]+', [$controllerClass, 'update']);
        self::applyMiddlewares($route, $middlewares);

        $route = Flight::route('DELETE ' . $pathPrefix . '/@id:[0-9]+', [$controllerClass, 'destroy']);
        self::applyMiddlewares($route, $middlewares);
    }

    
    private static function applyMiddlewares($route, array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $route->addMiddleware($middleware);
        }
    }
}
