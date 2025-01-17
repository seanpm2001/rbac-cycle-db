<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Cycle\ItemTreeTraversal;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Cycle\Database\Query\SelectQuery;
use Cycle\Database\StatementInterface;
use Yiisoft\Rbac\Cycle\ItemsStorage;
use Yiisoft\Rbac\Item;

/**
 * A RBAC item tree traversal strategy based on CTE (common table expression). Uses `WITH` expression to form a
 * recursive query. The base queries are unified as much possible to work for all RDBMS supported by Cycle with minimal
 * differences.
 *
 * @internal
 *
 * @psalm-import-type RawItem from ItemsStorage
 */
abstract class CteItemTreeTraversal implements ItemTreeTraversalInterface
{
    /**
     * @param DatabaseInterface $database Cycle database instance.
     *
     * @param string $tableName A name of the table for storing RBAC items.
     * @psalm-param non-empty-string $tableName
     *
     * @param string $childrenTableName A name of the table for storing relations between RBAC items.
     * @psalm-param non-empty-string $childrenTableName
     */
    public function __construct(
        protected DatabaseInterface $database,
        protected string $tableName,
        protected string $childrenTableName,
    ) {
    }

    public function getParentRows(string $name): array
    {
        $baseOuterQuery = $this->database->select('item.*')->where('item.name', '!=', $name);

        /** @psalm-var RawItem[] */
        return $this->getRowsStatement($name, baseOuterQuery: $baseOuterQuery)->fetchAll();
    }

    public function getChildrenRows(string $name): array
    {
        $baseOuterQuery = $this->database->select('item.*')->where('item.name', '!=', $name);

        /** @psalm-var RawItem[] */
        return $this->getRowsStatement($name, baseOuterQuery: $baseOuterQuery, areParents: false)->fetchAll();
    }

    public function getChildPermissionRows(string $name): array
    {
        $baseOuterQuery = $this
            ->database
            ->select('item.*')
            ->where('item.name', '!=', $name)
            ->andWhere(['item.type' => Item::TYPE_PERMISSION]);

        /** @psalm-var RawItem[] */
        return $this->getRowsStatement($name, baseOuterQuery: $baseOuterQuery, areParents: false)->fetchAll();
    }

    public function getChildRoleRows(string $name): array
    {
        $baseOuterQuery = $this
            ->database
            ->select('item.*')
            ->where('item.name', '!=', $name)
            ->andWhere(['item.type' => Item::TYPE_ROLE]);

        /** @psalm-var RawItem[] */
        return $this->getRowsStatement($name, baseOuterQuery: $baseOuterQuery, areParents: false)->fetchAll();
    }

    public function hasChild(string $parentName, string $childName): bool
    {
        /**
         * @infection-ignore-all
         * - ArrayItemRemoval, select.
         */
        $baseOuterQuery = $this
            ->database
            ->select([new Fragment('1 AS item_child_exists')])
            ->andWhere(['item.name' => $childName]);
        /** @psalm-var array<0, 1>|false $result */
        $result = $this->getRowsStatement($parentName, baseOuterQuery: $baseOuterQuery, areParents: false)->fetch();

        return $result !== false;
    }

    /**
     * Gets `WITH` expression used in DB query.
     *
     * @infection-ignore-all
     * - ProtectedVisibility.
     *
     * @return string `WITH` expression.
     */
    protected function getWithExpression(): string
    {
        return 'WITH RECURSIVE';
    }

    private function getRowsStatement(
        string $name,
        SelectQuery $baseOuterQuery,
        bool $areParents = true,
    ): StatementInterface {
        if ($areParents) {
            $cteSelectRelationName = 'parent';
            $cteConditionRelationName = 'child';
            $cteName = 'parent_of';
            $cteParameterName = 'child_name';
        } else {
            $cteSelectRelationName = 'child';
            $cteConditionRelationName = 'parent';
            $cteName = 'child_of';
            $cteParameterName = 'parent_name';
        }

        $cteSelectItemQuery = $this
            ->database
            ->select('name')
            ->from($this->tableName)
            ->where(['name' => $name]);
        $cteSelectRelationQuery = $this
            ->database
            ->select($cteSelectRelationName)
            ->from("$this->childrenTableName AS item_child_recursive")
            ->innerJoin($cteName)
            ->on("item_child_recursive.$cteConditionRelationName", "$cteName.$cteParameterName");
        $outerQuery = $baseOuterQuery
            ->from($cteName)
            ->leftJoin($this->tableName, 'item')
            ->on('item.name', "$cteName.$cteParameterName");
        $sql = "{$this->getWithExpression()} $cteName($cteParameterName) AS (
            $cteSelectItemQuery
            UNION ALL
            $cteSelectRelationQuery
        )
        $outerQuery";

        return $this->database->query($sql);
    }
}
