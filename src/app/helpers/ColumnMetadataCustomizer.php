<?php

namespace App\Helpers;

class ColumnMetadataCustomizer
{
    
    public static function enhance(array $data): array
    {
        if (isset($data['tables'])) {
            foreach ($data['tables'] as &$table) {
                if (isset($table['columns'])) {
                    $table['columns'] = self::enhanceColumns($table['name'], $table['columns']);
                }
            }
        }
        elseif (isset($data['columns']) && isset($data['name'])) {
            $data['columns'] = self::enhanceColumns($data['name'], $data['columns']);
        }

        return $data;
    }

    private static function enhanceColumns($tableName, $columns): array
    {
        try {
            $database = $_ENV['DB_DATABASE'];
            $db = \Flight::db($database);

            $stmt = $db->prepare("
                SELECT COLUMN_NAME as name, COLUMN_COMMENT as comment
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :database
                  AND TABLE_NAME = :table
            ");

            $stmt->execute([
                'database' => $database,
                'table' => $tableName
            ]);

            $comments = [];
            while ($row = $stmt->fetch()) {
                $comments[$row['name']] = $row['comment'];
            }

            foreach ($columns as &$column) {
                $columnName = $column['name'];
                $comment = $comments[$columnName] ?? '';
                $column['ui'] = self::parseColumnComment($comment);
            }

            return $columns;
        } catch (\Exception $e) {
            return $columns;
        }
    }

    private static function parseColumnComment($comment): array
    {
        if (empty($comment)) {
            return [];
        }

        $metadata = [];
        $pairs = explode(';', $comment);

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif ($key === 'options') {
                    $exploded = explode(',', $value);
                    $trimmed = [];
                    foreach ($exploded as $item) {
                        $trimmed[] = trim($item);
                    }
                    $value = $trimmed;
                }

                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }
}
