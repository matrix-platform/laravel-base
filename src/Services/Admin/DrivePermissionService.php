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
     * @return ?list<DriveNode>
     */
    public function ancestors(DriveNode $node, User $user): ?array {
        $walked = $this->walk($node, includeTrashed: true);

        return $this->matches($walked['anchor'], $user) ? array_reverse($walked['chain']) : null;
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
        return $this->walk($node, $includeTrashed)['anchor'];
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

    /**
     * @return array{anchor: ?DriveNode, chain: list<DriveNode>}
     */
    private function walk(DriveNode $node, bool $includeTrashed): array {
        $chain = [];

        while ($node->type !== DriveNodeType::Root) {
            if ($node->parent_id === null) {
                return ['anchor' => null, 'chain' => $chain];
            }

            $parent = $includeTrashed ? DriveNode::withTrashed()->find($node->parent_id) : DriveNode::query()->find($node->parent_id);

            if ($parent === null) {
                return ['anchor' => null, 'chain' => $chain];
            }

            $chain[] = $parent;
            $node = $parent;
        }

        return ['anchor' => $node, 'chain' => $chain];
    }

}
