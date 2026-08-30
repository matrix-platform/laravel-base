<?php //>

namespace MatrixPlatform\Services\Admin;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\MediaMeasurer;
use MatrixPlatform\Support\RollbackCallbacks;

class DriveService {

    private const FOLDER = 'drive/';

    public function __construct(private DrivePermissionService $permission) {}

    /**
     * @return Collection<int, DriveNode>
     */
    public function children(DriveNode $folder, User $user): Collection {
        if (!$this->permission->allowed($folder, $user)) {
            error('permission-denied', 403);
        }

        return DriveNode::query()
            ->where('parent_id', $folder->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }

    public function createFolder(DriveNode $parent, string $name, User $user): DriveNode {
        if (!$this->permission->allowed($parent, $user)) {
            error('permission-denied', 403);
        }

        if ($this->exists($parent->id, $name)) {
            error('name-already-exists');
        }

        $node = new DriveNode();

        $node->parent_id = $parent->id;
        $node->type = DriveNodeType::Folder;
        $node->name = $name;

        $node->save();

        return $node;
    }

    public function deletedBy(DriveNode $node): ?int {
        if ($node->deleted_at === null) {
            return null;
        }

        return ManipulationLog::query()
            ->where('data_type', $node->getTable())
            ->where('data_id', $node->id)
            ->where('type', ManipulationType::Deleted)
            ->latest('id')
            ->first()
            ?->creator_id;
    }

    /**
     * @param Collection<int, DriveNode> $nodes
     * @return array<int, int>
     */
    public function deletedByMany(Collection $nodes): array {
        if ($nodes->isEmpty()) {
            return [];
        }

        return ManipulationLog::query()
            ->where('data_type', $nodes->first()->getTable())
            ->whereIn('data_id', $nodes->pluck('id'))
            ->where('type', ManipulationType::Deleted)
            ->orderBy('id')
            ->get()
            ->groupBy('data_id')
            ->map(fn ($logs) => $logs->last()?->creator_id)
            ->filter(fn (?int $creatorId) => $creatorId !== null)
            ->all();
    }

    public function disk(): string {
        return config()->string('matrix.drive-disk');
    }

    public function find(string $id, bool $withTrashed = false): DriveNode {
        if (!ctype_digit($id)) {
            throw (new ModelNotFoundException())->setModel(DriveNode::class);
        }

        $query = $withTrashed ? DriveNode::withTrashed() : DriveNode::query();

        return $query->findOrFail((int) $id);
    }

    /**
     * @return ?DriveNode
     */
    public function group(User $user): ?DriveNode {
        if ($user->group_id === null) {
            return null;
        }

        return $this->findOrCreateAnchor($user->group_id, function () use ($user): string {
            $title = Group::query()->find($user->group_id)?->title;

            return $title === null ? "group-{$user->group_id}" : $title;
        });
    }

    public function home(User $user): DriveNode {
        return $this->findOrCreateAnchor($user->id, fn () => $user->username);
    }

    public function location(DriveNode $node): string {
        return self::FOLDER . $node->path;
    }

    public function move(DriveNode $node, DriveNode $newParent, User $user): void {
        if ($this->isAnchor($node)) {
            error('drive-anchor-immutable');
        }

        if (!$this->permission->allowed($node, $user) || !$this->permission->allowed($newParent, $user)) {
            error('permission-denied', 403);
        }

        if ($newParent->id === $node->id || $this->isDescendant($newParent, $node)) {
            error('invalid-move-target');
        }

        if ($this->exists($newParent->id, $node->name)) {
            error('name-already-exists');
        }

        $node->parent_id = $newParent->id;

        $node->save();
    }

    /**
     * @return Collection<int, DriveNode>
     */
    public function path(DriveNode $node, User $user): Collection {
        if (!$this->permission->visible($node, $user)) {
            error('permission-denied', 403);
        }

        $ancestors = [];
        $current = $node;

        while (($current = $this->permission->parent($current)) !== null) {
            array_unshift($ancestors, $current);
        }

        return new Collection($ancestors);
    }

    public function rename(DriveNode $node, string $name, ?string $description, User $user): void {
        if (!$this->permission->allowed($node, $user)) {
            error('permission-denied', 403);
        }

        if ($name !== $node->name && $node->parent_id !== null && $this->exists($node->parent_id, $name)) {
            error('name-already-exists');
        }

        $node->name = $name;
        $node->description = $description;

        $node->save();
    }

    public function restore(DriveNode $node, User $user): void {
        if (!$this->permission->allowed($node, $user)) {
            error('permission-denied', 403);
        }

        if ($node->parent_id !== null && $this->exists($node->parent_id, $node->name)) {
            error('name-already-exists');
        }

        $node->restore();
    }

    public function root(): DriveNode {
        return DriveNode::query()->findOrFail(DriveNode::ROOT);
    }

    public function trash(DriveNode $node, User $user): void {
        if ($this->isAnchor($node)) {
            error('drive-anchor-immutable');
        }

        if (!$this->permission->allowed($node, $user)) {
            error('permission-denied', 403);
        }

        $node->delete();
    }

    /**
     * @return Collection<int, DriveNode>
     */
    public function trashed(User $user, ?int $days, bool $all): Collection {
        $limit = $days === null ? intval(cfg('drive.trash-default-days')) : $days;
        $cutoff = $all ? null : now()->subDays($limit);

        return DriveNode::onlyTrashed()
            ->when($cutoff !== null, fn (Builder $query) => $query->where('deleted_at', '>=', $cutoff))
            ->get()
            ->filter(fn (DriveNode $node) => $this->permission->visible($node, $user))
            ->values();
    }

    public function upload(DriveNode $parent, UploadedFile $file, User $user): DriveNode {
        if (!$this->permission->allowed($parent, $user)) {
            error('permission-denied', 403);
        }

        $hash = hash_file('sha256', $file->getPathname());

        if ($hash === false) {
            error('request-failed');
        }

        $size = $file->getSize();
        $disk = $this->disk();
        $existing = (bool) cfg('drive.deduplicate') ? $this->duplicate($hash, $size) : null;

        if ($existing !== null && Storage::disk($disk)->exists($this->location($existing))) {
            $path = $existing->path;
        } else {
            $path = $this->store($file, $disk);

            app(RollbackCallbacks::class)->register(fn () => Storage::disk($disk)->delete(self::FOLDER . $path));
        }

        $mimeType = $file->getMimeType();
        $measured = app(MediaMeasurer::class)->measure($mimeType, $file->getPathname());

        $node = new DriveNode();

        $node->parent_id = $parent->id;
        $node->type = DriveNodeType::File;
        $node->name = $this->uniqueName($parent, $file->getClientOriginalName());
        $node->hash = $hash;
        $node->path = $path;
        $node->size = $size;
        $node->mime_type = $mimeType;
        $node->width = $measured['width'];
        $node->height = $measured['height'];
        $node->seconds = $measured['seconds'];

        $node->save();

        return $node;
    }

    /**
     * @return ?DriveNode
     */
    private function duplicate(string $hash, int $size): ?DriveNode {
        return DriveNode::withTrashed()
            ->where('type', DriveNodeType::File)
            ->where('hash', $hash)
            ->where('size', $size)
            ->whereNotNull('path')
            ->first();
    }

    private function exists(int $parentId, string $name): bool {
        return DriveNode::query()
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function findOrCreateAnchor(int $id, Closure $name): DriveNode {
        $node = DriveNode::query()->find($id);

        if ($node !== null) {
            return $node;
        }

        $node = new DriveNode();

        $node->id = $id;
        $node->type = DriveNodeType::Root;
        $node->name = $name();

        $node->save();

        return $node;
    }

    private function isAnchor(DriveNode $node): bool {
        return $node->type === DriveNodeType::Root;
    }

    private function isDescendant(DriveNode $candidate, DriveNode $ancestor): bool {
        $current = $candidate;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $ancestor->id) {
                return true;
            }

            $current = $this->permission->parent($current);

            if ($current === null) {
                return false;
            }
        }

        return false;
    }

    private function store(UploadedFile $file, string $disk): string {
        $path = date('Ym') . '/' . Str::random(32);

        Storage::disk($disk)->putFileAs(self::FOLDER, $file, $path);

        return $path;
    }

    private function uniqueName(DriveNode $parent, string $name): string {
        if (!$this->exists($parent->id, $name)) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));
        $index = 1;

        do {
            $candidate = $extension === '' ? "{$base} ({$index})" : "{$base} ({$index}).{$extension}";
            $index++;
        } while ($this->exists($parent->id, $candidate));

        return $candidate;
    }

}
