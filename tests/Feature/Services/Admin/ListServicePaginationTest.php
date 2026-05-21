<?php //>

namespace Tests\Feature\Services\Admin;

use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\Common\ListService;
use MatrixPlatform\Support\AdminPermission;
use Tests\FeatureTestCase;

class ListServicePaginationTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        for ($i = 1; $i <= 15; $i++) {
            User::forceCreate(['disabled' => false, 'username' => "pagi{$i}"]);
        }
    }

    protected function setUp(): void {
        parent::setUp();

        $permission = $this->createStub(AdminPermission::class);
        $permission->method('getCurrentMenu')->willReturn(null);
        $this->app->instance(AdminPermission::class, $permission);
    }

    private function list($input) {
        return (new ListService(User::class))
            ->columns(['username'])
            ->params([])
            ->output($input);
    }

    public function test_first_page_with_size_5_returns_5_rows() {
        $result = $this->list(['page' => 1, 'size' => 5]);

        $this->assertCount(5, $result['rows']);
        $this->assertEquals(15, $result['pagination']['total']);
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(5, $result['pagination']['size']);
    }

    public function test_second_page_does_not_overlap_with_first() {
        $page1 = $this->list(['page' => 1, 'size' => 5]);
        $page2 = $this->list(['page' => 2, 'size' => 5]);

        $ids1 = collect($page1['rows'])->pluck('id')->all();
        $ids2 = collect($page2['rows'])->pluck('id')->all();

        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    public function test_size_zero_disables_pagination_and_returns_all() {
        $result = $this->list(['page' => 1, 'size' => 0]);

        $this->assertCount(15, $result['rows']);
        $this->assertEquals(15, $result['pagination']['size']);
    }

    public function test_page_zero_disables_pagination_and_returns_all() {
        $result = $this->list(['page' => 0, 'size' => 5]);

        $this->assertCount(15, $result['rows']);
    }

}
