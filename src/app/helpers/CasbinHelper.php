<?php

namespace App\Helpers;

use Casbin\Enforcer;
use CasbinAdapter\Database\Adapter;

class CasbinHelper extends Enforcer
{
    protected ?\Casbin\Persist\Adapter $adapter;

    public function __construct()
    {
        $this->adapter = Adapter::newAdapter([
            'type' => 'mysql',
            'hostname' => $_ENV['DB_HOST'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'database' => $_ENV['DB_DATABASE'],
            'policy_table_name' => $_ENV['DB_TABLE_PREFIX'] . 'role',
        ]);

        parent::__construct(__DIR__ . '/../configs/casbin-model.conf', $this->adapter);
    }

    
    public static function userCan(?int $userId, string $table, string $column = '*', string $action = 'read'): bool
    {
        $enforcer = \Flight::casbin();

        if (!$userId) {
            $hasGuestPermission = $enforcer->enforce('guest', $table, $column, $action);

            return $hasGuestPermission;
        }

        $subject = "user:{$userId}";

        return $enforcer->enforce($subject, $table, $column, $action);
    }
}
