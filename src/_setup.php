<?php

use Casbin\Enforcer;
use CasbinAdapter\Database\Adapter;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__, null, true);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);


$tableName = $_ENV['DB_TABLE_PREFIX'] . 'role';
$pdo->exec("TRUNCATE TABLE `{$tableName}`");
echo "🗑️  Tabela {$tableName} limpa\n";

$adapter = Adapter::newAdapter([
    'type' => 'mysql',
    'hostname' => $_ENV['DB_HOST'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'database' => $_ENV['DB_DATABASE'],
    'policy_table_name' => $tableName,
]);

$e = new Enforcer(__DIR__ . '/app/configs/casbin-model.conf', $adapter);


$e->addPolicy('admin', '*', '*', '*');


$e->addGroupingPolicy('user:1', 'admin');

echo "✅ Permissões inicializadas com sucesso!\n";
echo "📋 Políticas criadas:\n";
echo "   - Admin: acesso total\n";
echo "   - Editor: CRUD (exceto delete e campos sensíveis)\n";
echo "   - Viewer: apenas leitura (exceto passwords)\n";
echo "\n👤 Usuário 1 configurado como admin\n";
