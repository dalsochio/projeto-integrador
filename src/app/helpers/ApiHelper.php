<?php

namespace App\Helpers;

use App\Records\ColumnRecord;
use App\Records\TableRecord;
use Nyholm\Psr7\ServerRequest;
use Tqdev\PhpCrudApi\Api;
use Tqdev\PhpCrudApi\Config\Config;
use Flight;

class ApiHelper extends Api
{
    protected Config $config;
    private ?string $tableName = null;
    private array $data = [
        'records' => [],
        'results' => []
    ];

    public function __construct()
    {
        $allowRegistration = \App\Helpers\ConfigHelper::get('allow_registration', '1');
        
        $this->config = new Config([
            'driver' => 'mysql',
            'address' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'database' => $_ENV['DB_DATABASE'],
            'basePath' => '/api',
            'debug' => $_ENV['APP_ENV'] === 'development',

            'controllers' => 'records,openapi',
            'customOpenApiBuilders' => 'App\\Helpers\\AuthOpenApiBuilder',
            
            'openApiBase' => json_encode([
                'info' => [
                    'title' => 'Painel API',
                    'version' => '1.0.0',
                    'description' => 'API do Painel de Administração - Gerenciamento de recursos e autenticação'
                ]
            ]),

            'middlewares' => 'cors,dbAuth,authorization,customization',

            'dbAuth.usersTable' => $_ENV['USER_LOGIN_TABLE'],
            'dbAuth.usernameColumn' => $_ENV['USER_LOGIN_COLUMN'],
            'dbAuth.usernameFormField' => 'login',
            'dbAuth.passwordColumn' => 'password',
            'dbAuth.passwordFormField' => 'password',
            'dbAuth.returnedColumns' => 'id,username,email,created_at',
            'dbAuth.registerUser' => $allowRegistration,
            'dbAuth.passwordLength' => '12',
            'dbAuth.mode' => 'optional',

            'authorization.pathHandler' => function ($path) {
                $restrictedPaths = ['openapi'];
                
                if (!isset($_SESSION['user'])) {
                    return !in_array($path, $restrictedPaths);
                }
                
                return true;
            },

            'authorization.tableHandler' => function ($operation, $tableName) {
                // Bloquear tabelas internas do painel (com prefixo DB_TABLE_PREFIX) 
                // em TODAS as operações, incluindo 'document' (OpenAPI)
                $tablePrefix = $_ENV['DB_TABLE_PREFIX'] ?? 'panel_';
                if (str_starts_with($tableName, $tablePrefix)) {
                    return false;
                }
                
                // Bloquear tabelas de migração (começam com __)
                if (str_starts_with($tableName, '__')) {
                    return false;
                }
                
                $userId = $_SESSION['user']['id'] ?? null;
                
                if (!$userId) {
                    return false;
                }
                
                $allowed = \App\Helpers\CasbinHelper::userCan($userId, $tableName, '*', $operation);
                return $allowed;
            },

            'authorization.columnHandler' => function ($op, $table, $column) {
                $blocked = ['password', 'api_key', 'secret_token'];
                return !in_array($column, $blocked);
            },

            'authorization.recordHandler' => function ($op, $table) {
                $userId = $_SESSION['user']['id'] ?? null;

                if (!$userId) return null;

                if (\App\Helpers\CasbinHelper::userCan($userId, '*', '*', '*')) {
                    return null;
                }

                $readOperations = ['list', 'read'];
                if (in_array($op, $readOperations)) {
                    return null;
                }

                return ['created_by' => $userId];
            },

            'customization.afterHandler' => function ($operation, $tableName, $response, $environment) {
                $modifyingOperations = ['create', 'update', 'delete'];
                
                if (!in_array($operation, $modifyingOperations)) {
                    return $response;
                }
                
                if ($response->getStatusCode() >= 300) {
                    return $response;
                }
                
                $userId = $_SESSION['user']['id'] ?? null;
                $method = strtoupper($operation === 'create' ? 'POST' : ($operation === 'update' ? 'PUT' : 'DELETE'));
                
                $recordId = null;
                if ($operation === 'create') {
                    $body = (string)$response->getBody();
                    $data = json_decode($body, true);
                    $recordId = $data ?? null;
                    
                    if ($tableName === 'user' && $recordId) {
                        $enforcer = \Flight::casbin();
                        $enforcer->addRoleForUser("user:{$recordId}", "user");
                    }
                } else {
                    $request = \Flight::request();
                    $pathSegments = explode('/', trim($request->url, '/'));
                    $recordId = $pathSegments[3] ?? null;
                }
                
                $requestData = null;
                if (in_array($operation, ['create', 'update'])) {
                    $request = \Flight::request();
                    $requestData = $request->data ? $request->data->getData() : null;
                }
                
                \App\Helpers\AuditLogger::log(
                    $userId,
                    $method,
                    $tableName,
                    $recordId,
                    $requestData,
                    null,
                    'api'
                );
                
                return $response;
            },
        ]);

        parent::__construct($this->config);
    }

    public function resource($tableName): ?array
    {
        $tableRecord = new TableRecord();
        $table = $tableRecord->equal('name', $tableName)->find();

        if (!$table || !$table->id) {
            return null;
        }

        return $table->toArray();
    }

    public function resourceByUrlPath($urlPath): ?array
    {
        $tableRecord = new TableRecord();
        $table = $tableRecord->equal('url_path', $urlPath)->find();

        if (!$table || !$table->id) {
            return null;
        }

        return $table->toArray();
    }

    public function records($tableName): ApiHelper
    {
        $this->tableName = $tableName;

        $perPage = 10;
        $page = 1;
        $params = Flight::request()->query->getData();

        if (empty($params['page'])) {
            $params['page'] = '1,' . $perPage;
        }

        if (!str_contains($params['page'], ',')) {
            $page = $params['page'];
            $params['page'] = $params['page'] . ',' . $perPage;
        }

        $urlParams = http_build_query($params);

        $apiUrl = '/api/records/' . $tableName . '?' . $urlParams;

        $request = new ServerRequest(
            'GET',
            $apiUrl
        );

        $response = $this->handle($request);

        $body = (string)$response->getBody();
        $arrayData = json_decode($body, true);
        
        $this->data['records'] = $arrayData['records'] ?? [];
        $this->data['pagination'] = [
            'currentPage' => (int)$page,
            'perPage' => (int)$perPage,
            'total' => (int)($arrayData['results'] ?? 0),
        ];
        return $this;
    }

    public function fields($tableName)
    {
        $table = $this->resource($tableName);

        if (!$table || !isset($table['id'])) {
            return [];
        }

        $tableId = $table['id'];
        
        // Buscar dados de layout/renderização de panel_table_form
        $formRecord = new \App\Records\TableFormRecord();
        $formData = $formRecord
            ->equal('table_id', $tableId)
            ->orderBy('position ASC')
            ->findAllToArray();
        
        // Mapear column_id para dados do form
        $formDataByColumnId = [];
        foreach ($formData as $form) {
            if (!empty($form['column_id'])) {
                $formDataByColumnId[$form['column_id']] = $form;
            }
        }

        $columnRecord = new ColumnRecord();
        $fields = $columnRecord->equal('table_id', $tableId)->orderBy('id ASC')->findAllToArray();
        
        foreach ($fields as &$field) {
            // Mesclar dados de panel_table_form
            if (isset($formDataByColumnId[$field['id']])) {
                $formItem = $formDataByColumnId[$field['id']];
                $field['input_type'] = $formItem['input_type'] ?? 'text';
                $field['input_options'] = $formItem['input_options'] ?? $field['input_options'] ?? null;
                $field['input_placeholder'] = $formItem['input_placeholder'] ?? null;
                $field['input_prefix'] = $formItem['input_prefix'] ?? null;
                $field['input_suffix'] = $formItem['input_suffix'] ?? null;
                $field['help_text'] = $formItem['help_text'] ?? null;
                $field['row_index'] = $formItem['row_index'] ?? 0;
                $field['row_size'] = $formItem['row_size'] ?? 1;
                $field['column_size'] = $formItem['column_size'] ?? 12;
                $field['position'] = $formItem['position'] ?? 0;
            }
            
            if (!empty($field['foreign_table']) && !empty($field['foreign_column'])) {
                $field['input_options'] = $this->getForeignTableOptions(
                    $field['foreign_table'], 
                    $field['foreign_column']
                );
            }
        }
        
        return $fields;
    }
    
    private function getForeignTableOptions(string $tableName, string $displayColumn): string
    {
        try {
            $apiUrl = '/api/records/' . $tableName;
            $request = new ServerRequest('GET', $apiUrl);
            $response = $this->handle($request);
            
            if ($response->getStatusCode() !== 200) {
                return json_encode([]);
            }
            
            $body = (string)$response->getBody();
            $data = json_decode($body, true);
            
            if (empty($data['records'])) {
                return json_encode([]);
            }
            
            $options = [];
            foreach ($data['records'] as $record) {
                $options[] = [
                    'value' => $record['id'],
                    'label' => $record[$displayColumn] ?? $record['id']
                ];
            }
            
            return json_encode($options);
            
        } catch (\Exception $e) {
            return json_encode([]);
        }
    }

    public function withColumns(): ApiHelper
    {
        $columns = $this->fields($this->tableName);
        
        // Garantir que ID seja sempre a primeira coluna
        $idColumn = null;
        $otherColumns = [];
        
        foreach ($columns as $column) {
            if ($column['name'] === 'id') {
                $idColumn = $column;
            } else {
                $otherColumns[] = $column;
            }
        }
        
        if ($idColumn) {
            $columns = array_merge([$idColumn], $otherColumns);
        }
        
        $this->data['columns'] = $columns;
        
        $this->enrichRecordsWithReferences($columns);

        return $this;
    }
    
    private function enrichRecordsWithReferences(array $columns): void
    {
        if (empty($this->data['records'])) {
            return;
        }
        
        $referenceColumns = [];
        foreach ($columns as $column) {
            if (!empty($column['foreign_table']) && !empty($column['foreign_column'])) {
                $referenceColumns[] = $column;
            }
        }
        
        if (empty($referenceColumns)) {
            return;
        }
        
        foreach ($this->data['records'] as &$record) {
            foreach ($referenceColumns as $refColumn) {
                $foreignKey = $record[$refColumn['name']] ?? null;
                
                if ($foreignKey) {
                    $foreignRecord = $this->record($refColumn['foreign_table'], (string)$foreignKey);
                    
                    if ($foreignRecord && isset($foreignRecord[$refColumn['foreign_column']])) {
                        $record[$refColumn['name'] . '_display'] = $foreignRecord[$refColumn['foreign_column']];
                    }
                }
            }
        }
    }
    public function get(): array
    {
        // Enriquecer registros com dados do usuário
        if (!empty($this->data['records'])) {
            $this->data['records'] = $this->enrichWithUserData($this->data['records']);
        }
        return $this->data;
    }

    
    public function record(string $tableName, string $id): ?array
    {
        $apiUrl = '/api/records/' . $tableName . '/' . $id;

        $request = new ServerRequest('GET', $apiUrl);
        $response = $this->handle($request);

        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        if ($data) {
            $enriched = $this->enrichWithUserData([$data]);
            return $enriched[0] ?? $data;
        }

        return $data ?? null;
    }
    
    /**
     * Enriquece registros com dados do usuário (username, email) para created_by e updated_by
     */
    private function enrichWithUserData(array $records): array
    {
        if (empty($records)) {
            return $records;
        }
        
        // Coletar IDs únicos de usuários
        $userIds = [];
        foreach ($records as $record) {
            if (!empty($record['created_by'])) {
                $userIds[] = (int)$record['created_by'];
            }
            if (!empty($record['updated_by'])) {
                $userIds[] = (int)$record['updated_by'];
            }
        }
        
        if (empty($userIds)) {
            return $records;
        }
        
        $userIds = array_unique($userIds);
        
        // Buscar dados dos usuários
        $usersMap = [];
        try {
            $userRecord = new \App\Records\UserRecord();
            $users = $userRecord
                ->select('id', 'username', 'email')
                ->findAll();
            
            foreach ($users as $user) {
                if (in_array((int)$user->id, $userIds)) {
                    $usersMap[$user->id] = [
                        'username' => $user->username,
                        'email' => $user->email
                    ];
                }
            }
        } catch (\Exception $e) {
            // Se falhar, retorna os registros sem enriquecimento
            return $records;
        }
        
        // Enriquecer registros
        foreach ($records as &$record) {
            if (!empty($record['created_by']) && isset($usersMap[$record['created_by']])) {
                $record['created_by_name'] = $usersMap[$record['created_by']]['username'];
                $record['created_by_email'] = $usersMap[$record['created_by']]['email'];
            }
            if (!empty($record['updated_by']) && isset($usersMap[$record['updated_by']])) {
                $record['updated_by_name'] = $usersMap[$record['updated_by']]['username'];
                $record['updated_by_email'] = $usersMap[$record['updated_by']]['email'];
            }
        }
        
        return $records;
    }

    
    public function createRecord(string $tableName, array $data): ?string
    {
        $columns = $this->fields($tableName);
        $columnNames = array_column($columns, 'name');
        
        $userId = $_SESSION['user']['id'] ?? null;
        
        if (in_array('created_at', $columnNames) && !isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        if (in_array('updated_at', $columnNames) && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        if ($userId && in_array('created_by', $columnNames) && !isset($data['created_by'])) {
            $data['created_by'] = $userId;
        }
        
        if ($userId && in_array('updated_by', $columnNames) && !isset($data['updated_by'])) {
            $data['updated_by'] = $userId;
        }
        
        $apiUrl = '/api/records/' . $tableName;

        $request = (new ServerRequest('POST', $apiUrl))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->createStream(json_encode($data)));

        $response = $this->handle($request);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = (string)$response->getBody();
        return $body;
    }

    
    public function updateRecord(string $tableName, string $id, array $data): bool
    {
        $columns = $this->fields($tableName);
        $columnNames = array_column($columns, 'name');
        
        $userId = $_SESSION['user']['id'] ?? null;
        
        if (in_array('updated_at', $columnNames) && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        if ($userId && in_array('updated_by', $columnNames) && !isset($data['updated_by'])) {
            $data['updated_by'] = $userId;
        }
        
        $apiUrl = '/api/records/' . $tableName . '/' . $id;

        $request = (new ServerRequest('PUT', $apiUrl))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->createStream(json_encode($data)));

        $response = $this->handle($request);
        return $response->getStatusCode() === 200;
    }

    
    public function deleteRecord(string $tableName, string $id): bool
    {
        $apiUrl = '/api/records/' . $tableName . '/' . $id;

        $request = new ServerRequest('DELETE', $apiUrl);
        $response = $this->handle($request);

        return $response->getStatusCode() === 200;
    }

    
    private function createStream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        return \Nyholm\Psr7\Stream::create($stream);
    }
}
