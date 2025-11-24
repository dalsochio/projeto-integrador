<?php

namespace App\Helpers;

use App\Records\CategoryRecord;
use App\Records\TableRecord;
use Flight;

class MenuHelper
{
    
    public static function generateMenu(): array
    {
        $user = $_SESSION['user'] ?? [];
        if (empty($user['id'])) {
            return [];
        }

        $enforcer = Flight::casbin();
        $userId = 'user:' . $user['id'];
        $menu = [];

        $categoryRecord = new CategoryRecord();
        $categories = $categoryRecord
            ->equal('is_active', 1)
            ->orderBy('menu_order ASC, display_name ASC')
            ->findAll();

        foreach ($categories as $category) {
            $categoryArray = $category->toArray();

            $menu[$category->id] = [
                ...$categoryArray,
                'items' => [],
            ];
        }

        $tableRecord = new TableRecord();
        $tables = $tableRecord
            ->equal('is_active', 1)
            ->orderBy('menu_order ASC, display_name ASC')
            ->findAll();

        $orphanedModules = [];

        foreach ($tables as $table) {
            $tableArray = $table->toArray();

            $hasPermission = $enforcer->enforce($userId, $table->name, '*', 'read');

            if (!$hasPermission) {
                continue;
            }

            if ($table->category_id && array_key_exists($table->category_id, $menu)) {
                $menu[$table->category_id]['items'][] = $tableArray;
            } else {
                $orphanedModules[] = $tableArray;
            }
        }

        $filteredMenu = [];
        foreach ($menu as $category) {
            if (!empty($category['items'])) {
                $filteredMenu[] = $category;
            }
        }
        $menu = $filteredMenu;

        if (!empty($orphanedModules)) {
            $menu = [
                'orphaned' => [
                    'id' => null,
                    'name' => 'orphaned',
                    'display_name' => 'Outros',
                    'url_path' => '',
                    'icon' => 'folder_open',
                    'icon_type' => 'text',
                    'description' => 'Módulos sem categoria',
                    'is_active' => 1,
                    'menu_order' => -1,
                    'items' => $orphanedModules,
                ],
                ...$menu,
            ];
        }

        return $menu;
    }
}
