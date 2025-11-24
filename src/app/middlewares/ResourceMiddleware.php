<?php

namespace App\Middlewares;

use App\Records\TableRecord;
use flight\Engine;

class ResourceMiddleware
{
    protected Engine $app;
    private string $category;
    private string $tableName;
    private string $method;
    private string $action;

    public function __construct(Engine $app)
    {
        $this->app = $app;
    }

    public function before(array $params)
    {
        $user = $_SESSION['user'] ?? null;

        if (!$user) {
            $this->app->notFound();
            return false;
        }

        $request = $this->app->request();
        $this->category = $params['category'] ?? '';
        $urlPath = $params['resource'] ?? '';

        $tableRecord = new TableRecord();
        $table = $tableRecord->equal('url_path', $urlPath)->find();

        if (!$table || !$table->id) {
            $this->app->notFound();
            return false;
        }

        $this->tableName = $table->name;
        $this->method = $request->method;

        $url = $request->url;
        switch ($this->method) {
            case 'GET':
                if (str_contains($url, '/create')) {
                    $this->action = 'create';
                } else if (str_contains($url, '/edit')) {
                    $this->action = 'edit';
                } else {
                    $this->action = 'read';
                }
                break;
            case 'POST':
                $this->action = 'create';
                break;
            case 'PUT':
                $this->action = 'edit';
                break;
            case 'DELETE':
                $this->action = 'delete';
                break;
            default:
                $this->action = '*';
                break;
        }

        $enforcer = $this->app->casbin();

        $userId = "user:{$user['id']}";
        $allowed = $enforcer->enforce($userId, $this->tableName, '*', $this->action);

        if (!$allowed) {
            $this->app->response()->status(403);
            return false;
        }

        return true;
    }
}
