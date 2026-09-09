<?php //>

namespace Tests\Feature\Services\Admin\Shared;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Operator;
use MatrixPlatform\Services\Admin\Shared\ManipulationLogService;
use Tests\Factories\GroupFactory;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;

class ManipulationLogServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->actAsRoot();
    }

    private function service(): ManipulationLogService {
        return app(ManipulationLogService::class);
    }

    public function test_the_rows_cover_create_update_and_delete_newest_first(): void {
        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);

        $group->title__tw = 'B';
        $group->save();
        $group->delete();

        $rows = $this->service()->query('group', $group->id, 1, 20)['rows'];

        $this->assertSame(['Deleted', 'Updated', 'Created'], $rows->pluck('type')->all());
    }

    public function test_the_update_row_carries_the_before_and_after_diff(): void {
        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);

        $group->title__tw = 'B';
        $group->save();

        $rows = $this->service()->query('group', $group->id, 1, 20)['rows'];
        $updated = $rows->firstWhere('type', 'Updated');

        $this->assertSame('A', $updated['before']['title__tw']);
        $this->assertSame('B', $updated['after']['title__tw']);
    }

    public function test_a_hard_deleted_record_still_reports_its_full_history(): void {
        $group = GroupFactory::new()->createOne();
        $id = $group->id;

        $group->delete();

        $this->assertNull(Group::query()->find($id));
        $this->assertSame(['Deleted', 'Created'], $this->service()->query('group', $id, 1, 20)['rows']->pluck('type')->all());
    }

    public function test_pagination_limits_the_returned_rows_and_reports_the_total(): void {
        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);

        $group->title__tw = 'B';
        $group->save();

        $result = $this->service()->query('group', $group->id, 1, 1);

        $this->assertCount(1, $result['rows']);
        $this->assertSame(['page' => 1, 'size' => 1, 'total' => 2], $result['pagination']);
    }

    public function test_the_create_time_is_formatted_like_the_rest_of_the_admin_api(): void {
        $group = GroupFactory::new()->createOne();

        $row = $this->service()->query('group', $group->id, 1, 20)['rows']->first();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['create_time']);
    }

    public function test_each_row_reports_the_username_and_type_of_its_own_creator(): void {
        $alice = UserFactory::new()->createOne(['username' => 'alice']);
        $bob = UserFactory::new()->createOne(['username' => 'bob']);

        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);
        $group->title__tw = 'B';
        $group->save();

        $rows = $this->service()->query('group', $group->id, 1, 20)['rows'];

        ManipulationLog::query()->whereKey($rows->firstWhere('type', 'Created')['id'])->update(['creator_id' => $alice->id]);
        ManipulationLog::query()->whereKey($rows->firstWhere('type', 'Updated')['id'])->update(['creator_id' => $bob->id]);

        $rows = $this->service()->query('group', $group->id, 1, 20)['rows'];

        $this->assertSame('alice', $rows->firstWhere('type', 'Created')['creator']);
        $this->assertSame('User', $rows->firstWhere('type', 'Created')['creator_type']);
        $this->assertSame('bob', $rows->firstWhere('type', 'Updated')['creator']);
        $this->assertSame('User', $rows->firstWhere('type', 'Updated')['creator_type']);
    }

    public function test_a_row_created_by_a_member_or_vendor_still_resolves_its_creator(): void {
        $member = MemberFactory::new()->createOne(['username' => 'the-member']);
        $vendor = VendorFactory::new()->createOne(['username' => 'the-vendor']);

        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);
        $group->title__tw = 'B';
        $group->save();

        $rows = $this->service()->query('group', $group->id, 1, 20)['rows'];

        ManipulationLog::query()->whereKey($rows->firstWhere('type', 'Created')['id'])->update(['creator_id' => $member->id]);
        ManipulationLog::query()->whereKey($rows->firstWhere('type', 'Updated')['id'])->update(['creator_id' => $vendor->id]);

        $rows = $this->service()->query('group', $group->id, 1, 20)['rows'];

        $this->assertSame('the-member', $rows->firstWhere('type', 'Created')['creator']);
        $this->assertSame('Member', $rows->firstWhere('type', 'Created')['creator_type']);
        $this->assertSame('the-vendor', $rows->firstWhere('type', 'Updated')['creator']);
        $this->assertSame('Vendor', $rows->firstWhere('type', 'Updated')['creator_type']);
    }

    public function test_a_row_with_no_creator_reports_a_null_creator(): void {
        $group = GroupFactory::new()->createOne();
        $rowId = $this->service()->query('group', $group->id, 1, 20)['rows']->first()['id'];

        ManipulationLog::query()->whereKey($rowId)->update(['creator_id' => null]);

        $row = $this->service()->query('group', $group->id, 1, 20)['rows']->first();

        $this->assertNull($row['creator_id']);
        $this->assertNull($row['creator']);
        $this->assertNull($row['creator_type']);
    }

    public function test_resolving_creators_runs_a_single_query_no_matter_how_many_rows_there_are(): void {
        $group = GroupFactory::new()->createOne(['title__tw' => 'A', 'title__en' => 'A']);

        foreach (['B', 'C', 'D', 'E'] as $title) {
            $group->title__tw = $title;
            $group->save();
        }

        $table = (new Operator())->getTable();
        $operatorQueries = 0;

        DB::listen(function ($query) use (&$operatorQueries, $table) {
            if (str_contains($query->sql, $table)) {
                $operatorQueries++;
            }
        });

        $this->service()->query('group', $group->id, 1, 20);

        $this->assertSame(1, $operatorQueries);
    }

}
