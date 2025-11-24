<?php

namespace App\Records;

class ConfigRecord extends AbstractRecord
{
    protected string $table = 'panel_config';

    public static function get(string $key, $default = null)
    {
        $record = new self();
        $record->equal('config_key', $key)->find();

        if (!$record->isHydrated()) {
            return $default;
        }

        return $record->config_value;
    }

    public static function set(string $key, string $value): void
    {
        $record = new self();
        $record->equal('config_key', $key)->find();

        if ($record->isHydrated()) {
            $record->config_value = $value;
            $record->update();
        } else {
            $record->config_key = $key;
            $record->config_value = $value;
            $record->insert();
        }
    }

    public static function all(): array
    {
        $record = new self();
        $rows = $record->findAll();

        $configs = [];
        foreach ($rows as $row) {
            $configs[$row->config_key] = $row->config_value;
        }

        return $configs;
    }
}
