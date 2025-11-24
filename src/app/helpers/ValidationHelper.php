<?php

namespace App\Helpers;

use Flight;

class ValidationHelper
{
    
    public static function validate(array $fields, array $data): true|array
    {
        $errors = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'];

            if ($fieldName == 'id') continue;

            if (($field['input_type'] ?? '') === 'file') {
                $files = Flight::request()->files;
                $fileUploaded = isset($files[$fieldName]) && $files[$fieldName]['error'] !== UPLOAD_ERR_NO_FILE;

                if (!$field['is_nullable'] && !$fileUploaded) {
                    $displayName = $field['display_name'] ?? $fieldName;
                    $errors[$fieldName] = "{$displayName} é obrigatório";
                }

                if ($fileUploaded) {
                    $displayName = $field['display_name'] ?? $fieldName;

                    switch ($files[$fieldName]['error']) {
                        case UPLOAD_ERR_OK:
                            break;
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $errors[$fieldName] = "{$displayName}: Arquivo muito grande (máx: " . \App\Helpers\FileUploadHelper::getMaxSizeLabel() . ")";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $errors[$fieldName] = "{$displayName}: Upload incompleto, tente novamente";
                            break;
                        default:
                            $errors[$fieldName] = "{$displayName}: Erro no upload do arquivo";
                            break;
                    }
                }

                continue;
            }

            $value = $data[$fieldName] ?? null;

            if (!$field['is_nullable'] && self::isEmpty($value)) {
                $displayName = $field['display_name'] ?? $fieldName;
                $errors[$fieldName] = "{$displayName} é obrigatório";
                continue;
            }

            if (self::isEmpty($value)) {
                continue;
            }

            if (!empty($field['validation_rules'])) {
                $rules = json_decode($field['validation_rules'], true);
                $error = self::validateRules($fieldName, $value, $rules, $field);

                if ($error) {
                    $errors[$fieldName] = $error;
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    
    private static function isEmpty($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value)) {
            $stripped = strip_tags($value);
            $stripped = trim($stripped);
            if ($stripped === '') {
                return true;
            }
        }

        return false;
    }

    
    private static function validateRules(string $fieldName, $value, array $rules, array $field): ?string
    {
        $displayName = $field['display_name'] ?? $fieldName;

        if (isset($rules['min'])) {
            if (is_numeric($value) && $value < $rules['min']) {
                return "{$displayName} deve ser no mínimo {$rules['min']}";
            }
            if (is_string($value) && strlen($value) < $rules['min']) {
                return "{$displayName} deve ter no mínimo {$rules['min']} caracteres";
            }
        }

        if (isset($rules['max'])) {
            if (is_numeric($value) && $value > $rules['max']) {
                return "{$displayName} deve ser no máximo {$rules['max']}";
            }
            if (is_string($value) && strlen($value) > $rules['max']) {
                return "{$displayName} deve ter no máximo {$rules['max']} caracteres";
            }
        }

        if (isset($rules['regex'])) {
            if (!preg_match('/' . $rules['regex'] . '/', $value)) {
                return "{$displayName} está em formato inválido";
            }
        }

        return null;
    }
}
