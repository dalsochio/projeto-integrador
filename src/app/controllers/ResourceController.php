<?php

namespace App\Controllers;

use App\Helpers\ApiHelper;
use App\Helpers\ValidationHelper;
use App\Records\TableFormRecord;
use App\Records\ColumnRecord;
use Flight;
use flight\Engine;
class ResourceController extends ApiHelper
{
    private Engine $flight;
    private string $category;
    private string $resourceName;
    private string $tableName;
    private array $resource;
    private array $fields;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
        parent::__construct();

        $request = $this->flight->request();
        $url = substr($request->url, 1);
        [$category, $resourceName] = explode('/', $url);
        $this->category = $category;
        $this->resourceName = $resourceName;

        if ($this->resourceName) {
            $resource = $this->resourceByUrlPath($this->resourceName);

            if ($resource) {
                $this->resource = $resource;
                $this->tableName = $this->resource['name'];
                $this->fields = $this->getFieldsWithLayout($this->resource['id']);
            } else {
                $this->resource = [];
                $this->tableName = '';
                $this->fields = [];
            }
        }
    }

    public function index(): void
    {
        $table = $this->records($this->tableName)->withColumns()->get();

        Flight::render('page/resource/index.latte', [
            'resource' => $this->resource,
            'table' => $table,
            'title' => $this->resource['display_name'] ?? 'Recursos'
        ]);
    }

    public function show($category, $resourceName, string $id): void
    {
        $data = $this->record($this->tableName, $id);

        if (!$data) {
            Flight::notFound();
            return;
        }

        $data = $this->processCheckboxFields($data);

        Flight::render('page/resource/show.latte', [
            'title' => $this->resource['display_name'] ?? 'Detalhes',
            'resource' => $this->resource,
            'fields' => $this->fields,
            'data' => $data
        ]);
    }

    private function getFieldsWithLayout(int $tableId): array
    {
        $formRecords = (new TableFormRecord())
            ->equal('table_id', $tableId)
            ->orderBy('position ASC')
            ->findAll();
        
        if (empty($formRecords)) {
            return $this->fields($this->tableName);
        }
        
        $fields = [];
        
        foreach ($formRecords as $formRecord) {
            $componentType = $formRecord->component_type ?? 'field';
            
            if ($componentType === 'field' && !empty($formRecord->column_id)) {
                $column = (new ColumnRecord())
                    ->equal('id', $formRecord->column_id)
                    ->find();
                
                if ($column->isHydrated()) {
                    // Dados da coluna (definição do campo)
                    $fieldData = [
                        'id' => $column->id,
                        'table_id' => $column->table_id,
                        'name' => $column->name,
                        'display_name' => $column->display_name,
                        'type' => $column->type,
                        'length' => $column->length,
                        'is_nullable' => $column->is_nullable,
                        'default_value' => $column->default_value,
                        'is_unique' => $column->is_unique,
                        'is_primary' => $column->is_primary,
                        'auto_increment' => $column->auto_increment,
                        'foreign_table' => $column->foreign_table,
                        'foreign_column' => $column->foreign_column,
                        'is_visible_list' => $column->is_visible_list,
                        'is_visible_form' => $column->is_visible_form,
                        'is_visible_detail' => $column->is_visible_detail,
                        'is_editable' => $column->is_editable,
                        'is_searchable' => $column->is_searchable,
                        'display_format' => $column->display_format,
                        'list_template' => $column->list_template,
                    ];
                    
                    // Mesclar dados de layout/renderização de panel_table_form
                    $fieldData['row_index'] = $formRecord->row_index;
                    $fieldData['row_size'] = $formRecord->row_size;
                    $fieldData['column_size'] = $formRecord->column_size;
                    $fieldData['position'] = $formRecord->position;
                    $fieldData['input_type'] = $formRecord->input_type ?? 'text';
                    $fieldData['input_options'] = $formRecord->input_options;
                    $fieldData['input_placeholder'] = $formRecord->input_placeholder;
                    $fieldData['input_prefix'] = $formRecord->input_prefix;
                    $fieldData['input_suffix'] = $formRecord->input_suffix;
                    $fieldData['input_mask'] = $formRecord->input_mask;
                    $fieldData['validation_rules'] = $formRecord->validation_rules;
                    $fieldData['validation_message'] = $formRecord->validation_message;
                    $fieldData['help_text'] = $formRecord->help_text;
                    $fieldData['tooltip'] = $formRecord->tooltip;
                    
                    $fields[] = $fieldData;
                }
            } elseif (in_array($componentType, ['divider_horizontal', 'divider_vertical'])) {
                $config = !empty($formRecord->config) ? json_decode($formRecord->config, true) : [];
                $fields[] = [
                    'component_type' => $componentType,
                    'divider_type' => $componentType === 'divider_horizontal' ? 'horizontal' : 'vertical',
                    'divider_text' => $config['text'] ?? '',
                    'divider_color' => $config['color'] ?? '',
                    'divider_align' => $config['align'] ?? '',
                    'row_index' => $formRecord->row_index,
                    'row_size' => $formRecord->row_size,
                    'column_size' => $formRecord->column_size,
                    'position' => $formRecord->position,
                    'is_visible_form' => true,
                    'is_visible_list' => false
                ];
            }
        }
        
        return $fields;
    }
    
    public function create(): void
    {
        $formFields = [];
        foreach ($this->fields as $field) {
            if ($field['is_visible_form'] ?? false) {
                $formFields[] = $field;
            }
        }

        Flight::render('page/resource/create.latte', [
            'title' => 'Novo registro',
            'resource' => $this->resource,
            'fields' => $formFields,
        ]);
    }

    public function store(): void
    {
        $data = Flight::request()->data->getData();

        $formFields = [];
        foreach ($this->fields as $field) {
            if ($field['is_visible_form'] ?? false) {
                $formFields[] = $field;
            }
        }

        $validation = ValidationHelper::validate($formFields, $data);

        if ($validation !== true) {
            Flight::render('page/resource/create.latte', [
                'title' => 'Novo registro',
                'resource' => $this->resource,
                'fields' => $formFields,
                'data' => $data,
                'errors' => $validation
            ]);
            return;
        }

        $uploadedFiles = [];

        foreach ($this->fields as $field) {
            if (($field['input_type'] ?? '') === 'checkbox' && isset($data[$field['name']]) && is_array($data[$field['name']])) {
                $data[$field['name']] = json_encode($data[$field['name']]);
            }

            if (($field['input_type'] ?? '') === 'file') {
                $fieldName = $field['name'];
                $files = Flight::request()->files;

                if (isset($files[$fieldName]) && $files[$fieldName]['error'] === UPLOAD_ERR_OK) {
                    $relativePath = \App\Helpers\FileUploadHelper::upload($files[$fieldName], $this->tableName);

                    if ($relativePath) {
                        $data[$fieldName] = $relativePath;
                        $uploadedFiles[] = $relativePath;
                    } else {
                        foreach ($uploadedFiles as $uploadedFile) {
                            \App\Helpers\FileUploadHelper::delete($uploadedFile);
                        }

                        Flight::render('page/resource/create.latte', [
                            'title' => 'Novo registro',
                            'resource' => $this->resource,
                            'fields' => $this->fields,
                            'data' => $data,
                            'errors' => [$fieldName => 'Erro ao fazer upload do arquivo. Tipos permitidos: ' . \App\Helpers\FileUploadHelper::getAllowedTypesLabel() . '. Tamanho máximo: ' . \App\Helpers\FileUploadHelper::getMaxSizeLabel()]
                        ]);
                        return;
                    }
                }
            }
        }

        $id = $this->createRecord($this->tableName, $data);

        if ($id) {
            \App\Helpers\AuditLogger::logFromResourceRoute($this->tableName, 'create', $id, $data);
            Flight::flash()->success('Registro criado com sucesso');
            Flight::redirect("/{$this->category}/{$this->resourceName}");
        } else {
            foreach ($uploadedFiles as $uploadedFile) {
                \App\Helpers\FileUploadHelper::delete($uploadedFile);
            }

            Flight::render('page/resource/create.latte', [
                'title' => 'Novo registro',
                'resource' => $this->resource,
                'fields' => $this->fields,
                'data' => $data,
                'errors' => ['_error' => 'Erro ao criar registro']
            ]);
        }
    }

    public function edit($category, $resourceName, string $id): void
    {
        $data = $this->record($this->tableName, $id);

        if (!$data) {
            Flight::notFound();
            return;
        }

        $formFields = [];
        foreach ($this->fields as $field) {
            if ($field['is_visible_form'] ?? false) {
                $formFields[] = $field;
            }
        }

        $data = $this->processCheckboxFields($data);

        Flight::render('page/resource/edit.latte', [
            'title' => 'Editar registro',
            'resource' => $this->resource,
            'fields' => $formFields,
            'data' => $data
        ]);
    }

    public function update($category, $resourceName, string $id): void
    {
        $data = Flight::request()->data->getData();

        $formFields = [];
        foreach ($this->fields as $field) {
            if ($field['is_visible_form'] ?? false) {
                $formFields[] = $field;
            }
        }

        $validation = ValidationHelper::validate($formFields, $data);

        if ($validation !== true) {
            Flight::render('page/resource/edit.latte', [
                'title' => 'Editar registro',
                'resource' => $this->resource,
                'fields' => $formFields,
                'data' => array_merge($this->record($this->tableName, $id) ?? [], $data),
                'errors' => $validation
            ]);
            return;
        }

        $currentRecord = $this->record($this->tableName, $id);

        if (!$currentRecord) {
            Flight::notFound();
            return;
        }

        $uploadedFiles = [];
        $oldFilesToDelete = [];

        foreach ($this->fields as $field) {
            if (($field['input_type'] ?? '') === 'checkbox' && isset($data[$field['name']]) && is_array($data[$field['name']])) {
                $data[$field['name']] = json_encode($data[$field['name']]);
            }

            if (($field['input_type'] ?? '') === 'file') {
                $fieldName = $field['name'];
                $files = Flight::request()->files;

                if (isset($files[$fieldName]) && $files[$fieldName]['error'] === UPLOAD_ERR_OK) {
                    $relativePath = \App\Helpers\FileUploadHelper::upload($files[$fieldName], $this->tableName);

                    if ($relativePath) {
                        if (!empty($currentRecord[$fieldName])) {
                            $oldFilesToDelete[] = $currentRecord[$fieldName];
                        }

                        $data[$fieldName] = $relativePath;
                        $uploadedFiles[] = $relativePath;
                    } else {
                        foreach ($uploadedFiles as $uploadedFile) {
                            \App\Helpers\FileUploadHelper::delete($uploadedFile);
                        }

                        Flight::render('page/resource/edit.latte', [
                            'title' => 'Editar registro',
                            'resource' => $this->resource,
                            'fields' => $this->fields,
                            'data' => array_merge($currentRecord, $data),
                            'errors' => [$fieldName => 'Erro ao fazer upload do arquivo. Tipos permitidos: ' . \App\Helpers\FileUploadHelper::getAllowedTypesLabel() . '. Tamanho máximo: ' . \App\Helpers\FileUploadHelper::getMaxSizeLabel()]
                        ]);
                        return;
                    }
                } elseif ($files[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
                    $data[$fieldName] = $currentRecord[$fieldName];
                }
            }
        }

        $success = $this->updateRecord($this->tableName, $id, $data);

        if ($success) {
            \App\Helpers\AuditLogger::logFromResourceRoute($this->tableName, 'update', $id, $data);
            foreach ($oldFilesToDelete as $oldFile) {
                \App\Helpers\FileUploadHelper::delete($oldFile);
            }

            Flight::flash()->success('Registro atualizado com sucesso');
            Flight::redirect("/{$this->category}/{$this->resourceName}");
        } else {
            foreach ($uploadedFiles as $uploadedFile) {
                \App\Helpers\FileUploadHelper::delete($uploadedFile);
            }

            Flight::render('page/resource/edit.latte', [
                'title' => 'Editar registro',
                'resource' => $this->resource,
                'fields' => $this->fields,
                'data' => array_merge($currentRecord, $data),
                'errors' => ['_error' => 'Erro ao atualizar registro']
            ]);
        }
    }

    public function destroy($category, $resourceName, string $id): void
    {
        $record = $this->record($this->tableName, $id);

        if (!$record) {
            Flight::flash()->error('Registro não encontrado');
            Flight::json(['success' => false], 404);
            return;
        }

        $success = $this->deleteRecord($this->tableName, $id);

        if ($success) {
            \App\Helpers\AuditLogger::logFromResourceRoute($this->tableName, 'delete', $id, null);
            foreach ($this->fields as $field) {
                if (($field['input_type'] ?? '') === 'file' && !empty($record[$field['name']])) {
                    \App\Helpers\FileUploadHelper::delete($record[$field['name']]);
                }
            }

            Flight::flash()->success('Registro deletado com sucesso!');
            Flight::json(['success' => true], 200);
        } else {
            Flight::flash()->error('Erro ao deletar registro');
            Flight::json(['success' => false], 500);
        }
    }

    private function processCheckboxFields(array $data): array
    {
        foreach ($this->fields as $field) {
            if (($field['input_type'] ?? '') === 'checkbox' && isset($data[$field['name']])) {
                $value = $data[$field['name']];
                if (is_string($value) && !empty($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data[$field['name']] = $decoded;
                    }
                }
            }
        }
        return $data;
    }

}
