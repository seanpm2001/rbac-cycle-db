<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Cycle\ItemTreeTraversal;

use Yiisoft\Rbac\Cycle\ItemsStorage;

/**
 * An interface for retrieving hierarchical RBAC items' data in a more efficient way depending on used RDBMS and their
 * versions.
 *
 * @internal
 *
 * @psalm-import-type RawItem from ItemsStorage
 */
interface ItemTreeTraversalInterface
{
    /**
     * Get all parent rows for an item by the given name.
     *
     * @param string $name Item name.
     *
     * @return array Flat list of all parents.
     * @psalm-return RawItem[]
     */
    public function getParentRows(string $name): array;

    /**
     * Get all children rows for an item by the given name.
     *
     * @param string $name Item name.
     *
     * @return array Flat list of all children.
     * @psalm-return RawItem[]
     */
    public function getChildrenRows(string $name): array;

    public function getChildPermissionRows(string $name): array;

    public function getChildRoleRows(string $name): array;

    public function hasChild(string $parentName, string $childName): bool;
}
