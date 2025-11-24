<?php

namespace App\Helpers;

use App\Records\ConfigRecord;

class ConfigHelper
{
    private static ?array $cache = null;

    public static function get(string $key, $default = null)
    {
        if (self::$cache === null) {
            self::loadConfigs();
        }

        return self::$cache[$key] ?? $default;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            self::loadConfigs();
        }

        return self::$cache ?? [];
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    private static function loadConfigs(): void
    {
        self::$cache = ConfigRecord::all();
    }
}
