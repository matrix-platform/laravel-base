<?php //>

namespace Tests\Feature\Http\Controllers;

use MatrixPlatform\Models\City;
use MatrixPlatform\Models\CityArea;
use MatrixPlatform\Models\Menu;
use Tests\FeatureTestCase;

class CommonControllerTest extends FeatureTestCase {

    private function child(Menu $parent, string $title, int $ranking): Menu {
        $menu = $this->node($title, $ranking);

        $menu->parent_id = $parent->id;

        $menu->save();

        return $menu;
    }

    private function node(string $title, int $ranking): Menu {
        $menu = new Menu();

        $menu->title = $title;
        $menu->ranking = $ranking;
        $menu->enable_time = now()->subDay();

        $menu->save();

        return $menu;
    }

    public function test_the_city_endpoint_answers_without_any_identity(): void {
        $taipei = new City();

        $taipei->title = 'Taipei';
        $taipei->ranking = 100;

        $taipei->save();

        $area = new CityArea();

        $area->city_id = $taipei->id;
        $area->title = 'Daan';
        $area->post_code = '106';
        $area->ranking = 100;

        $area->save();

        $response = $this->postJson('api/common/city');

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
            'data' => [
                ['id' => $taipei->id, 'title' => 'Taipei', 'areas' => [['id' => $area->id, 'title' => 'Daan', 'post_code' => '106']]]
            ]
        ]);
    }

    public function test_the_menu_endpoint_answers_without_any_identity(): void {
        $this->node('root', 100);

        $response = $this->postJson('api/common/menu');

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_the_menu_endpoint_without_a_body_returns_the_whole_tree(): void {
        $root = $this->node('root', 100);

        $this->child($root, 'child', 100);

        $response = $this->postJson('api/common/menu');

        $response->assertOk();
        $this->assertSame('root', $response->json('data.0.title'));
        $this->assertSame('child', $response->json('data.0.children.0.title'));
    }

    public function test_the_menu_endpoint_with_a_parent_returns_that_subtree(): void {
        $root = $this->node('root', 100);

        $this->child($root, 'child', 100);

        $response = $this->postJson('api/common/menu', ['parent' => $root->id]);

        $response->assertOk();
        $this->assertSame('child', $response->json('data.0.title'));
        $this->assertNull($response->json('data.1'));
    }

    public function test_a_non_integer_parent_is_rejected_as_a_validation_failure(): void {
        $response = $this->postJson('api/common/menu', ['parent' => 'not-a-number']);

        $response->assertJson(['success' => false, 'code' => 422, 'error' => 'validation-failed']);
    }

    public function test_a_refused_request_comes_back_inside_the_error_envelope(): void {
        $response = $this->postJson('api/common/menu', ['parent' => ['an-array']]);

        $response->assertJsonStructure(['success', 'code', 'error', 'message']);
        $this->assertFalse($response->json('success'));
    }

    public function test_the_error_message_follows_the_requested_locale(): void {
        $fallback = $this->postJson('api/common/menu', ['parent' => 'not-a-number']);
        $localised = $this->postJson('api/common/menu', ['parent' => 'not-a-number'], ['Matrix-Locale' => 'tw']);

        $this->assertNotSame($fallback->json('message'), $localised->json('message'));
    }

}
