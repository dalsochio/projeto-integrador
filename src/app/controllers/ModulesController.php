<?php

namespace App\Controllers;

use App\Records\CategoryRecord;
use App\Records\ColumnRecord;
use App\Records\TableRecord;
use App\Services\ModuleService;
use flight\Engine;
use Flight;
use Exception;
use Cocur\Slugify\Slugify;

class ModulesController
{
    private Engine $flight;
    private Slugify $slugify;
    private ModuleService $moduleService;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
        $this->slugify = new Slugify(['separator' => '_', 'lowercase' => true]);
        $this->moduleService = new ModuleService();
    }

    public function index(): void
    {
        $tableRecord = new TableRecord();

        $modules = $tableRecord
            ->select('panel_table.*, panel_category.name as category_name, panel_category.display_name as category_display_name, panel_category.url_path as category_url_path')
            ->join('panel_category', 'panel_category.id = panel_table.category_id', 'LEFT')
            ->orderBy('panel_category.display_name ASC, panel_table.display_name ASC')
            ->findAllToArray();

        foreach ($modules as &$module) {
            $canDelete = $this->canDeleteModule($module);
            $module['can_delete'] = $canDelete['can_delete'];
            $module['delete_reason'] = $canDelete['reason'];
            $module['record_count'] = $canDelete['record_count'];
        }

        Flight::render('page/panel/module/index.latte', [
            'title' => 'Módulos',
            'modules' => $modules,
        ]);
    }

    public function show(string $id): void
    {
    }

    public function create(): void
    {
        $categoryRecord = new CategoryRecord();
        $categories = $categoryRecord->orderBy('display_name ASC')->findAllToArray();

        $tableRecord = new TableRecord();
        $availableModules = $tableRecord
            ->select('name, display_name')
            ->equal('internal', 0)
            ->orderBy('display_name ASC')
            ->findAllToArray();
        
        // Incluir tabela user (unica interna permitida para referencias)
        $userTable = $tableRecord->equal('name', 'user')->find();
        if ($userTable->isHydrated()) {
            $availableModules[] = [
                'name' => $userTable->name,
                'display_name' => $userTable->display_name
            ];
        }

        $userId = $_SESSION['user']['id'] ?? null;
        $isAdmin = \App\Helpers\CasbinHelper::userCan($userId, '*', '*', '*');

        Flight::render('page/panel/module/create.latte', [
            'title' => 'Novo módulo',
            'categories' => $categories,
            'availableModules' => $availableModules,
            'isAdmin' => $isAdmin
        ]);
    }

    public function store(): void
    {
        try {
            $data = Flight::request()->data->getData();
            
            if (empty($data['category_id']) || empty($data['name']) || empty($data['url'])) {
                Flight::json([
                    'success' => false,
                    'message' => 'Campos obrigatórios não preenchidos: categoria, nome e URL são necessários.'
                ], 400);
                return;
            }
            
            if (empty($data['fields']) || !is_array($data['fields']) || count($data['fields']) === 0) {
                Flight::json([
                    'success' => false,
                    'message' => 'É necessário adicionar pelo menos 1 campo ao módulo.'
                ], 400);
                return;
            }
            
            $categoryRecord = new CategoryRecord();
            $category = $categoryRecord->equal('id', (int)$data['category_id'])->find();
            
            if (!$category->isHydrated()) {
                Flight::json([
                    'success' => false,
                    'message' => 'Categoria não encontrada.'
                ], 404);
                return;
            }
            
            $tableName = $this->slugify->slugify($data['name']);
            
            $moduleData = [
                'category_id' => (int)$data['category_id'],
                'name' => $tableName,
                'display_name' => $data['name'],
                'url_path' => trim($data['url'], '/'),
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? 'table_chart',
                'is_active' => 1,
                'menu_order' => 0
            ];
            
            $fields = [];
            $foreignKeys = [];
            
            foreach ($data['fields'] as $field) {
                $isForeignKey = !empty($field['is_foreign_key']) || 
                               !empty($field['foreign_table']) && !empty($field['foreign_column']);

                if ($isForeignKey) {
                    $fieldSlug = $this->slugify->slugify($field['display_name']);

                    if (!empty($field['foreign_table'])) {
                        $field['name'] = $fieldSlug . '_' . $field['foreign_table'] . '_id';
                    } else {
                        $field['name'] = $fieldSlug;
                    }

                    if (!empty($field['foreign_table'])) {
                        $foreignKeys[] = [
                            'column' => $field['name'],
                            'foreign_table' => $field['foreign_table'],
                            'foreign_column' => 'id' // FK always references the primary key
                        ];
                    }

                    $inputType = $field['input_type'] ?? 'select';
                    if (!in_array($inputType, ['select', 'radio'])) {
                        $field['input_type'] = 'select';
                    }

                    $field['type'] = 'INT';
                    $field['length'] = '11';
                    $field['is_nullable'] = 1; // FK columns must be nullable for ON DELETE SET NULL
                } else {
                    $field['name'] = $this->slugify->slugify($field['display_name']);

                    if (in_array($field['input_type'] ?? '', ['select', 'radio', 'checkbox']) && 
                        !in_array($originalType, ['REFERENCE_SELECT', 'REFERENCE_RADIO'])) {
                        $manualOptions = $field['manual_options'] ?? [];
                        $useSeparateValue = $field['use_separate_value'] ?? false;

                        $options = [];
                        foreach ($manualOptions as $option) {
                            $options[] = [
                                'value' => $useSeparateValue ? ($option['value'] ?? $option['label']) : $option['label'],
                                'label' => $option['label']
                            ];
                        }

                        if (!empty($options)) {
                            $field['input_options'] = json_encode($options);
                        }
                    }

                    $typeMapping = [
                        'SELECT' => 'VARCHAR',
                        'RADIO' => 'VARCHAR',
                        'CHECKBOX' => 'TEXT',
                        'FILE' => 'VARCHAR',
                        'COLOR' => 'VARCHAR',
                        'TOGGLE' => 'TINYINT',
                        'WYSIWYG' => 'TEXT',
                        'MARKDOWN' => 'TEXT',
                        'CODE' => 'TEXT'
                    ];

                    if (isset($typeMapping[$field['type']])) {
                        $field['type'] = $typeMapping[$field['type']];

                        if (!isset($field['length']) || empty($field['length'])) {
                            if ($field['type'] === 'VARCHAR') {
                                $field['length'] = '255';
                            }
                        }
                    }
                }
                
                $fields[] = $field;
            }
            
            $moduleId = $this->moduleService->createModule($moduleData, $fields, $foreignKeys);
            
            \App\Helpers\AuditLogger::logFromResourceRoute('panel_table', 'create', $moduleId, $moduleData);
            
            $fullUrl = '/' . $category->url_path . '/' . trim($data['url'], '/');
            
            Flight::json([
                'success' => true,
                'message' => 'Módulo criado com sucesso!',
                'data' => [
                    'id' => $moduleId,
                    'name' => $tableName,
                    'display_name' => $data['name'],
                    'url' => $fullUrl
                ]
            ], 201);
            
        } catch (Exception $e) {
            $message = $e->getMessage();
            
            $prefix = 'Erro ao criar módulo: ';
            while (substr($message, 0, strlen($prefix)) === $prefix) {
                $message = substr($message, strlen($prefix));
            }
            
            if (strpos($message, 'Já existe') !== 0) {
                $message = $prefix . $message;
            }
            
            Flight::json([
                'success' => false,
                'message' => $message
            ], 500);
        }
    }

    
    private function createPhysicalTable(PDO $pdo, string $tableName, array $fields): void
    {
        $columns = [];

        $columns[] = "`id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY";

        foreach ($fields as $index => $field) {
            if (!isset($field['name'])) {
                throw new Exception("Field at index $index is missing 'name' key");
            }
            $columnDef = "`{$field['name']}` ";

            switch ($field['type']) {
                case 'VARCHAR':
                    $length = $field['length'] ?? '255';
                    $columnDef .= "VARCHAR($length)";
                    break;
                case 'TEXT':
                    $columnDef .= "TEXT";
                    break;
                case 'INT':
                case 'TINYINT':
                    $length = $field['length'] ?? '11';
                    $columnDef .= "{$field['type']}($length)";
                    break;
                case 'DECIMAL':
                    $length = $field['length'] ?? '10,2';
                    $columnDef .= "DECIMAL($length)";
                    break;
                case 'DATE':
                    $columnDef .= "DATE";
                    break;
                case 'DATETIME':
                    $columnDef .= "DATETIME";
                    break;
                case 'BOOLEAN':
                    $columnDef .= "TINYINT(1)";
                    break;
                case 'JSON':
                    $columnDef .= "JSON";
                    break;
                case 'ENUM':
                    $columnDef .= "VARCHAR(255)";
                    break;
                default:
                    $columnDef .= "VARCHAR(255)";
            }

            if (!empty($field['is_nullable'])) {
                $columnDef .= " NULL";
            } else {
                $columnDef .= " NOT NULL";
            }

            if (isset($field['default_value']) && $field['default_value'] !== '') {
                $columnDef .= " DEFAULT '{$field['default_value']}'";
            }

            $columns[] = $columnDef;
        }

        $columns[] = "`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
        $columns[] = "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

        $sql = "CREATE TABLE `$tableName` (\n    " . implode(",\n    ", $columns) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
    }

    public function edit(string $id): void
    {
        $tableRecord = new TableRecord();
        $module = $tableRecord
            ->select('panel_table.*, panel_category.name as category_name, panel_category.display_name as category_display_name, panel_category.url_path as category_url_path')
            ->join('panel_category', 'panel_category.id = panel_table.category_id', 'LEFT')
            ->equal('id', (int)$id)
            ->find();

        if (empty($module->id)) {
            Flight::redirect('/panel/module');
            return;
        }

        $categoryRecord = new CategoryRecord();
        $categories = $categoryRecord->orderBy('display_name ASC')->findAllToArray();

        $columnRecord = new ColumnRecord();
        $columns = $columnRecord
            ->equal('table_id', (int)$id)
            ->orderBy('position ASC')
            ->findAllToArray();
        
        // Carregar modulos disponiveis para referencias
        $tableRecordForModules = new TableRecord();
        $availableModules = $tableRecordForModules
            ->select('name, display_name')
            ->equal('internal', 0)
            ->orderBy('display_name ASC')
            ->findAllToArray();
        
        // Incluir tabela user (unica interna permitida para referencias)
        $userTable = $tableRecordForModules->equal('name', 'user')->find();
        if ($userTable->isHydrated()) {
            $availableModules[] = [
                'name' => $userTable->name,
                'display_name' => $userTable->display_name
            ];
        }

        $createdByUser = null;
        $updatedByUser = null;

        if (!empty($module->created_by)) {
            $userRecord = new \App\Records\UserRecord();
            $createdByUser = $userRecord->equal('id', (int)$module->created_by)->find();
        }

        if (!empty($module->updated_by)) {
            $userRecord = new \App\Records\UserRecord();
            $updatedByUser = $userRecord->equal('id', (int)$module->updated_by)->find();
        }

        Flight::render('page/panel/module/edit.latte', [
            'title' => 'Editar módulo: ' . $module->display_name,
            'module' => $module->toArray(),
            'categories' => $categories,
            'columns' => $columns,
            'availableModules' => $availableModules,
            'createdByUser' => $createdByUser ? $createdByUser->toArray() : null,
            'updatedByUser' => $updatedByUser ? $updatedByUser->toArray() : null
        ]);
    }

    public function update(string $id): void
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (empty($data['category_id']) || empty($data['name']) || empty($data['url'])) {
                Flight::json([
                    'success' => false,
                    'message' => 'Campos obrigatórios não preenchidos: categoria, nome e URL são necessários.'
                ], 400);
                return;
            }

            $tableRecord = new TableRecord();
            $module = $tableRecord->equal('id', (int)$id)->find();

            if (empty($module->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Módulo não encontrado.'
                ], 404);
                return;
            }

            if ($module->internal) {
                if ((int)$data['category_id'] !== (int)$module->category_id || trim($data['url'], '/') !== $module->url_path) {
                    Flight::json([
                        'success' => false,
                        'message' => 'Módulos internos não podem ter categoria ou URL alterados.'
                    ], 403);
                    return;
                }
            }

            $categoryRecord = new CategoryRecord();
            $category = $categoryRecord
                ->equal('id', (int)$data['category_id'])
                ->find();

            if (empty($category->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Categoria não encontrada.'
                ], 404);
                return;
            }

            if (!$module->internal) {
                $module->category_id = (int)$category->id;
                $module->url_path = trim($data['url'], '/');
            }
            
            $module->display_name = $data['name'];
            $module->description = $data['description'] ?? null;
            $module->icon = $data['icon'] ?? 'table_chart';
            $module->is_active = $data['is_active'] ? 1 : 0;
            $module->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_table', 'update', $id, $data);

            $fullUrl = '/' . $category->url_path . '/' . trim($data['url'], '/');

            Flight::json([
                'success' => true,
                'message' => 'Módulo atualizado com sucesso!',
                'data' => [
                    'id' => $module->id,
                    'display_name' => $module->display_name,
                    'url' => $fullUrl
                ]
            ], 200);

        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao atualizar módulo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFields(string $id): void
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (empty($data['fields']) || !is_array($data['fields'])) {
                Flight::json([
                    'success' => false,
                    'message' => 'Dados inválidos.'
                ], 400);
                return;
            }

            $moduleId = (int)$id;
            
            $tableRecord = new TableRecord();
            $module = $tableRecord->equal('id', $moduleId)->find();

            if (empty($module->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Módulo não encontrado.'
                ], 404);
                return;
            }

            $columnRecord = new ColumnRecord();
            $systemFields = ['id', 'created_at', 'created_by', 'updated_at', 'updated_by'];
            $currentColumns = $columnRecord
                ->equal('table_id', $moduleId)
                ->findAllToArray();

            $currentUserColumns = [];
            foreach ($currentColumns as $col) {
                if (!in_array($col['name'], $systemFields)) {
                    $currentUserColumns[] = $col;
                }
            }

            $allFieldIds = array_column($data['fields'], 'id');
            $frontendFieldIds = [];
            foreach ($allFieldIds as $fieldId) {
                if (!empty($fieldId)) {
                    $frontendFieldIds[] = $fieldId;
                }
            }

            $deletedCount = 0;
            foreach ($currentUserColumns as $currentCol) {
                if (!in_array($currentCol['id'], $frontendFieldIds)) {
                    $colToDelete = new ColumnRecord();
                    $colToDelete->id = $currentCol['id'];
                    $colToDelete->delete();
                    $deletedCount++;
                }
            }

            $updatedCount = 0;
            $createdCount = 0;

            foreach ($data['fields'] as $index => $fieldData) {
                $columnRecord = new ColumnRecord();

                if (!empty($fieldData['id'])) {
                    $column = $columnRecord->equal('id', (int)$fieldData['id'])->find();

                    if (!empty($column->id)) {
                        $column->display_name = $fieldData['display_name'] ?? $column->display_name;
                        if (isset($fieldData['display_name']) && $fieldData['display_name'] !== $column->display_name) {
                            $column->name = $this->slugify->slugify($fieldData['display_name']);
                        }
                        $column->column_size = $fieldData['column_size'] ?? $column->column_size ?? 12;
                        $column->row_index = $fieldData['row_index'] ?? $column->row_index ?? null;
                        $column->row_size = $fieldData['row_size'] ?? $column->row_size ?? 1;
                        $column->position = $fieldData['position'] ?? $index;
                        $column->input_type = $fieldData['input_type'] ?? $column->input_type;
                        $column->input_placeholder = $fieldData['input_placeholder'] ?? null;
                        $column->input_prefix = $fieldData['input_prefix'] ?? null;
                        $column->input_suffix = $fieldData['input_suffix'] ?? null;
                        $column->help_text = $fieldData['help_text'] ?? null;
                        $column->is_visible_list = !empty($fieldData['is_visible_list']) ? 1 : 0;
                        $column->is_visible_form = !empty($fieldData['is_visible_form']) ? 1 : 0;
                        $column->is_editable = !empty($fieldData['is_editable']) ? 1 : 0;
                        $column->is_searchable = !empty($fieldData['is_searchable']) ? 1 : 0;
                        $column->is_nullable = !empty($fieldData['is_nullable']) ? 1 : 0;
                        $column->is_unique = !empty($fieldData['is_unique']) ? 1 : 0;

                        $column->save();
                        $updatedCount++;
                    }
                } else {
                    $fieldData['name'] = $this->slugify->slugify($fieldData['display_name']);
                    
                    $newColumn = new ColumnRecord();
                    $newColumn->table_id = $moduleId;
                    $newColumn->name = $fieldData['name'];
                    $newColumn->display_name = $fieldData['display_name'];
                    $newColumn->type = $fieldData['type'];
                    $newColumn->length = $fieldData['length'] ?? null;
                    $newColumn->is_nullable = !empty($fieldData['is_nullable']) ? 1 : 0;
                    $newColumn->default_value = $fieldData['default_value'] ?? null;
                    $newColumn->is_unique = !empty($fieldData['is_unique']) ? 1 : 0;
                    $newColumn->is_primary = 0;
                    $newColumn->auto_increment = 0;
                    $newColumn->is_visible_list = !empty($fieldData['is_visible_list']) ? 1 : 0;
                    $newColumn->is_visible_form = !empty($fieldData['is_visible_form']) ? 1 : 0;
                    $newColumn->is_visible_detail = 1;
                    $newColumn->is_editable = !empty($fieldData['is_editable']) ? 1 : 0;
                    $newColumn->is_searchable = !empty($fieldData['is_searchable']) ? 1 : 0;
                    $newColumn->is_sortable = 1;
                    $newColumn->is_filterable = 0;
                    $newColumn->input_type = $fieldData['input_type'] ?? 'text';
                    $newColumn->input_placeholder = $fieldData['input_placeholder'] ?? null;
                    $newColumn->input_prefix = $fieldData['input_prefix'] ?? null;
                    $newColumn->input_suffix = $fieldData['input_suffix'] ?? null;
                    $newColumn->help_text = $fieldData['help_text'] ?? null;
                    $newColumn->position = $fieldData['position'] ?? $index;
                    $newColumn->column_size = $fieldData['column_size'] ?? 12;
                    $newColumn->row_index = $fieldData['row_index'] ?? null;
                    $newColumn->row_size = $fieldData['row_size'] ?? 1;

                    $newColumn->save();
                    $createdCount++;
                }
            }

            foreach ($data['fields'] as &$field) {
                if (empty($field['id']) && !isset($field['name'])) {
                    $field['name'] = $this->slugify->slugify($field['display_name']);
                }
            }
            unset($field);

            $pdo = Flight::db();
            $this->syncPhysicalTableColumns($pdo, $module->name, $data['fields'], $currentUserColumns);

            $message = [];
            if ($createdCount > 0) $message[] = "$createdCount campo(s) criado(s)";
            if ($updatedCount > 0) $message[] = "$updatedCount campo(s) atualizado(s)";
            if ($deletedCount > 0) $message[] = "$deletedCount campo(s) deletado(s)";

            Flight::json([
                'success' => true,
                'message' => 'Campos salvos com sucesso! ' . implode(', ', $message),
                'data' => [
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'deleted_count' => $deletedCount
                ]
            ], 200);

        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao atualizar campos: ' . $e->getMessage()
            ], 500);
        }
    }

    
    private function syncPhysicalTableColumns($pdo, string $tableName, array $newFields, array $oldFields): void
    {
        $oldFieldsMap = [];
        foreach ($oldFields as $field) {
            $oldFieldsMap[$field['name']] = $field;
        }

        $newFieldsMap = [];
        foreach ($newFields as $field) {
            if (isset($field['name'])) {
                $newFieldsMap[$field['name']] = $field;
            }
        }

        foreach ($oldFieldsMap as $oldFieldName => $oldField) {
            if (!isset($newFieldsMap[$oldFieldName])) {
                try {
                    $sql = "ALTER TABLE `{$tableName}` DROP COLUMN `{$oldFieldName}`";
                    $pdo->exec($sql);
                } catch (Exception $e) {
                }
            }
        }

        foreach ($newFields as $newField) {
            $fieldName = $newField['name'];

            $columnExists = isset($oldFieldsMap[$fieldName]);

            if (!$columnExists) {
                try {
                    $columnDef = $this->buildColumnDefinition($newField);
                    $sql = "ALTER TABLE `{$tableName}` ADD COLUMN {$columnDef}";
                    $pdo->exec($sql);
                } catch (Exception $e) {
                }
            } else {
                $oldField = $oldFieldsMap[$fieldName];

                $typeChanged = ($oldField['type'] ?? '') !== ($newField['type'] ?? '');
                $lengthChanged = ($oldField['length'] ?? '') !== ($newField['length'] ?? '');
                $nullableChanged = ($oldField['is_nullable'] ?? 0) !== ($newField['is_nullable'] ?? 0);

                if ($typeChanged || $lengthChanged || $nullableChanged) {
                    try {
                        $columnDef = $this->buildColumnDefinition($newField);
                        $sql = "ALTER TABLE `{$tableName}` MODIFY COLUMN {$columnDef}";
                        $pdo->exec($sql);
                    } catch (Exception $e) {
                    }
                }
            }
        }
    }

    
    private function buildColumnDefinition(array $field): string
    {
        $columnDef = "`{$field['name']}` ";

        switch ($field['type']) {
            case 'VARCHAR':
                $length = $field['length'] ?? '255';
                $columnDef .= "VARCHAR($length)";
                break;
            case 'TEXT':
                $columnDef .= "TEXT";
                break;
            case 'INT':
            case 'TINYINT':
                $length = $field['length'] ?? '11';
                $columnDef .= "{$field['type']}($length)";
                break;
            case 'DECIMAL':
                $length = $field['length'] ?? '10,2';
                $columnDef .= "DECIMAL($length)";
                break;
            case 'DATE':
                $columnDef .= "DATE";
                break;
            case 'DATETIME':
                $columnDef .= "DATETIME";
                break;
            case 'BOOLEAN':
                $columnDef .= "TINYINT(1)";
                break;
            case 'JSON':
                $columnDef .= "JSON";
                break;
            case 'ENUM':
                $columnDef .= "VARCHAR(255)";
                break;
            default:
                $columnDef .= "VARCHAR(255)";
        }

        if (!empty($field['is_nullable'])) {
            $columnDef .= " NULL";
        } else {
            $columnDef .= " NOT NULL";
        }

        if (isset($field['default_value']) && $field['default_value'] !== '' && $field['default_value'] !== null) {
            $columnDef .= " DEFAULT '{$field['default_value']}'";
        }

        return $columnDef;
    }

    
    private function canDeleteModule(array $module): array
    {
        if ($module['internal'] ?? false) {
            return [
                'can_delete' => false,
                'reason' => 'Módulo interno não pode ser excluído',
                'record_count' => 0
            ];
        }
        
        try {
            $result = $this->moduleService->canDeleteModule($module['id']);
            
            return [
                'can_delete' => $result['canDelete'],
                'reason' => $result['reason'] ?? '',
                'record_count' => 0
            ];
        } catch (Exception $e) {
            return [
                'can_delete' => false,
                'reason' => 'Erro ao verificar registros do módulo',
                'record_count' => 0
            ];
        }
    }

    public function destroy(string $id): void
    {
        try {
            $tableRecord = new TableRecord();
            $module = $tableRecord->equal('id', (int)$id)->find();

            if (!$module->isHydrated()) {
                Flight::json([
                    'success' => false,
                    'message' => 'Módulo não encontrado.'
                ], 404);
                return;
            }

            if ($module->internal) {
                Flight::json([
                    'success' => false,
                    'message' => 'Módulos internos não podem ser deletados.'
                ], 403);
                return;
            }

            $deleted = $this->moduleService->deleteModule((int)$id);
            
            if ($deleted) {
                \App\Helpers\AuditLogger::logFromResourceRoute('panel_table', 'delete', $id, null);
                Flight::json([
                    'success' => true,
                    'message' => 'Módulo deletado com sucesso'
                ], 200);
            } else {
                Flight::json([
                    'success' => false,
                    'message' => 'Erro ao deletar módulo'
                ], 500);
            }
            
        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getModuleColumns(string $moduleName): void
    {
        try {
            $tableRecord = new TableRecord();
            $module = $tableRecord
                ->equal('name', $moduleName)
                ->find();
            
            if (!$module->isHydrated()) {
                Flight::json([
                    'success' => false,
                    'message' => 'Módulo não encontrado',
                    'columns' => []
                ], 404);
                return;
            }

            $columnRecord = new ColumnRecord();
            $columns = $columnRecord
                ->equal('table_id', $module->id)
                ->select('name, display_name, type')
                ->orderBy('display_name ASC')
                ->findAllToArray();
            
            $excludedColumns = ['id', 'password', 'created_at', 'created_by', 'updated_at', 'updated_by'];
            
            $columns = array_filter($columns, function($col) use ($excludedColumns) {
                return !in_array($col['name'], $excludedColumns);
            });
            
            $columns = array_values($columns);
            
            Flight::json([
                'success' => true,
                'columns' => $columns
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao buscar colunas: ' . $e->getMessage(),
                'columns' => []
            ], 500);
        }
    }
}
