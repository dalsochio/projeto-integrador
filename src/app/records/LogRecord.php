<?php

namespace App\Records;

use Flight;

class LogRecord extends AbstractRecord
{
    protected string $table;

    public function __construct()
    {
        $this->table = $_ENV['DB_TABLE_PREFIX'] . 'log';
        parent::__construct();
    }
}
