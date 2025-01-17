<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Cycle\Tests\Mssql;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\SQLServer\DsnConnectionConfig;
use Cycle\Database\Config\SQLServerDriverConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseManager;
use Yiisoft\Rbac\Cycle\Tests\Base\Logger;

trait DatabaseTrait
{
    protected function makeDatabase(): DatabaseInterface
    {
        $dbConfig = new DatabaseConfig(
            [
                'default' => 'default',
                'databases' => [
                    'default' => ['connection' => 'mssql'],
                ],
                'connections' => [
                    'mssql' => new SQLServerDriverConfig(new DsnConnectionConfig(
                        'sqlsrv:Server=127.0.0.1,1433;Database=yiitest',
                        'SA',
                        'YourStrong!Passw0rd',
                    )),
                ],
            ]
        );
        $dbManager = new DatabaseManager($dbConfig);

        $logger = new Logger();
        $dbManager->setLogger($logger);
        $this->setLogger($logger);

        return $dbManager->database();
    }

    protected function checkItemsChildrenTable(): void
    {
        parent::checkItemsChildrenTable();

        $this->checkItemsChildrenTableForeignKeys();
    }
}
