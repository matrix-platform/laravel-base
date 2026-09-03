<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\City;
use MatrixPlatform\Models\User;
use Tests\Factories\CityAreaFactory;
use Tests\Factories\CityFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class CityControllerTest extends FeatureTestCase {

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

    public function test_the_listing_reports_each_citys_title_and_area_count(): void {
        $city = CityFactory::new()->createOne(['title__tw' => 'Taipei', 'title__en' => 'Taipei']);

        CityAreaFactory::new()->createOne(['city_id' => $city->id]);
        CityAreaFactory::new()->createOne(['city_id' => $city->id]);

        $response = $this->send('admin/city');

        $response->assertJsonPath('data.rows.0.title', 'Taipei');
        $response->assertJsonPath('data.rows.0.areas_count', 2);
    }

    public function test_the_new_form_only_exposes_the_title(): void {
        $names = array_column($this->send('admin/city/new')->json('data.columns'), 'name');

        $this->assertSame(['title'], $names);
    }

    public function test_inserting_creates_the_city(): void {
        $this->send('admin/city/insert', ['title__tw' => 'Taipei', 'title__en' => 'Taipei'])->assertJsonPath('success', true);

        $this->assertSame(1, City::query()->where('title__en', 'Taipei')->count());
    }

    public function test_updating_renames_the_city(): void {
        $city = CityFactory::new()->createOne(['title__tw' => 'Taipei', 'title__en' => 'Taipei']);

        $this->send("admin/city/{$city->id}/update", ['title__tw' => 'New Taipei', 'title__en' => 'New Taipei'])->assertJsonPath('success', true);

        $this->assertSame('New Taipei', $city->refresh()->title__en);
    }

    public function test_deleting_removes_the_city(): void {
        $city = CityFactory::new()->createOne();

        $this->send('admin/city/delete', ['id' => [$city->id]])->assertJsonPath('success', true);

        $this->assertNull(City::query()->find($city->id));
    }

    public function test_the_default_sorting_follows_ranking(): void {
        $second = CityFactory::new()->createOne(['ranking' => 100]);
        $first = CityFactory::new()->createOne(['ranking' => 50]);

        $response = $this->send('admin/city');

        $this->assertSame([$first->id, $second->id], array_column($response->json('data.rows'), 'id'));
    }

    public function test_the_sort_listing_is_reachable(): void {
        $city = CityFactory::new()->createOne();

        $this->send('admin/city/sort')->assertJsonPath('data.rows.0.id', $city->id);
    }

}
