<?php //>

namespace MatrixPlatform\Services\Admin;

use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\User;

class DrivePermissionService {

    public function allowed(DriveNode $node, User $user): bool {
        return $this->matches($this->anchor($node, includeTrashed: false), $user);
    }

    /**
     * The trashed-inclusive parent of $node.
     */
    public function parent(DriveNode $node): ?DriveNode {
        return $node->parent_id === null ? null : DriveNode::withTrashed()->find($node->parent_id);
    }

    public function visible(DriveNode $node, User $user): bool {
        return $this->matches($this->anchor($node, includeTrashed: true), $user);
    }

    private function anchor(DriveNode $node, bool $includeTrashed): ?DriveNode {
        while ($node->type !== DriveNodeType::Root) {
            if ($node->parent_id === null) {
                return null;
            }

            $parent = $includeTrashed ? DriveNode::withTrashed()->find($node->parent_id) : DriveNode::query()->find($node->parent_id);

            if ($parent === null) {
                return null;
            }

            $node = $parent;
        }

        return $node;
    }

    private function matches(?DriveNode $anchor, User $user): bool {
        if ($anchor === null) {
            return false;
        }

        if ($user->id === User::ROOT) {
            return true;
        }

        return $anchor->id === $user->id
            || ($user->group_id !== null && $anchor->id === $user->group_id)
            || $anchor->id === DriveNode::ROOT;
    }

}
