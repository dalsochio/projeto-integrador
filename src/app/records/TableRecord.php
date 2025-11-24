<?php

namespace App\Records;

use Flight;

class TableRecord extends AbstractRecord
{
    protected string $table;

    public function __construct()
    {
        $this->table = $_ENV['DB_TABLE_PREFIX'] . 'table';
        parent::__construct();
    }
}
