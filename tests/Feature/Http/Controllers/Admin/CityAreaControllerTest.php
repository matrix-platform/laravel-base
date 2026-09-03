<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\CityArea;
use MatrixPlatform\Models\User;
use Tests\Factories\CityAreaFactory;
use Tests\Factories\CityFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class CityAreaControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_a_nested_lists_row_carries_the_foreign_key_without_exposing_it_as_a_column(): void {
        $taipei = CityFactory::new()->createOne();

        CityAreaFactory::new()->createOne(['city_id' => $taipei->id]);

        $response = $this->send("admin/city/{$taipei->id}/area");

        $this->assertSame($taipei->id, $response->json('data.rows.0.city_id'));
        $this->assertNotContains('city_id', array_column($response->json('data.columns'), 'name'));
    }

    public function test_a_single_areas_get_response_carries_the_foreign_key(): void {
        $taipei = CityFactory::new()->createOne();
        $area = CityAreaFactory::new()->createOne(['city_id' => $taipei->id]);

        $response = $this->send("admin/city/{$taipei->id}/area/{$area->id}");

        $this->assertSame($taipei->id, $response->json('data.data.city_id'));
    }

    public function test_a_nested_list_is_scoped_to_its_city(): void {
        $taipei = CityFactory::new()->createOne();
        $taichung = CityFactory::new()->createOne();

        CityAreaFactory::new()->createOne(['city_id' => $taipei->id, 'title__tw' => 'mine', 'title__en' => 'mine']);
        CityAreaFactory::new()->createOne(['city_id' => $taichung->id, 'title__tw' => 'theirs', 'title__en' => 'theirs']);

        $response = $this->send("admin/city/{$taipei->id}/area");

        $this->assertSame(['mine'], array_column($response->json('data.rows'), 'title'));
    }

    public function test_a_nested_insert_takes_the_city_from_the_route(): void {
        $taipei = CityFactory::new()->createOne();

        $response = $this->send("admin/city/{$taipei->id}/area/insert", ['title__tw' => 'Xinyi', 'title__en' => 'Xinyi', 'post_code' => '110']);

        $this->assertSame($taipei->id, CityArea::query()->findOrFail(intval($response->json('data.id')))->city_id);
    }

    public function test_a_nested_get_cannot_reach_another_citys_area(): void {
        $taipei = CityFactory::new()->createOne();
        $taichung = CityFactory::new()->createOne();

        $area = CityAreaFactory::new()->createOne(['city_id' => $taichung->id]);

        $response = $this->send("admin/city/{$taipei->id}/area/{$area->id}");

        $response->assertJson(['success' => false, 'code' => 404, 'error' => 'data-not-found']);
    }

    public function test_the_listings_subtitle_carries_the_city_title(): void {
        $taipei = CityFactory::new()->createOne(['title__tw' => 'Taipei', 'title__en' => 'Taipei']);

        $response = $this->send("admin/city/{$taipei->id}/area");

        $response->assertJsonPath('data.subtitle', 'Taipei');
    }

    public function test_the_breadcrumb_carries_the_city_title(): void {
        $taipei = CityFactory::new()->createOne(['title__tw' => 'Taipei', 'title__en' => 'Taipei']);

        $response = $this->send("admin/city/{$taipei->id}/area");

        $this->assertContains('Taipei', array_column($response->json('data.breadcrumbs'), 'label'));
    }

}
