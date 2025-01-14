<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;

/**
 * **Warning:** Do not use directly! Use with {@see Manager} instead.
 *
 * Storage for RBAC assignments in the form of database table. Operations are performed using Cycle ORM.
 *
 * @psalm-type RawAssignment = array{
 *     itemName: string,
 *     userId: string,
 *     createdAt: int|string,
 * }
 */
final class AssignmentsStorage implements AssignmentsStorageInterface
{
    /**
     * @param DatabaseInterface $database Cycle database instance.
     *
     * @param string $tableName A name of the table for storing RBAC assignments.
     * @psalm-param non-empty-string $tableName
     */
    public function __construct(
        private DatabaseInterface $database,
        private string $tableName = DbSchemaManager::ASSIGNMENTS_TABLE,
    ) {
    }

    public function getAll(): array
    {
        /** @psalm-var RawAssignment[] $rows */
        $rows = $this
            ->database
            ->select()
            ->from($this->tableName)
            ->fetchAll();

        $assignments = [];
        foreach ($rows as $row) {
            $assignments[$row['userId']][$row['itemName']] = new Assignment(
                $row['userId'],
                $row['itemName'],
                (int) $row['createdAt'],
            );
        }

        return $assignments;
    }

    public function getByUserId(string $userId): array
    {
        /** @psalm-var RawAssignment[] $rawAssignments */
        $rawAssignments = $this->database
            ->select(['itemName', 'createdAt'])
            ->from($this->tableName)
            ->where(['userId' => $userId])
            ->fetchAll();
        $assignments = [];
        foreach ($rawAssignments as $rawAssignment) {
            $assignments[$rawAssignment['itemName']] = new Assignment(
                $userId,
                $rawAssignment['itemName'],
                (int) $rawAssignment['createdAt'],
            );
        }

        return $assignments;
    }

    public function getByItemNames(array $itemNames): array
    {
        if (empty($itemNames)) {
            return [];
        }

        /** @psalm-var RawAssignment[] $rawAssignments */
        $rawAssignments = $this->database
            ->select()
            ->from($this->tableName)
            ->where('itemName', 'IN', $itemNames)
            ->fetchAll();
        $assignments = [];
        foreach ($rawAssignments as $rawAssignment) {
            $assignments[] = new Assignment(
                $rawAssignment['userId'],
                $rawAssignment['itemName'],
                (int) $rawAssignment['createdAt'],
            );
        }

        return $assignments;
    }

    public function get(string $itemName, string $userId): ?Assignment
    {
        /** @psalm-var RawAssignment|false $row */
        $row = $this
            ->database
            ->select(['createdAt'])
            ->from($this->tableName)
            ->where(['itemName' => $itemName, 'userId' => $userId])
            ->run()
            ->fetch();

        return $row === false ? null : new Assignment($userId, $itemName, (int) $row['createdAt']);
    }

    public function exists(string $itemName, string $userId): bool
    {
        /**
         * @psalm-var array<0, 1>|false $result
         * @infection-ignore-all
         * - ArrayItemRemoval, select.
         * - IncrementInteger, limit.
         */
        $result = $this
            ->database
            ->select([new Fragment('1 AS item_exists')])
            ->from($this->tableName)
            ->where(['itemName' => $itemName, 'userId' => $userId])
            ->limit(1)
            ->run()
            ->fetch();

        return $result !== false;
    }

    public function userHasItem(string $userId, array $itemNames): bool
    {
        if (empty($itemNames)) {
            return false;
        }

        /**
         * @psalm-var array<0, 1>|false $result
         * @infection-ignore-all
         * - ArrayItemRemoval, select.
         * - IncrementInteger, limit.
         */
        $result = $this
            ->database
            ->select([new Fragment('1 AS assignment_exists')])
            ->from($this->tableName)
            ->where(['userId' => $userId])
            ->andWhere('itemName', 'IN', $itemNames)
            ->limit(1)
            ->run()
            ->fetch();

        return $result !== false;
    }

    public function add(Assignment $assignment): void
    {
        $this
            ->database
            ->insert($this->tableName)
            ->values([
                'itemName' => $assignment->getItemName(),
                'userId' => $assignment->getUserId(),
                'createdAt' => $assignment->getCreatedAt(),
            ])
            ->run();
    }

    public function hasItem(string $name): bool
    {
        /**
         * @psalm-var array<0, 1>|false $result
         * @infection-ignore-all
         * - ArrayItemRemoval, select.
         * - IncrementInteger, limit.
         */
        $result = $this
            ->database
            ->select([new Fragment('1 AS assignment_exists')])
            ->from($this->tableName)
            ->where(['itemName' => $name])
            ->limit(1)
            ->run()
            ->fetch();

        return $result !== false;
    }

    public function renameItem(string $oldName, string $newName): void
    {
        $this
            ->database
            ->update($this->tableName, values: ['itemName' => $newName], where: ['itemName' => $oldName])
            ->run();
    }

    public function remove(string $itemName, string $userId): void
    {
        $this
            ->database
            ->delete($this->tableName, ['itemName' => $itemName, 'userId' => $userId])
            ->run();
    }

    public function removeByUserId(string $userId): void
    {
        $this
            ->database
            ->delete($this->tableName, ['userId' => $userId])
            ->run();
    }

    public function removeByItemName(string $itemName): void
    {
        $this
            ->database
            ->delete($this->tableName, ['itemName' => $itemName])
            ->run();
    }

    public function clear(): void
    {
        $this
            ->database
            ->delete($this->tableName)
            ->run();
    }
}
