<?php

namespace App\Services;

use App\Records\TableRecord;
use App\Records\ColumnRecord;
use App\Records\TableFormRecord;
use App\Helpers\ColumnTypeMapper;
use Flight;
use Exception;

class ModuleService
{
    private $db;
    
    public function __construct()
    {
        $this->db = Flight::db();
    }
    
    public function createModuleWithForm(array $moduleData, array $formBuilderData, array $foreignKeys = []): int
    {
        try {
            $existingModule = new TableRecord();
            $existingModule = $existingModule->equal('name', $moduleData['name'])->find();
            
            if ($existingModule->isHydrated()) {
                throw new Exception("Já existe um módulo com o nome '{$moduleData['name']}'. Por favor, escolha outro nome.");
            }
            
            $existingUrl = new TableRecord();
            $existingUrl = $existingUrl->equal('url_path', $moduleData['url_path'])->find();
            
            if ($existingUrl->isHydrated()) {
                throw new Exception("Já existe um módulo com a URL '{$moduleData['url_path']}'. Por favor, escolha outra URL.");
            }
            
            // Criar módulo
            $tableRecord = new TableRecord();
            $tableRecord->category_id = $moduleData['category_id'];
            $tableRecord->name = $moduleData['name'];
            $tableRecord->display_name = $moduleData['display_name'];
            $tableRecord->url_path = $moduleData['url_path'];
            $tableRecord->icon = $moduleData['icon'] ?? null;
            $tableRecord->description = $moduleData['description'] ?? null;
            $tableRecord->is_active = $moduleData['is_active'] ?? 1;
            $tableRecord->internal = 0;
            $tableRecord->menu_order = $moduleData['menu_order'] ?? 0;
            $tableRecord->created_by = $_SESSION['user']['id'] ?? null;
            $tableRecord->updated_by = $_SESSION['user']['id'] ?? null;
            $tableRecord->insert();
            
            $moduleId = $tableRecord->id;
            
            // Criar colunas de sistema
            $systemColumns = $this->getSystemColumns();
            foreach ($systemColumns as $column) {
                $columnRecord = new ColumnRecord();
                $columnRecord->table_id = $moduleId;
                $columnRecord->name = $column['name'];
                $columnRecord->display_name = $column['display_name'];
                $columnRecord->type = $column['type'];
                $columnRecord->is_nullable = $column['is_nullable'];
                $columnRecord->is_unique = $column['is_unique'] ?? 0;
                $columnRecord->is_editable = 0;
                $columnRecord->is_visible_list = $column['is_visible_list'] ?? 0;
                $columnRecord->is_visible_form = 0;
                $columnRecord->is_visible_detail = 1;
                $columnRecord->insert();
            }
            
            // Separar campos e divisores para criar tabela física só com campos
            $fields = [];
            foreach ($formBuilderData as $item) {
                $isDivider = isset($item['itemType']) && $item['itemType'] === 'divider';
                if (!$isDivider && !empty($item['display_name'])) {
                    $fields[] = $item;
                }
            }
            
            // Processar todos os itens na ordem (campos + divisores)
            foreach ($formBuilderData as $item) {
                $isDivider = isset($item['itemType']) && $item['itemType'] === 'divider';
                
                if ($isDivider) {
                    // Divisor: salvar só em panel_table_form com column_id = null
                    $formRecord = new TableFormRecord();
                    $formRecord->table_id = $moduleId;
                    $formRecord->column_id = null;
                    $formRecord->component_type = $item['divider_type'] === 'horizontal' 
                        ? 'divider_horizontal' 
                        : 'divider_vertical';
                    $formRecord->row_index = $item['row_index'] ?? 0;
                    $formRecord->row_size = $item['row_size'] ?? 1;
                    $formRecord->column_size = $item['column_size'] ?? 12;
                    $formRecord->position = $item['position'] ?? 0;
                    $formRecord->config = json_encode([
                        'text' => $item['divider_text'] ?? '',
                        'color' => $item['divider_color'] ?? '',
                        'align' => $item['divider_align'] ?? ''
                    ]);
                    $formRecord->insert();
                } elseif (!empty($item['display_name'])) {
                    // Campo: salvar em panel_column + panel_table_form
                    $columnRecord = new ColumnRecord();
                    $columnRecord->table_id = $moduleId;
                    $columnRecord->name = $item['name'];
                    $columnRecord->display_name = $item['display_name'];
                    $columnRecord->type = $item['type'];
                    $columnRecord->length = $item['length'] ?? null;
                    $columnRecord->is_nullable = $item['is_nullable'] ?? 1;
                    $columnRecord->is_unique = $item['is_unique'] ?? 0;
                    $columnRecord->is_editable = empty($item['is_readonly']) ? 1 : 0;
                    $columnRecord->is_visible_list = $item['is_visible_list'] ?? 1;
                    $columnRecord->is_visible_form = $item['is_visible_form'] ?? 1;
                    $columnRecord->is_visible_detail = 1;
                    
                    if (!empty($item['foreign_table'])) {
                        $columnRecord->foreign_table = $item['foreign_table'];
                    }
                    if (!empty($item['foreign_column'])) {
                        $columnRecord->foreign_column = $item['foreign_column'];
                    }
                    if (!empty($item['default_value'])) {
                        $columnRecord->default_value = $item['default_value'];
                    }
                    if (!empty($item['display_format'])) {
                        $columnRecord->display_format = $item['display_format'];
                    }
                    if (!empty($item['list_template'])) {
                        $columnRecord->list_template = $item['list_template'];
                    }
                    
                    $columnRecord->insert();
                    
                    // Salvar em panel_table_form
                    $formRecord = new TableFormRecord();
                    $formRecord->table_id = $moduleId;
                    $formRecord->column_id = $columnRecord->id;
                    $formRecord->component_type = 'field';
                    $formRecord->row_index = $item['row_index'] ?? 0;
                    $formRecord->row_size = $item['row_size'] ?? 1;
                    $formRecord->column_size = $item['column_size'] ?? 12;
                    $formRecord->position = $item['position'] ?? 0;
                    $formRecord->input_type = $item['input_type'] ?? 'text';
                    
                    if (!empty($item['input_options'])) {
                        $formRecord->input_options = $item['input_options'];
                    }
                    if (!empty($item['input_placeholder'])) {
                        $formRecord->input_placeholder = $item['input_placeholder'];
                    }
                    if (!empty($item['input_prefix'])) {
                        $formRecord->input_prefix = $item['input_prefix'];
                    }
                    if (!empty($item['input_suffix'])) {
                        $formRecord->input_suffix = $item['input_suffix'];
                    }
                    if (!empty($item['input_mask'])) {
                        $formRecord->input_mask = $item['input_mask'];
                    }
                    if (!empty($item['validation_rules'])) {
                        $formRecord->validation_rules = is_string($item['validation_rules']) 
                            ? $item['validation_rules'] 
                            : json_encode($item['validation_rules']);
                    }
                    if (!empty($item['validation_message'])) {
                        $formRecord->validation_message = is_string($item['validation_message'])
                            ? $item['validation_message']
                            : json_encode($item['validation_message']);
                    }
                    if (!empty($item['help_text'])) {
                        $formRecord->help_text = $item['help_text'];
                    }
                    if (!empty($item['tooltip'])) {
                        $formRecord->tooltip = $item['tooltip'];
                    }
                    
                    $formRecord->insert();
                }
            }
            
            // Criar tabela física só com os campos
            $this->createPhysicalTable($moduleData['name'], $fields);
            
            // Criar foreign keys
            if (!empty($foreignKeys)) {
                $this->createForeignKeys($moduleData['name'], $foreignKeys);
            }
            
            return $moduleId;
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Já existe') !== false || 
                strpos($e->getMessage(), 'escolha') !== false) {
                throw $e;
            }
            
            throw new Exception('Erro ao criar módulo: ' . $e->getMessage());
        }
    }
    
    public function createModule(array $moduleData, array $fields, array $foreignKeys = []): int
    {
        try {
            $existingModule = new TableRecord();
            $existingModule = $existingModule->equal('name', $moduleData['name'])->find();
            
            if ($existingModule->isHydrated()) {
                throw new Exception("Já existe um módulo com o nome '{$moduleData['name']}'. Por favor, escolha outro nome.");
            }
            
            $existingUrl = new TableRecord();
            $existingUrl = $existingUrl->equal('url_path', $moduleData['url_path'])->find();
            
            if ($existingUrl->isHydrated()) {
                throw new Exception("Já existe um módulo com a URL '{$moduleData['url_path']}'. Por favor, escolha outra URL.");
            }
            
            $tableRecord = new TableRecord();
            $tableRecord->category_id = $moduleData['category_id'];
            $tableRecord->name = $moduleData['name'];
            $tableRecord->display_name = $moduleData['display_name'];
            $tableRecord->url_path = $moduleData['url_path'];
            $tableRecord->icon = $moduleData['icon'] ?? null;
            $tableRecord->description = $moduleData['description'] ?? null;
            $tableRecord->is_active = $moduleData['is_active'] ?? 1;
            $tableRecord->internal = 0;
            $tableRecord->menu_order = $moduleData['menu_order'] ?? 0;
            $tableRecord->created_by = $_SESSION['user']['id'] ?? null;
            $tableRecord->updated_by = $_SESSION['user']['id'] ?? null;
            $tableRecord->insert();
            
            $moduleId = $tableRecord->id;
            
            $systemColumns = $this->getSystemColumns();
            foreach ($systemColumns as $index => $column) {
                $columnRecord = new ColumnRecord();
                $columnRecord->table_id = $moduleId;
                $columnRecord->name = $column['name'];
                $columnRecord->display_name = $column['display_name'];
                $columnRecord->type = $column['type'];
                $columnRecord->is_nullable = $column['is_nullable'];
                $columnRecord->is_unique = $column['is_unique'] ?? 0;
                $columnRecord->is_editable = 0;
                $columnRecord->is_visible_list = $column['is_visible_list'] ?? 0;
                $columnRecord->is_visible_form = 0;
                $columnRecord->is_visible_detail = 1;
                $columnRecord->insert();
            }
            
            $columnIndex = count($systemColumns);
            $foreignKeyMap = [];
            
            foreach ($foreignKeys as $fk) {
                $foreignKeyMap[$fk['column']] = $fk;
            }
            
            $formPosition = 0;
            
            foreach ($fields as $field) {
                $columnRecord = new ColumnRecord();
                $columnRecord->table_id = $moduleId;
                $columnRecord->name = $field['name'];
                $columnRecord->display_name = $field['display_name'];
                $columnRecord->type = $field['type'];
                $columnRecord->length = $field['length'] ?? null;
                $columnRecord->is_nullable = $field['is_nullable'] ?? 1;
                $columnRecord->is_unique = $field['is_unique'] ?? 0;
                $columnRecord->is_editable = empty($field['is_readonly']) ? 1 : 0;
                $columnRecord->is_visible_list = $field['is_visible_list'] ?? 1;
                $columnRecord->is_visible_form = $field['is_visible_form'] ?? 1;
                $columnRecord->is_visible_detail = 1;
                
                if (!empty($field['foreign_table'])) {
                    $columnRecord->foreign_table = $field['foreign_table'];
                }
                if (!empty($field['foreign_column'])) {
                    $columnRecord->foreign_column = $field['foreign_column'];
                }
                
                if (!empty($field['default_value'])) {
                    $columnRecord->default_value = $field['default_value'];
                }
                
                if (!empty($field['display_format'])) {
                    $columnRecord->display_format = $field['display_format'];
                }
                
                if (!empty($field['list_template'])) {
                    $columnRecord->list_template = $field['list_template'];
                }
                
                $columnRecord->insert();
                
                $formRecord = new TableFormRecord();
                $formRecord->table_id = $moduleId;
                $formRecord->column_id = $columnRecord->id;
                $formRecord->component_type = 'field';
                $formRecord->row_index = $field['row_index'] ?? $formPosition;
                $formRecord->row_size = $field['row_size'] ?? 1;
                $formRecord->column_size = $field['column_size'] ?? 12;
                $formRecord->position = $formPosition++;
                $formRecord->input_type = $field['input_type'] ?? 'text';
                
                if (!empty($field['input_options'])) {
                    $formRecord->input_options = $field['input_options'];
                }
                
                if (!empty($field['input_placeholder'])) {
                    $formRecord->input_placeholder = $field['input_placeholder'];
                }
                
                if (!empty($field['input_prefix'])) {
                    $formRecord->input_prefix = $field['input_prefix'];
                }
                
                if (!empty($field['input_suffix'])) {
                    $formRecord->input_suffix = $field['input_suffix'];
                }
                
                if (!empty($field['input_mask'])) {
                    $formRecord->input_mask = $field['input_mask'];
                }
                
                if (!empty($field['validation_rules'])) {
                    $formRecord->validation_rules = is_string($field['validation_rules']) 
                        ? $field['validation_rules'] 
                        : json_encode($field['validation_rules']);
                }
                
                if (!empty($field['validation_message'])) {
                    $formRecord->validation_message = is_string($field['validation_message'])
                        ? $field['validation_message']
                        : json_encode($field['validation_message']);
                }
                
                if (!empty($field['help_text'])) {
                    $formRecord->help_text = $field['help_text'];
                }
                
                if (!empty($field['tooltip'])) {
                    $formRecord->tooltip = $field['tooltip'];
                }
                
                $formRecord->insert();
            }
            
            $this->createPhysicalTable($moduleData['name'], $fields);
            
            if (!empty($foreignKeys)) {
                $this->createForeignKeys($moduleData['name'], $foreignKeys);
            }
            
            return $moduleId;
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Já existe') !== false || 
                strpos($e->getMessage(), 'escolha') !== false) {
                throw $e;
            }
            
            throw new Exception('Erro ao criar módulo: ' . $e->getMessage());
        }
    }
    
    public function updateModuleFields(int $moduleId, array $newFields): bool
    {
        try {
            $tableRecord = new TableRecord();
            $module = $tableRecord->equal('id', $moduleId)->find();
            
            if (!$module->isHydrated()) {
                throw new Exception('Módulo não encontrado');
            }
            
            $columnRecord = new ColumnRecord();
            $currentColumns = $columnRecord
                ->equal('table_id', $moduleId)
                ->orderBy('id ASC')
                ->findAllToArray();
            
            $systemFields = ['id', 'created_at', 'created_by', 'updated_at', 'updated_by'];
            $currentUserColumns = [];
            foreach ($currentColumns as $col) {
                if (!in_array($col['name'], $systemFields)) {
                    $currentUserColumns[$col['id']] = $col;
                }
            }
            
            $processedFieldIds = [];
            foreach ($newFields as $index => $field) {
                if (!empty($field['id'])) {
                    $columnRecord = new ColumnRecord();
                    $column = $columnRecord->equal('id', $field['id'])->find();
                    
                    if ($column->isHydrated()) {
                        $column->display_name = $field['display_name'];
                        $column->is_nullable = $field['is_nullable'] ?? 1;
                        $column->is_unique = $field['is_unique'] ?? 0;
                        $column->is_editable = empty($field['is_readonly']) ? 1 : 0;
                        $column->is_visible_list = $field['is_visible_list'] ?? 1;
                        $column->is_visible_form = $field['is_visible_form'] ?? 1;
                        
                        if (isset($field['default_value'])) {
                            $column->default_value = $field['default_value'];
                        }
                        
                        if (isset($field['display_format'])) {
                            $column->display_format = $field['display_format'];
                        }
                        
                        if (isset($field['list_template'])) {
                            $column->list_template = $field['list_template'];
                        }
                        
                        $column->save();
                        $processedFieldIds[] = $field['id'];
                    }
                } else {
                    $columnRecord = new ColumnRecord();
                    $columnRecord->table_id = $moduleId;
                    $columnRecord->name = $field['name'];
                    $columnRecord->display_name = $field['display_name'];
                    $columnRecord->type = $field['type'];
                    $columnRecord->length = $field['length'] ?? null;
                    $columnRecord->is_nullable = $field['is_nullable'] ?? 1;
                    $columnRecord->is_unique = $field['is_unique'] ?? 0;
                    $columnRecord->is_editable = empty($field['is_readonly']) ? 1 : 0;
                    $columnRecord->is_visible_list = $field['is_visible_list'] ?? 1;
                    $columnRecord->is_visible_form = $field['is_visible_form'] ?? 1;
                    $columnRecord->is_visible_detail = 1;
                    
                    if (!empty($field['default_value'])) {
                        $columnRecord->default_value = $field['default_value'];
                    }
                    
                    if (!empty($field['display_format'])) {
                        $columnRecord->display_format = $field['display_format'];
                    }
                    
                    if (!empty($field['list_template'])) {
                        $columnRecord->list_template = $field['list_template'];
                    }
                    
                    $columnRecord->insert();
                    
                    $this->addPhysicalColumn($module->name, $field);
                }
            }
            
            foreach ($currentUserColumns as $id => $column) {
                if (!in_array($id, $processedFieldIds)) {
                    $columnRecord = new ColumnRecord();
                    $col = $columnRecord->equal('id', $id)->find();
                    if ($col->isHydrated()) {
                        $col->delete();
                        
                        $this->dropPhysicalColumn($module->name, $column['name']);
                    }
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao atualizar campos: ' . $e->getMessage());
        }
    }
    
    public function canDeleteModule(int $moduleId): array
    {
        $tableRecord = new TableRecord();
        $module = $tableRecord->equal('id', $moduleId)->find();
        
        if (!$module->isHydrated()) {
            return ['canDelete' => false, 'reason' => 'Módulo não encontrado'];
        }
        
        $tableName = $module->name;
        
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM `{$tableName}`");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $recordCount = $result['count'] ?? 0;
            
            if ($recordCount > 0) {
                return [
                    'canDelete' => false, 
                    'reason' => "O módulo possui {$recordCount} registro(s). Delete os dados primeiro."
                ];
            }
            
            return ['canDelete' => true];
            
        } catch (Exception $e) {
            return ['canDelete' => true];
        }
    }
    
    public function deleteModule(int $moduleId): bool
    {
        $canDelete = $this->canDeleteModule($moduleId);
        if (!$canDelete['canDelete']) {
            throw new Exception($canDelete['reason']);
        }
        
        try {
            $tableRecord = new TableRecord();
            $module = $tableRecord->equal('id', $moduleId)->find();
            
            if (!$module->isHydrated()) {
                throw new Exception('Módulo não encontrado');
            }
            
            $this->dropAllForeignKeysReferencingTable($module->name);
            
            $columnRecord = new ColumnRecord();
            $columns = $columnRecord->equal('table_id', $moduleId)->findAll();
            foreach ($columns as $column) {
                $column->delete();
            }
            
            $module->delete();
            
            $this->dropPhysicalTable($module->name);
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception('Erro ao deletar módulo: ' . $e->getMessage());
        }
    }
    
    private function createPhysicalTable(string $tableName, array $fields): void
    {
        $allFields = array_merge($this->getSystemColumns(), $fields);
        $sql = ColumnTypeMapper::buildCreateTableSql($tableName, $allFields);
        
        $this->db->exec($sql);
    }
    
    private function addPhysicalColumn(string $tableName, array $field): void
    {
        $sql = ColumnTypeMapper::buildAlterAddColumn($tableName, $field);
        
        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            if (!str_contains($e->getMessage(), 'Duplicate column')) {
                throw $e;
            }
        }
    }
    
    private function dropPhysicalColumn(string $tableName, string $columnName): void
    {
        if (in_array($columnName, ['id', 'created_at', 'created_by', 'updated_at', 'updated_by'])) {
            return;
        }
        
        $sql = ColumnTypeMapper::buildAlterDropColumn($tableName, $columnName);
        
        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
        }
    }
    
    private function dropPhysicalTable(string $tableName): void
    {
        try {
            $sql = "DROP TABLE IF EXISTS `{$tableName}`";
            $this->db->exec($sql);
        } catch (Exception $e) {
        }
    }
    
    private function getSystemColumns(): array
    {
        return [
            [
                'name' => 'id',
                'display_name' => 'ID',
                'type' => 'INT',
                'length' => '11',
                'input_type' => 'number',
                'is_nullable' => 0,
                'is_unique' => 1,
                'is_visible_list' => 1,
                'is_visible_form' => 0,
                'is_editable' => 0
            ],
            [
                'name' => 'created_at',
                'display_name' => 'Criado em',
                'type' => 'DATETIME',
                'input_type' => 'datetime',
                'is_nullable' => 0,
                'is_visible_list' => 0,
                'is_visible_form' => 0,
                'is_editable' => 0
            ],
            [
                'name' => 'created_by',
                'display_name' => 'Criado por',
                'type' => 'INT',
                'length' => '11',
                'input_type' => 'number',
                'is_nullable' => 1,
                'is_visible_list' => 0,
                'is_visible_form' => 0,
                'is_editable' => 0
            ],
            [
                'name' => 'updated_at', 
                'display_name' => 'Atualizado em',
                'type' => 'DATETIME',
                'input_type' => 'datetime',
                'is_nullable' => 0,
                'is_visible_list' => 0,
                'is_visible_form' => 0,
                'is_editable' => 0
            ],
            [
                'name' => 'updated_by',
                'display_name' => 'Atualizado por',
                'type' => 'INT',
                'length' => '11',
                'input_type' => 'number',
                'is_nullable' => 1,
                'is_visible_list' => 0,
                'is_visible_form' => 0,
                'is_editable' => 0
            ]
        ];
    }
    
    private function createForeignKeys(string $tableName, array $foreignKeys): void
    {
        foreach ($foreignKeys as $fk) {
            $constraintName = "fk_{$tableName}_{$fk['column']}";
            
            try {
                $sql = "ALTER TABLE `{$tableName}` 
                        ADD CONSTRAINT `{$constraintName}`
                        FOREIGN KEY (`{$fk['column']}`) 
                        REFERENCES `{$fk['foreign_table']}`(`id`)
                        ON DELETE SET NULL";
                
                $this->db->exec($sql);
            } catch (Exception $e) {
                // Silently fail - FK creation is optional
            }
        }
    }
    
    private function dropAllForeignKeysReferencingTable(string $tableName): void
    {
        try {
            $sql = "SELECT 
                        CONSTRAINT_NAME,
                        TABLE_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE REFERENCED_TABLE_NAME = :table
                    AND CONSTRAINT_SCHEMA = DATABASE()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['table' => $tableName]);
            $constraints = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($constraints as $constraint) {
                try {
                    $dropSql = "ALTER TABLE `{$constraint['TABLE_NAME']}` 
                               DROP FOREIGN KEY `{$constraint['CONSTRAINT_NAME']}`";
                    $this->db->exec($dropSql);
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }
    }
}
