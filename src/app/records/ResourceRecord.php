<?php

namespace App\Records;

class ResourceRecord extends AbstractRecord
{
    protected string $table;

    public function __construct($table)
    {
        $this->table = $table;
        parent::__construct();
    }
}
