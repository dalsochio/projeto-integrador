<?php

namespace App\Controllers;

use App\Helpers\ApiHelper;
use App\Helpers\ValidationHelper;
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
            $this->resource = $this->resourceByUrlPath($this->resourceName);

            if ($this->resource) {
                $this->tableName = $this->resource['name'];
                $this->fields = $this->fields($this->tableName);
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
