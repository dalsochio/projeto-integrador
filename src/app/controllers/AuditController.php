<?php

namespace App\Controllers;

use App\Helpers\CasbinHelper;
use App\Records\LogRecord;
use App\Records\TableRecord;
use App\Records\UserRecord;
use Flight;
use flight\Engine;
use PDO;

class AuditController
{
    private Engine $flight;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
    }

    public function index(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'panel_log', '*', 'read');

        if (!$hasPermission) {
            Flight::halt(403, 'Você não tem permissão para acessar esta página');
            return;
        }

        $request = Flight::request();
        $filters = [
            'table_name' => $request->query->table_name ?? null,
            'action' => $request->query->action ?? null,
            'route_type' => $request->query->route_type ?? null,
            'user_id' => $request->query->user_id ?? null,
            'date_from' => $request->query->date_from ?? null,
            'date_to' => $request->query->date_to ?? null,
        ];

        $perPage = 50;
        $page = (int)($request->query->page ?? 1);
        $offset = ($page - 1) * $perPage;

        $db = Flight::db();
        
        $conditions = [];
        $params = [];

        if ($filters['table_name']) {
            $conditions[] = 'table_name = ?';
            $params[] = $filters['table_name'];
        }

        if ($filters['action']) {
            $conditions[] = 'action = ?';
            $params[] = strtoupper($filters['action']);
        }

        if ($filters['route_type']) {
            $conditions[] = 'route_type = ?';
            $params[] = $filters['route_type'];
        }

        if ($filters['user_id']) {
            $conditions[] = 'user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if ($filters['date_from']) {
            $conditions[] = 'created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if ($filters['date_to']) {
            $conditions[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }

        $table = $_ENV['DB_TABLE_PREFIX'] . 'log';
        
        $countSql = "SELECT COUNT(*) as total FROM `{$table}`" . $whereClause;
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalLogs = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        $sql = "SELECT * FROM `{$table}`" . $whereClause . " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $userCache = [];
        foreach ($logs as &$log) {
            if ($log['user_id']) {
                if (!isset($userCache[$log['user_id']])) {
                    $userRecord = new UserRecord();
                    $user = $userRecord->equal('id', $log['user_id'])->find();
                    $userCache[$log['user_id']] = $user->isHydrated() ? $user->username : 'Desconhecido';
                }
                $log['username'] = $userCache[$log['user_id']];
            } else {
                $log['username'] = 'Sistema';
            }

            if ($log['data']) {
                $log['data_decoded'] = json_decode($log['data'], true);
            }

            if ($log['changes']) {
                $log['changes_decoded'] = json_decode($log['changes'], true);
            }
        }

        $tableRecord = new TableRecord();
        $tables = $tableRecord
            ->select('name, display_name')
            ->orderBy('display_name ASC')
            ->findAllToArray();

        $userRecord = new UserRecord();
        $users = $userRecord
            ->select('id, username')
            ->orderBy('username ASC')
            ->findAllToArray();

        $totalPages = ceil($totalLogs / $perPage);

        Flight::render('page/panel/audit/index.latte', [
            'title' => 'Auditoria',
            'logs' => $logs,
            'tables' => $tables,
            'users' => $users,
            'filters' => $filters,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'perPage' => $perPage,
                'total' => $totalLogs,
            ],
        ]);
    }

    public function show(string $id): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'panel_log', '*', 'read');

        if (!$hasPermission) {
            Flight::halt(403, 'Você não tem permissão para acessar esta página');
            return;
        }

        $logRecord = new LogRecord();
        $log = $logRecord->equal('id', (int)$id)->find();

        if (!$log->isHydrated()) {
            Flight::notFound();
            return;
        }

        $logData = $log->toArray();

        if ($logData['user_id']) {
            $userRecord = new UserRecord();
            $user = $userRecord->equal('id', $logData['user_id'])->find();
            $logData['username'] = $user->isHydrated() ? $user->username : 'Desconhecido';
        } else {
            $logData['username'] = 'Sistema';
        }

        if ($logData['data']) {
            $logData['data_decoded'] = json_decode($logData['data'], true);
        } else {
            $logData['data_decoded'] = null;
        }

        if ($logData['changes']) {
            $logData['changes_decoded'] = json_decode($logData['changes'], true);
        } else {
            $logData['changes_decoded'] = null;
        }

        $tableRecord = new TableRecord();
        $table = $tableRecord->equal('name', $logData['table_name'])->find();
        $logData['table_display_name'] = $table->isHydrated() ? $table->display_name : $logData['table_name'];

        Flight::render('page/panel/audit/show.latte', [
            'title' => 'Detalhes do Log',
            'log' => $logData,
        ]);
    }
}
