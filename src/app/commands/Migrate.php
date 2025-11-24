<?php

namespace App\Commands;

use ByJG\DbMigration\Database\MySqlDatabase;
use ByJG\DbMigration\Exception\DatabaseDoesNotRegistered;
use ByJG\DbMigration\Exception\DatabaseIsIncompleteException;
use ByJG\DbMigration\Exception\DatabaseNotVersionedException;
use ByJG\DbMigration\Exception\InvalidMigrationFile;
use ByJG\DbMigration\Exception\OldVersionSchemaException;
use ByJG\DbMigration\Migration;
use ByJG\Util\Uri;
class Migrate extends AbstractCommand
{
    
    protected $migrationPath;

    
    protected $connectionUri;

    
    protected $migration;

    
    public function __construct(array $config)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);
        parent::__construct('migrate', 'Database migration tool', $config);

        $this->argument('action', 'Migration action: update, status, down, up')
            ->argument('[target_version]', 'Target migration version (optional)');
    }

    
    protected function initMigration()
    {
        $io = $this->app()->io();

        $connectionString = 'mysql://' . $_ENV['DB_USERNAME'] . ':' . $_ENV['DB_PASSWORD'] . '@' . $_ENV['DB_HOST'] . '/' . $_ENV['DB_DATABASE'];

        $this->connectionUri = new Uri($connectionString);

        $this->migrationPath = __DIR__ . '/../databases';

        Migration::registerDatabase(MySqlDatabase::class);

        $this->migration = new Migration($this->connectionUri, $this->migrationPath, false, '__migration');

        $this->migration->addCallbackProgress(function ($action, $currentVersion, $fileInfo) use ($io) {
            $versionDescription = $fileInfo['description'] ?? '';
            $io->info('[' . $action . '] version [' . $currentVersion . '] - [' . $versionDescription . ']', true);
        });

        try {
            $this->migration->getCurrentVersion();
        } catch (DatabaseNotVersionedException $e) {
            $io->info('Version table not found. Creating it automatically...', true);
            $this->migration->createVersion();
            $io->ok('Version table created successfully.', true);
        }
    }

    
    public function execute()
    {
        $io = $this->app()->io();

        try {
            $this->initMigration();

            $action = $this->action;
            $version = $this->target_version;

            switch ($action) {
                case 'update':
                    $io->info('Updating database [' . ($version ? " to version $version" : ' to latest version') . ']', true);
                    $this->migration->update($version);
                    break;

                case 'status':
                    $currentVersion = $this->migration->getCurrentVersion();
                    $io->info('Current database version: [' . $currentVersion . ']', true);
                    break;

                case 'up':
                    $io->info('Migrating database up [' . ($version ? " to version $version" : '') . ']', true);
                    $this->migration->up($version);
                    break;

                case 'down':
                    $io->info('Migrating database down [' . ($version ? " to version $version" : '') . ']', true);
                    $this->migration->down($version);
                    break;

                default:
                    $io->error('Invalid action: [' . $action . ']', true);
                    $io->info('Available actions: update, status, down, up', true);
                    return;
            }

            $io->ok('Migration completed successfully!', true);

        } catch (DatabaseDoesNotRegistered $e) {
            $io->error('Database driver not registered: [' . $e->getMessage() . ']', true);
            $io->info('Make sure you have registered the correct database driver for your connection.', true);

        } catch (DatabaseIsIncompleteException $e) {
            $io->error('Database is incomplete: [' . $e->getMessage() . ']', true);
        } catch (DatabaseNotVersionedException $e) {
            $io->error('Database not versioned: [' . $e->getMessage() . ']', true);
            $io->info('Run "migrate create" to create the version table first.', true);

        } catch (InvalidMigrationFile $e) {
            $io->error('Invalid migration file: [' . $e->getMessage() . ']', true);
            $io->info('Check your migration files for syntax errors or invalid SQL.', true);

        } catch (OldVersionSchemaException $e) {
            $io->error('Old version schema: [' . $e->getMessage() . ']', true);
            $io->info('Your current schema is older than the requested migration version.', true);

        } catch (\Exception $e) {
            $io->error('Migration error: [' . $e->getMessage() . '] [' . $e->getTraceAsString() . ']', true);
        }
    }
}
