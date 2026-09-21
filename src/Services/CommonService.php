<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Columns\Declarations\Variant;
use MatrixPlatform\Models\Block;
use MatrixPlatform\Models\BlockItem;
use MatrixPlatform\Models\City;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Models\Page;
use MatrixPlatform\Support\Subject;
use MatrixPlatform\Support\Variants;

class CommonService {

    public function __construct(private Subject $subject, private Variants $variants) {}

    /**
     * @return list<array{id: int, title: ?string, areas: list<array{id: int, title: ?string, post_code: string}>}>
     */
    public function city(): array {
        $cities = City::query()
            ->with('areas')
            ->orderBy('ranking')
            ->get();
        $payload = [];

        foreach ($cities as $city) {
            $payload[] = ['id' => $city->id, 'title' => $this->subject->title($city), 'areas' => $this->areas($city)];
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function menu(?int $parent): array {
        $nodes = Menu::query()
            ->whereActive()
            ->orderBy('ranking')
            ->get()
            ->all();

        if ($parent !== null && !$this->rooted($nodes, null, $parent)) {
            return [];
        }

        return $this->branch($nodes, $parent);
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $path): array {
        $page = Page::query()
            ->whereActive()
            ->where('path', $path)
            ->first();

        if (!$page instanceof Page) {
            error('data-not-found', 404);
        }

        $image = $this->translated($page, 'og_image');

        return [
            'id' => $page->id,
            'path' => $page->path,
            'title' => $this->subject->title($page),
            'seo_title' => $this->translated($page, 'seo_title'),
            'seo_description' => $this->translated($page, 'seo_description'),
            'og_image' => is_array($image) ? $image : [],
            'data' => $this->localized($this->variants->variant('page-data', $page, null), $page->data),
            'children' => $this->blocks($page)
        ];
    }

    /**
     * @return list<array{id: int, title: ?string, post_code: string}>
     */
    private function areas(City $city): array {
        $payload = [];

        foreach ($city->areas as $area) {
            $payload[] = ['id' => $area->id, 'title' => $this->subject->title($area), 'post_code' => $area->post_code];
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(Page $page): array {
        $blocks = Block::query()
            ->whereActive()
            ->where('page_id', $page->id)
            ->orderBy('ranking')
            ->get();
        $items = $this->items($blocks->pluck('type', 'id')->all());
        $payload = [];

        foreach ($blocks as $block) {
            $payload[] = [
                'id' => $block->id,
                'type' => $block->type,
                'title' => $this->subject->title($block),
                'data' => $this->localized($this->variants->of('block-data', $block->type), $block->data),
                'children' => array_get_value($items, $block->id, [])
            ];
        }

        return $payload;
    }

    /**
     * @param array<int, Menu> $nodes
     * @return list<array<string, mixed>>
     */
    private function branch(array $nodes, ?int $parent): array {
        $branch = [];

        foreach ($nodes as $node) {
            if ($node->parent_id === $parent) {
                $branch[] = [
                    'id' => $node->id,
                    'title' => $this->subject->title($node),
                    'data' => $this->localized($this->variants->variant('menu-data', $node, null), $node->data),
                    'children' => $this->branch($nodes, $node->id)
                ];
            }
        }

        return $branch;
    }

    /**
     * @param array<int, string> $types
     * @return array<int, list<array<string, mixed>>>
     */
    private function items(array $types): array {
        $rows = BlockItem::query()
            ->whereActive()
            ->whereIn('block_id', array_keys($types))
            ->orderBy('ranking')
            ->get();
        $grouped = [];

        foreach ($rows as $item) {
            $grouped[$item->block_id][] = [
                'id' => $item->id,
                'title' => $this->subject->title($item),
                'data' => $this->localized($this->variants->of('block-item-data', array_get_value($types, $item->block_id)), $item->data),
                'children' => []
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>|object
     */
    private function localized(?Variant $variant, mixed $data): array|object {
        $values = is_array($data) ? $data : [];
        $locale = app()->getLocale();

        foreach ($variant === null ? [] : $variant->definitions() as $field => $definition) {
            if (!$definition->translatable) {
                continue;
            }

            $value = array_get_value($values, $field);
            $values[$field] = is_array($value) ? array_get_value($value, $locale) : null;
        }

        return $values === [] ? (object) [] : $values;
    }

    /**
     * @param array<int, Menu> $nodes
     */
    private function rooted(array $nodes, ?int $parent, int $target): bool {
        foreach ($nodes as $node) {
            if ($node->parent_id === $parent && ($node->id === $target || $this->rooted($nodes, $node->id, $target))) {
                return true;
            }
        }

        return false;
    }

    private function translated(Model $model, string $field): mixed {
        return $model->getAttribute("{$field}__" . app()->getLocale());
    }

}
