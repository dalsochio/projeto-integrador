<?php

namespace App\Helpers;

use App\Controllers\ModulesController;
use App\Controllers\RolesController;
use App\Controllers\UserController;
use App\Controllers\CategoryController;
use App\Controllers\AuditController;
class InternalModuleMapper
{
    
    private static array $controllerMap = [
        'panel_table' => ModulesController::class,
        'panel_category' => CategoryController::class,
        'panel_role_info' => RolesController::class,
        'user' => UserController::class,
        'panel_log' => AuditController::class,
        'panel_config' => \App\Controllers\ConfigController::class,
    ];

    
    private static array $viewPathMap = [
        'panel_table' => 'page/panel/module',
        'panel_category' => 'page/panel/category',
        'panel_role_info' => 'page/panel/role',
        'user' => 'page/user',
        'panel_log' => 'page/panel/audit',
        'panel_config' => 'page/panel/config',
    ];

    
    public static function hasCustomController(string $tableName): bool
    {
        return isset(self::$controllerMap[$tableName]);
    }

    
    public static function getController(string $tableName): ?string
    {
        return self::$controllerMap[$tableName] ?? null;
    }

    
    public static function getViewPath(string $tableName): ?string
    {
        return self::$viewPathMap[$tableName] ?? null;
    }

    
    public static function register(string $tableName, string $controllerClass, string $viewPath): void
    {
        self::$controllerMap[$tableName] = $controllerClass;
        self::$viewPathMap[$tableName] = $viewPath;
    }

    
    public static function getAll(): array
    {
        $modules = [];
        foreach (self::$controllerMap as $tableName => $controller) {
            $modules[$tableName] = [
                'controller' => $controller,
                'viewPath' => self::$viewPathMap[$tableName] ?? null,
            ];
        }
        return $modules;
    }
}
