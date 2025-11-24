<?php

namespace App\Helpers;

use PDO;

class DatabaseManager
{
    
    private $connectionArray = [];

    
    public function get(string $databaseKey): mixed
    {
        $databaseKey = $databaseKey ?? $_ENV['DB_DATABASE'];
        if (!array_key_exists($databaseKey, $this->connectionArray)) {
            $this->set($databaseKey);
        }

        return $this->connectionArray[$databaseKey];
    }

    
    public function set($databaseKey): void
    {
        $pdoOptions = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        $keyToDatabaseNameArray = [
            'panel' => $_ENV['DB_DATABASE'],
        ];

        if (!array_key_exists($databaseKey, $keyToDatabaseNameArray)) {
            throw new \Exception("Connection '$databaseKey' not found.");
        }

        $this->connectionArray[$databaseKey] = new PdoWrapper(
            'mysql:host=' . $_ENV['DB_HOST'] . ':' . $_ENV['DB_PORT'] . ';dbname=' . $keyToDatabaseNameArray[$databaseKey],
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD'],
            $pdoOptions
        );
    }
}
