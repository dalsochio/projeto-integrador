<?php

namespace App\Helpers;

class ColumnTypeMapper
{
    private static array $typeMap = [
        'text' => 'VARCHAR(255)',
        'textarea' => 'TEXT',
        'markdown' => 'TEXT',
        'wysiwyg' => 'TEXT',
        'number' => 'INT',
        'decimal' => 'DECIMAL(10,2)',
        'money' => 'DECIMAL(10,2)',
        'date' => 'DATE',
        'datetime' => 'DATETIME',
        'time' => 'TIME',
        'select' => 'VARCHAR(255)',
        'radio' => 'VARCHAR(255)',
        'checkbox' => 'TINYINT(1)',
        'toggle' => 'TINYINT(1)',
        'file' => 'VARCHAR(500)',
        'image' => 'VARCHAR(500)',
        'color' => 'VARCHAR(7)',
        'json' => 'JSON',
        'code' => 'TEXT',
        'email' => 'VARCHAR(255)',
        'url' => 'VARCHAR(500)',
        'phone' => 'VARCHAR(20)',
        'password' => 'VARCHAR(255)',
    ];
    
    public static function getSqlType(string $inputType): string
    {
        return self::$typeMap[$inputType] ?? 'VARCHAR(255)';
    }
    
    public static function buildColumnDefinition(array $field): string
    {
        $definition = "`{$field['name']}` ";
        
        if (!empty($field['type'])) {
            $sqlType = $field['type'];
            if (!empty($field['length'])) {
                $sqlType .= "({$field['length']})";
            } elseif (strtoupper($sqlType) === 'VARCHAR') {
                $sqlType .= "(255)";
            }
            $definition .= $sqlType;
        } else {
            $definition .= self::getSqlType($field['input_type'] ?? 'text');
        }
        
        if (!($field['is_nullable'] ?? true)) {
            $definition .= " NOT NULL";
        }
        
        if (isset($field['default_value']) && $field['default_value'] !== '') {
            $defaultValue = $field['default_value'];
            
            if (strtoupper($defaultValue) === 'CURRENT_TIMESTAMP') {
                $definition .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif (strtoupper($defaultValue) === 'NULL') {
                $definition .= " DEFAULT NULL";
            } else {
                $definition .= " DEFAULT '" . addslashes($defaultValue) . "'";
            }
        }
        
        if ($field['is_unique'] ?? false) {
            $definition .= " UNIQUE";
        }
        
        return $definition;
    }
    
    public static function buildAlterAddColumn(string $tableName, array $field): string
    {
        $columnDef = self::buildColumnDefinition($field);
        return "ALTER TABLE `{$tableName}` ADD COLUMN {$columnDef}";
    }
    
    public static function buildAlterModifyColumn(string $tableName, array $field): string
    {
        $columnDef = self::buildColumnDefinition($field);
        return "ALTER TABLE `{$tableName}` MODIFY COLUMN {$columnDef}";
    }
    
    public static function buildAlterDropColumn(string $tableName, string $columnName): string
    {
        return "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`";
    }
    
    public static function getSystemColumns(): array
    {
        return [
            [
                'name' => 'id',
                'input_type' => 'number',
                'is_nullable' => false,
                'is_primary' => true,
                'is_auto_increment' => true
            ],
            [
                'name' => 'created_at',
                'input_type' => 'datetime',
                'is_nullable' => false,
                'default_value' => 'CURRENT_TIMESTAMP'
            ],
            [
                'name' => 'updated_at',
                'input_type' => 'datetime',
                'is_nullable' => false,
                'default_value' => 'CURRENT_TIMESTAMP'
            ]
        ];
    }
    
    public static function buildCreateTableSql(string $tableName, array $fields): string
    {
        $columns = [];
        
        foreach ($fields as $field) {
            if ($field['name'] === 'id') {
                $columns[] = "`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
                continue;
            }
            
            $definition = self::buildColumnDefinition($field);
            $columns[] = $definition;
        }
        
        $hasCreatedAt = false;
        $hasUpdatedAt = false;
        $hasCreatedBy = false;
        $hasUpdatedBy = false;
        
        foreach ($fields as $field) {
            if ($field['name'] === 'created_at') $hasCreatedAt = true;
            if ($field['name'] === 'updated_at') $hasUpdatedAt = true;
            if ($field['name'] === 'created_by') $hasCreatedBy = true;
            if ($field['name'] === 'updated_by') $hasUpdatedBy = true;
        }
        
        if (!$hasCreatedAt) {
            $columns[] = "`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
        }
        
        if (!$hasCreatedBy) {
            $columns[] = "`created_by` INT(11) DEFAULT NULL";
        }
        
        if (!$hasUpdatedAt) {
            $columns[] = "`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        }
        
        if (!$hasUpdatedBy) {
            $columns[] = "`updated_by` INT(11) DEFAULT NULL";
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
        $sql .= "  " . implode(",\n  ", $columns);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        return $sql;
    }
}
