<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Models\Page;
use MatrixPlatform\Models\User;
use Tests\Factories\PageFactory;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class PageControllerTest extends FeatureTestCase {

    private string $token;

    protected function setUp(): void {
        parent::setUp();

        $this->token = UserFactory::new()->createOne(['id' => User::ROOT])->createToken();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(string $path, array $overrides = []): array {
        return array_merge([
            'path' => $path,
            'title' => "{$path} TW",
            'seo_title__tw' => null,
            'seo_title__en' => null,
            'seo_description__tw' => null,
            'seo_description__en' => null,
            'og_image__tw' => null,
            'og_image__en' => null,
            'data' => null,
            'enable_time' => null,
            'disable_time' => null
        ], $overrides);
    }

    private function page(string $path, int $ranking = 100): Page {
        return PageFactory::new()->createOne(['path' => $path, 'title' => $path, 'ranking' => $ranking]);
    }

    /**
     * @param array<string, mixed> $input
     * @return TestResponse<JsonResponse>
     */
    private function send(string $uri, array $input = []): TestResponse {
        return $this->withToken($this->token)->postJson($uri, $input);
    }

    public function test_the_listing_reports_the_path_the_title_and_the_block_count(): void {
        $this->page('about-us');

        $response = $this->send('admin/page');

        $this->assertSame(['path', 'title', 'blocks_count'], array_column($response->json('data.columns'), 'name'));
        $this->assertSame('about-us', $response->json('data.rows.0.path'));
        $this->assertSame(0, $response->json('data.rows.0.blocks_count'));
    }

    public function test_the_new_form_carries_every_editable_column(): void {
        $columns = $this->send('admin/page/new')->json('data.columns');

        $this->assertSame(
            ['path', 'title', 'seo_title', 'seo_description', 'og_image', 'enable_time', 'disable_time'],
            array_column($columns, 'name')
        );
    }

    public function test_the_seo_columns_sit_on_the_seo_tab(): void {
        $columns = $this->send('admin/page/new')->json('data.columns');
        $tabs = array_column($columns, 'tab', 'name');

        $this->assertSame('seo', $tabs['seo_title']);
        $this->assertSame('seo', $tabs['seo_description']);
        $this->assertSame('seo', $tabs['og_image']);
        $this->assertSame('other', $tabs['enable_time']);
        $this->assertNull($tabs['path']);
    }

    public function test_inserting_creates_the_page(): void {
        $this->send('admin/page/insert', $this->payload('about-us', [
            'seo_title__en' => 'About Us | Sample',
            'seo_description__en' => 'Who we are'
        ]))->assertJsonPath('success', true);

        $page = Page::query()->where('path', 'about-us')->sole();

        $this->assertSame('About Us | Sample', $page->seo_title__en);
        $this->assertSame('Who we are', $page->seo_description__en);
        $this->assertSame('about-us TW', $page->title);
    }

    public function test_inserting_a_duplicate_path_is_refused_with_a_validation_error(): void {
        $this->page('about-us');

        $response = $this->send('admin/page/insert', $this->payload('about-us'));

        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('error', 'validation-failed');
    }

    public function test_inserting_without_a_path_is_refused(): void {
        $this->send('admin/page/insert', $this->payload('about-us', ['path' => null]))
            ->assertJsonPath('code', 422);
    }

    public function test_updating_keeps_its_own_path_without_tripping_the_unique_rule(): void {
        $page = $this->page('about-us');

        $this->send("admin/page/{$page->id}/update", $this->payload('about-us', [
            'seo_title__en' => 'Renamed'
        ]))->assertJsonPath('success', true);

        $this->assertSame('Renamed', Page::query()->findOrFail($page->id)->seo_title__en);
    }

    public function test_reading_one_page_reports_its_columns(): void {
        $page = $this->page('about-us');

        $this->send("admin/page/{$page->id}")->assertJsonPath('data.data.path', 'about-us');
    }

    public function test_deleting_removes_the_page(): void {
        $page = $this->page('about-us');

        $this->send('admin/page/delete', ['id' => [$page->id]])->assertJsonPath('success', true);

        $this->assertNull(Page::query()->find($page->id));
    }

    public function test_the_arrange_endpoints_answer(): void {
        $page = $this->page('about-us');

        $this->send('admin/page/arrange')->assertJsonPath('success', true);

        $this->send('admin/page/arrange/save', [
            'items' => [['id' => $page->id, 'enable_time' => null, 'disable_time' => null]]
        ])->assertJsonPath('success', true);
    }

    public function test_the_new_form_expands_the_variant_subfields_into_ordinary_columns(): void {
        $this->usePageDataFixtures();

        $response = $this->send('admin/page/new');
        $columns = $response->json('data.columns');

        $this->assertSame(
            ['path', 'title', 'seo_title', 'seo_description', 'og_image', 'data__layout', 'data__lead', 'enable_time', 'disable_time'],
            array_column($columns, 'name')
        );

        $this->assertFalse($this->columnByName($columns, 'data__layout')['translatable']);
        $this->assertTrue($this->columnByName($columns, 'data__lead')['translatable']);

        $blank = $response->json('data.data');

        $this->assertArrayNotHasKey('data', $blank);
        $this->assertArrayHasKey('data__layout', $blank);
        $this->assertArrayHasKey('data__lead__tw', $blank);
        $this->assertArrayHasKey('data__lead__en', $blank);
    }

    public function test_the_new_form_reflects_the_values_it_was_opened_with(): void {
        $data = $this->send('admin/page/new', ['path' => 'about-us'])->json('data.data');

        $this->assertSame('about-us', array_get_value($data, 'path'));
    }

    public function test_the_new_form_echoes_nothing_the_caller_invented(): void {
        $data = $this->send('admin/page/new', ['ranking' => 7, 'made_up' => 'x'])->json('data.data');

        $this->assertArrayNotHasKey('made_up', $data);
        $this->assertArrayNotHasKey('ranking', $data);
    }

    public function test_inserting_stores_the_resolved_variant_data(): void {
        $this->usePageDataFixtures();

        $payload = Arr::except($this->payload('about-us'), ['data']);

        $this->send('admin/page/insert', array_merge($payload, [
            'data__layout' => 'wide',
            'data__lead__tw' => 'Lead TW',
            'data__lead__en' => 'Lead EN'
        ]))->assertJsonPath('success', true);

        $data = Page::query()->where('path', 'about-us')->sole()->data;

        $this->assertIsArray($data);
        $this->assertSame('wide', array_get_value($data, 'layout'));

        $lead = array_get_value($data, 'lead');

        $this->assertIsArray($lead);
        $this->assertSame('Lead TW', array_get_value($lead, 'tw'));
        $this->assertSame('Lead EN', array_get_value($lead, 'en'));
    }

    public function test_without_a_configured_driver_the_data_column_is_left_untouched(): void {
        $this->send('admin/page/insert', $this->payload('about-us'))->assertJsonPath('success', true);

        $this->assertNull(Page::query()->where('path', 'about-us')->sole()->data);
    }

    public function test_the_unregistered_actions_stay_closed(): void {
        $page = $this->page('about-us');

        $this->send("admin/page/{$page->id}/copy")->assertJsonPath('code', 403);
        $this->send('admin/page/export')->assertJsonPath('code', 403);
        $this->send('admin/page/sort')->assertJsonPath('code', 403);
        $this->send('admin/page/sort/save')->assertJsonPath('code', 403);
    }

}
