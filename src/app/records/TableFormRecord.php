<?php

namespace App\Records;

class TableFormRecord extends AbstractRecord
{
    protected string $table;

    public function __construct()
    {
        $this->table = $_ENV['DB_TABLE_PREFIX'] . 'table_form';
        parent::__construct();
    }
}
