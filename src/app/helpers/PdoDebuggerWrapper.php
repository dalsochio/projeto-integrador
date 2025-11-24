<?php

namespace App\Helpers;

use App\traits\PdoWrapperTrait;
use flight\debug\database\PdoQueryCapture;
use PDO;

class PdoDebuggerWrapper extends PdoQueryCapture
{
    use PdoWrapperTrait;

    private string $dns;
    private string $username;
    private string $password;
    private array $options;

    public function __construct()
    {
        $this->dns = 'mysql:host=' . $_ENV['DB_HOST'] . ':' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_DATABASE'];
        $this->username = $_ENV['DB_USERNAME'];
        $this->password = $_ENV['DB_PASSWORD'];
        $this->options = [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'utf8mb4\', time_zone = \'+00:00\'',
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        parent::__construct($this->dns, $this->username, $this->password, $this->options, false);
    }
}
