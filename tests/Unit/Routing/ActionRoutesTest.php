<?php //>

namespace Tests\Unit\Routing;

use MatrixPlatform\Routing\ActionRoutes;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\LeafController;
use Tests\Stubs\StubController;

class ActionRoutesTest extends TestCase {

    /**
     * @param class-string $controller
     * @return list<string>
     */
    private function inherited(string $controller): array {
        return array_column(ActionRoutes::resolve($controller, null), 'path');
    }

    /**
     * @return list<string>
     */
    private function paths(?string $scope): array {
        return array_column(ActionRoutes::resolve(StubController::class, $scope), 'path');
    }

    public function test_a_method_without_the_attribute_is_not_routed(): void {
        $this->assertNotContains('not-an-action', $this->paths(null));
    }

    public function test_the_path_defaults_to_the_kebab_case_method_name(): void {
        $paths = $this->paths(null);

        $this->assertContains('ping-pong', $paths);
        $this->assertNotContains('pingPong', $paths);
    }

    public function test_an_explicit_path_wins_over_the_method_name(): void {
        $paths = $this->paths(null);

        $this->assertContains('custom-path', $paths);
        $this->assertNotContains('named', $paths);
    }

    public function test_an_empty_path_stays_empty(): void {
        $paths = $this->paths(null);

        $this->assertContains('', $paths);
        $this->assertNotContains('nameless', $paths);
    }

    public function test_the_scope_filters_the_result(): void {
        $this->assertSame(['open'], $this->paths('anonymous'));
        $this->assertNotContains('open', $this->paths(null));
    }

    public function test_the_result_is_sorted_by_path(): void {
        $paths = $this->paths(null);
        $sorted = $paths;

        sort($sorted);

        $this->assertSame($sorted, $paths);
    }

    public function test_an_override_without_the_attribute_keeps_the_inherited_route(): void {
        $this->assertContains('plain', $this->inherited(LeafController::class));
    }

    public function test_the_nearest_ancestor_wins_over_the_topmost_declaration(): void {
        $paths = $this->inherited(LeafController::class);

        $this->assertContains('middle-path', $paths);
        $this->assertNotContains('custom-path', $paths);
    }

    public function test_a_non_public_method_is_not_routed(): void {
        $this->assertNotContains('guarded', $this->inherited(LeafController::class));
    }

    public function test_the_middleware_travels_with_the_route(): void {
        $middleware = array_column(ActionRoutes::resolve(StubController::class, null), 'middleware', 'path');

        $this->assertSame('throttle:1,1', $middleware['limited']);
        $this->assertNull($middleware['plain']);
    }

}
