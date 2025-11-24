<?php

namespace App\Records;

use Flight;

class RoleInfoRecord extends AbstractRecord
{
    protected string $table;

    public function __construct()
    {
        $this->table = $_ENV['DB_TABLE_PREFIX'] . 'role_info';
        parent::__construct();
    }
}
