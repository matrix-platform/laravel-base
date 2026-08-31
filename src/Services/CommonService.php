<?php //>

namespace MatrixPlatform\Services;

use MatrixPlatform\Models\City;
use MatrixPlatform\Models\Menu;
use MatrixPlatform\Support\Subject;

class CommonService {

    public function __construct(private Subject $subject) {}

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
     * @param array<int, Menu> $nodes
     * @return list<array<string, mixed>>
     */
    private function branch(array $nodes, ?int $parent): array {
        $branch = [];

        foreach ($nodes as $node) {
            if ($node->parent_id === $parent) {
                $branch[] = ['id' => $node->id, 'title' => $this->subject->title($node), 'data' => $node->data, 'children' => $this->branch($nodes, $node->id)];
            }
        }

        return $branch;
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

}
