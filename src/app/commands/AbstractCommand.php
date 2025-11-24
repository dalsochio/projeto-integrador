<?php

namespace App\Commands;

use flight\commands\AbstractBaseCommand;

class AbstractCommand extends AbstractBaseCommand
{
    public function __construct($nameOrConfig, $description = 'description', $config = [])
    {
        require_once __DIR__ . '/../bootstrap.php';

        if (is_array($nameOrConfig)) {
            $config = $nameOrConfig;
            $nameOrConfig = 'default-name';
            $description = 'Default description';
        }

        parent::__construct($nameOrConfig, $description, $config);
    }
}