<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use InvalidArgumentException;

/**
 * A class for working with RBAC tables' schema using configured Cycle Database driver. Supports schema creation,
 * deletion and checking its existence.
 */
final class DbSchemaManager
{
    public const TABLE_PREFIX = 'yii_rbac_';
    public const ITEMS_TABLE = self::TABLE_PREFIX . 'item';
    public const ITEMS_CHILDREN_TABLE = self::TABLE_PREFIX . 'item_child';
    public const ASSIGNMENTS_TABLE = self::TABLE_PREFIX . 'assignment';

    /**
     * @var string|null A name of the table for storing RBAC items (roles and permissions).
     * @psalm-var ?non-empty-string
     */
    private ?string $itemsTable;
    /**
     * @var string|null A name of the table for storing RBAC assignments.
     * @psalm-var ?non-empty-string
     */
    private ?string $assignmentsTable;
    /**
     * @var string|null A name of the table for storing relations between RBAC items.
     * @psalm-var ?non-empty-string
     */
    private ?string $itemsChildrenTable;

    /**
     * @param DatabaseInterface $database Cycle database instance.
     * @param string|null $itemsTable A name of the table for storing RBAC items (roles and permissions).
     * @param string|null $itemsChildrenTable A name of the table for storing relations between RBAC items. When set to
     * `null`, it will be automatically generated using {@see $itemsTable}.
     * @param string|null $assignmentsTable A name of the table for storing RBAC assignments.
     *
     * @throws InvalidArgumentException When a table name is set to the empty string.
     */
    public function __construct(
        private DatabaseInterface $database,
        ?string $itemsTable = self::ITEMS_TABLE,
        ?string $itemsChildrenTable = self::ITEMS_CHILDREN_TABLE,
        ?string $assignmentsTable = self::ASSIGNMENTS_TABLE,
    ) {
        $this->initTables(
            itemsTable: $itemsTable,
            itemsChildrenTable: $itemsChildrenTable,
            assignmentsTable: $assignmentsTable,
        );
    }

    /**
     * Creates table for storing RBAC items (roles and permissions).
     *
     * @see $itemsTable
     */
    public function createItemsTable(): void
    {
        if ($this->itemsTable === null || $this->hasTable($this->itemsTable)) {
            return;
        }

        /** @var Table $table */
        $table = $this->database->table($this->itemsTable);
        $schema = $table->getSchema();

        $schema->string('name', 128)->nullable(false);
        $schema->string('type', 10)->nullable(false);
        $schema->string('description', 191);
        $schema->string('ruleName', 64);
        $schema->integer('createdAt')->nullable(false);
        $schema->integer('updatedAt')->nullable(false);
        $schema->index(['type'])->setName("idx-$this->itemsTable-type");
        $schema->setPrimaryKeys(['name']);

        $schema->save();
    }

    /**
     * Creates table for storing relations between RBAC items.
     *
     * @see $itemsChildrenTable
     */
    public function createItemsChildrenTable(): void
    {
        if (
            $this->itemsTable ===null ||
            $this->itemsChildrenTable === null ||
            $this->hasTable($this->itemsChildrenTable)
        ) {
            return;
        }

        /** @var Table $table */
        $table = $this->database->table($this->itemsChildrenTable);
        $schema = $table->getSchema();

        $schema->string('parent', 128)->nullable(false);
        $schema->string('child', 128)->nullable(false);
        $schema->setPrimaryKeys(['parent', 'child']);

        $schema
            ->foreignKey(['parent'])
            ->references($this->itemsTable, ['name'])
            ->setName("fk-$this->itemsChildrenTable-parent");
        $schema->renameIndex(['parent'], "idx-$this->itemsChildrenTable-parent");

        $schema
            ->foreignKey(['child'])
            ->references($this->itemsTable, ['name'])
            ->setName("fk-$this->itemsChildrenTable-child");
        $schema->renameIndex(['child'], "idx-$this->itemsChildrenTable-child");

        $schema->save();
    }

    /**
     * Creates table for storing RBAC assignments.
     *
     * @see $assignmentsTable
     */
    public function createAssignmentsTable(): void
    {
        if ($this->assignmentsTable === null || $this->hasTable($this->assignmentsTable)) {
            return;
        }

        /** @var Table $table */
        $table = $this->database->table($this->assignmentsTable);
        $schema = $table->getSchema();

        $schema->string('itemName', 128)->nullable(false);
        $schema->string('userId', 128)->nullable(false);
        $schema->setPrimaryKeys(['itemName', 'userId']);
        $schema->integer('createdAt')->nullable(false);

        $schema->save();
    }

    /**
     * Checks existence of a table in {@see $database} by a given name
     *
     * @param string $tableName Table name for checking.
     *
     * @throws InvalidArgumentException When a table name is set to the empty string.
     * @return bool Whether a table exists: `true` - exists, `false` - doesn't exist.
     */
    public function hasTable(string $tableName): bool
    {
        if ($tableName === '') {
            throw new InvalidArgumentException('Table name must be non-empty.');
        }

        return $this->database->hasTable($tableName);
    }

    /**
     * Drops a table in {@see $database} by a given name.
     *
     * @param string $tableName Table name for dropping.
     *
     * @throws InvalidArgumentException When a table name is set to the empty string.
     */
    public function dropTable(string $tableName): void
    {
        if ($tableName === '') {
            throw new InvalidArgumentException('Table name must be non-empty.');
        }

        /** @var Table $table */
        $table = $this->database->table($tableName);
        $schema = $table->getSchema();
        $schema->declareDropped();
        $schema->save();
    }

    /**
     * Ensures all Cycle RBAC related tables are present in the database. Creation is executed for each table only when
     * it doesn't exist.
     */
    public function ensureTables(): void
    {
        $this->createItemsTable();
        $this->createItemsChildrenTable();
        $this->createAssignmentsTable();
    }

    /**
     * Ensures no Cycle RBAC related tables are present in the database. Drop is executed for each table only when it
     * exists.
     */
    public function ensureNoTables(): void
    {
        if ($this->itemsChildrenTable !== null && $this->hasTable($this->itemsChildrenTable)) {
            $this->dropTable($this->itemsChildrenTable);
        }

        if ($this->assignmentsTable !== null && $this->hasTable($this->assignmentsTable)) {
            $this->dropTable($this->assignmentsTable);
        }

        if ($this->itemsTable !== null && $this->hasTable($this->itemsTable)) {
            $this->dropTable($this->itemsTable);
        }
    }

    /**
     * Gets name of the table for storing RBAC items (roles and permissions).
     *
     * @return string|null Table name.
     *
     * @see $itemsTable
     */
    public function getItemsTable(): ?string
    {
        return $this->itemsTable;
    }

    /**
     * Gets name of the table for storing RBAC assignments.
     *
     * @return string|null Table name.
     *
     * @see $assignmentsTable
     */
    public function getAssignmentsTable(): ?string
    {
        return $this->assignmentsTable;
    }

    /**
     * Gets name of the table for storing relations between RBAC items.
     *
     * @return string|null Table name.
     *
     * @see $itemsChildrenTable
     */
    public function getItemsChildrenTable(): ?string
    {
        return $this->itemsChildrenTable;
    }

    /**
     * Initializes table names.
     *
     * @throws InvalidArgumentException When a table name is set to the empty string.
     */
    private function initTables(?string $itemsTable, ?string $itemsChildrenTable, ?string $assignmentsTable): void
    {
        if ($itemsTable === null && $itemsChildrenTable === null && $assignmentsTable === null) {
            $message = 'At least items and items children table names or assignments table name must be set.';

            throw new InvalidArgumentException($message);
        }

        if (
            ($itemsTable !== null && $itemsChildrenTable === null) ||
            ($itemsTable === null && $itemsChildrenTable !== null)
        ) {
            throw new InvalidArgumentException('Items and items children table names must be set together.');
        }

        if ($itemsTable === '') {
            throw new InvalidArgumentException('Items table name can\'t be empty.');
        }

        $this->itemsTable = $itemsTable;

        if ($assignmentsTable === '') {
            throw new InvalidArgumentException('Assignments table name can\'t be empty.');
        }

        $this->assignmentsTable = $assignmentsTable;

        if ($itemsChildrenTable === '') {
            throw new InvalidArgumentException('Items children table name can\'t be empty.');
        }

        $this->itemsChildrenTable = $itemsChildrenTable;
    }
}
