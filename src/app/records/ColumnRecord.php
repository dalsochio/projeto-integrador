<?php

namespace App\Records;

use Flight;

class ColumnRecord extends AbstractRecord
{
    protected string $table;

    public function __construct()
    {
        $this->table = $_ENV['DB_TABLE_PREFIX'] . 'column';
        parent::__construct();
    }
}
