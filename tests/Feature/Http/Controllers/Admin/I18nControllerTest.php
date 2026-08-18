<?php //>

namespace Tests\Feature\Http\Controllers\Admin;

use MatrixPlatform\Models\ResourceOverride;
use Tests\FeatureTestCase;

class I18nControllerTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useResourceFixtures();
    }

    public function test_a_bundle_is_readable_without_a_session(): void {
        $this->postJson('admin/i18n/get', ['name' => 'errors'])
            ->assertOk()
            ->assertJsonPath('data.data-not-found', 'Data not found');
    }

    public function test_a_nested_bundle_name_is_reachable(): void {
        $this->postJson('admin/i18n/get', ['name' => 'menu/base'])
            ->assertOk()
            ->assertJsonPath('data.system', 'System');
    }

    public function test_an_unknown_bundle_answers_null_without_failing(): void {
        $this->postJson('admin/i18n/get', ['name' => 'does-not-exist'])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => null]);
    }

    public function test_a_missing_name_is_rejected(): void {
        $this->postJson('admin/i18n/get')
            ->assertOk()
            ->assertJson(['success' => false, 'code' => 422]);
    }

    public function test_an_edited_translation_reaches_the_endpoint(): void {
        ResourceOverride::forceCreate(['bundle' => 'i18n/en/widget', 'data' => ['hello' => 'Edited']]);

        $this->postJson('admin/i18n/get', ['name' => 'widget'])
            ->assertOk()
            ->assertJsonPath('data.hello', 'Edited');
    }

}
