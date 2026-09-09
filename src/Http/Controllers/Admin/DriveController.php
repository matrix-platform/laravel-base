<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Models\DriveNode;
use MatrixPlatform\Models\DriveNodeType;
use MatrixPlatform\Services\Admin\DriveService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriveController extends BaseController {

    public function __construct(private DriveService $service) {}

    /**
     * @return list<array<string, mixed>>
     */
    #[Action('{id}/children')]
    public function children(Request $request): array {
        $nodes = $this->service->children($this->node($request), actor()->requireUser());
        $createdBy = $this->service->createdByMany($nodes);
        $payload = [];

        foreach ($nodes as $node) {
            $payload[] = $this->present($node, $createdBy);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/delete')]
    public function delete(Request $request): array {
        $node = $this->node($request);

        $this->service->trash($node, actor()->requireUser());

        return $this->present($node);
    }

    #[Action('{id}/download')]
    public function download(Request $request): StreamedResponse {
        $node = $this->node($request, withTrashed: true);

        $this->service->requireAllowed($node, actor()->requireUser());

        if ($node->type !== DriveNodeType::File) {
            error('data-not-found', 404);
        }

        return $this->stream($this->service->disk(), $this->service->location($node), $node->name, $node->mime_type);
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/folder')]
    public function folder(Request $request): array {
        $request->validate([
            'name' => ['required', 'string']
        ]);

        return $this->present($this->service->createFolder($this->node($request), $request->string('name')->value(), actor()->requireUser()));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}')]
    public function get(Request $request): array {
        return $this->present($this->node($request));
    }

    /**
     * @return ?array<string, mixed>
     */
    #[Action]
    public function group(): ?array {
        $node = $this->service->group(actor()->requireUser());

        return $node === null ? null : $this->present($node);
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function home(): array {
        return $this->present($this->service->home(actor()->requireUser()));
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/move')]
    public function move(Request $request): array {
        $request->validate([
            'parent_id' => ['required', 'integer']
        ]);

        $node = $this->node($request);
        $newParent = $this->service->find((string) $request->integer('parent_id'));

        $this->service->move($node, $newParent, actor()->requireUser());

        return $this->present($node);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Action('{id}/path')]
    public function path(Request $request): array {
        $ancestors = $this->service->path($this->node($request, withTrashed: true), actor()->requireUser());
        $createdBy = $this->service->createdByMany($ancestors);
        $deletedBy = $this->service->deletedByMany($ancestors);
        $payload = [];

        foreach ($ancestors as $ancestor) {
            $payload[] = $this->present($ancestor, $createdBy, $deletedBy);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/rename')]
    public function rename(Request $request): array {
        $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string']
        ]);

        $node = $this->node($request);

        $this->service->rename($node, $request->string('name')->value(), $this->optionalString($request, 'description'), actor()->requireUser());

        return $this->present($node);
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/restore')]
    public function restore(Request $request): array {
        $node = $this->node($request, withTrashed: true);

        $this->service->restore($node, actor()->requireUser());

        return $this->present($node);
    }

    /**
     * @return array<string, mixed>
     */
    #[Action]
    public function root(): array {
        return $this->present($this->service->root());
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Action]
    public function trashed(Request $request): array {
        $nodes = $this->service->trashed(actor()->requireUser(), $this->optional($request, 'days'), $request->boolean('all'));
        $createdBy = $this->service->createdByMany($nodes);
        $deletedBy = $this->service->deletedByMany($nodes);
        $payload = [];

        foreach ($nodes as $node) {
            $payload[] = $this->present($node, $createdBy, $deletedBy);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    #[Action('{id}/upload')]
    public function upload(Request $request): array {
        $request->validate([
            'file' => ['required', 'file']
        ]);

        $file = $request->file('file');

        if (!$file instanceof UploadedFile) {
            error('validation-failed', 422);
        }

        return $this->present($this->service->upload($this->node($request), $file, actor()->requireUser()));
    }

    /**
     * @param ?array<int, string> $map
     */
    private function mapped(DriveNode $node, ?array $map, Closure $fallback): ?string {
        if ($map === null) {
            return $fallback();
        }

        return array_key_exists($node->id, $map) ? $map[$node->id] : null;
    }

    private function node(Request $request, bool $withTrashed = false): DriveNode {
        return $this->service->find((string) $request->route('id'), $withTrashed);
    }

    private function optional(Request $request, string $key): ?int {
        return $request->filled($key) ? $request->integer($key) : null;
    }

    /**
     * @param array<int, string> $createdByMap
     * @param array<int, string> $deletedByMap
     * @return array<string, mixed>
     */
    private function present(DriveNode $node, ?array $createdByMap = null, ?array $deletedByMap = null): array {
        return [
            ...array_intersect_key($node->toArray(), array_flip([
                'id', 'parent_id', 'type', 'name', 'size', 'description', 'mime_type', 'width', 'height', 'seconds', 'create_time', 'update_time', 'deleted_at'
            ])),
            'created_by' => $this->mapped($node, $createdByMap, fn (): ?string => $this->service->createdBy($node)),
            'deleted_by' => $this->mapped($node, $deletedByMap, fn (): ?string => $this->service->deletedBy($node))
        ];
    }

}
