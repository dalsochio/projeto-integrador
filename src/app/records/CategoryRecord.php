<?php

namespace App\Records;

use Flight;

class CategoryRecord extends AbstractRecord
{
    protected string $table;

    public function __construct()
    {
        $this->table = $_ENV['DB_TABLE_PREFIX'] . 'category';
        parent::__construct();
    }
}
