<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use MatrixPlatform\Exceptions\ServiceException;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\Actor;
use Tests\FeatureTestCase;
use Tests\Stubs\Gadget;
use Tests\Stubs\Widget;

class BaseModelTest extends FeatureTestCase {

    private function lastLog(): ManipulationLog {
        return ManipulationLog::query()->orderByDesc('id')->firstOrFail();
    }

    private function logCount(): int {
        return ManipulationLog::query()->count();
    }

    public function test_creating_writes_a_log_of_type_created(): void {
        Widget::forceCreate(['title' => 'alpha']);

        $log = $this->lastLog();

        $this->assertSame(ManipulationType::Created, $log->type);
        $this->assertNull($log->before);
        $this->assertSame('alpha', array_get_value($log->after, 'title'));
    }

    public function test_creating_log_excludes_the_primary_key_and_audit_columns(): void {
        Widget::forceCreate(['title' => 'alpha']);

        $after = $this->lastLog()->after;

        $this->assertIsArray($after);
        $this->assertArrayNotHasKey('id', $after);
        $this->assertArrayNotHasKey('create_time', $after);
        $this->assertArrayNotHasKey('creator_id', $after);
        $this->assertArrayNotHasKey('update_time', $after);
        $this->assertArrayNotHasKey('updater_id', $after);
    }

    public function test_creating_log_excludes_untraceable_columns(): void {
        Widget::forceCreate(['title' => 'alpha', 'secret' => 'hunter2']);

        $after = $this->lastLog()->after;

        $this->assertIsArray($after);
        $this->assertArrayNotHasKey('secret', $after);
    }

    public function test_updating_writes_a_log_of_type_updated_with_only_the_changed_columns(): void {
        $widget = Widget::forceCreate(['title' => 'alpha', 'ip' => '10.0.0.1']);

        $widget->setAttribute('title', 'beta');
        $widget->save();

        $log = $this->lastLog();

        $this->assertSame(ManipulationType::Updated, $log->type);
        $this->assertSame(['title' => 'alpha'], $log->before);
        $this->assertSame('beta', array_get_value($log->after, 'title'));
    }

    public function test_updating_only_untraceable_columns_writes_no_log(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);
        $before = $this->logCount();

        $widget->setAttribute('secret', 'hunter2');
        $widget->save();

        $this->assertSame($before, $this->logCount());
    }

    public function test_deleting_writes_a_log_of_type_deleted(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);
        $widget->delete();

        $log = $this->lastLog();

        $this->assertSame(ManipulationType::Deleted, $log->type);
        $this->assertSame('alpha', array_get_value($log->before, 'title'));
        $this->assertNull($log->after);
    }

    public function test_a_model_with_tracing_disabled_writes_no_log(): void {
        Gadget::forceCreate(['title' => 'alpha']);

        $this->assertSame(0, $this->logCount());
    }

    public function test_create_time_is_set_while_update_time_starts_null(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);

        $this->assertTrue($widget->create_time->isToday());
        $this->assertNull($widget->update_time);
    }

    public function test_updating_sets_update_time(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);

        $widget->setAttribute('title', 'beta');
        $widget->save();

        $this->assertTrue($widget->update_time?->isToday());
    }

    public function test_creator_and_updater_are_null_for_a_guest(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);

        $widget->setAttribute('title', 'beta');
        $widget->save();

        $this->assertNull($widget->creator_id);
        $this->assertNull($widget->updater_id);
    }

    public function test_creator_and_updater_pick_up_the_current_identity(): void {
        $user = User::forceCreate(['username' => 'the-actor']);

        app(Actor::class)->setUser($user);

        $widget = Widget::forceCreate(['title' => 'alpha']);

        $widget->setAttribute('title', 'beta');
        $widget->save();

        $this->assertSame($user->getKey(), $widget->creator_id);
        $this->assertSame($user->getKey(), $widget->updater_id);
    }

    public function test_generators_fill_their_column_on_create(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);

        $this->assertSame('127.0.0.1', $widget->ip);
    }

    public function test_lock_succeeds_when_nothing_changed_underneath(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);
        $widget = Widget::query()->whereKey($widget->getKey())->firstOrFail();

        $this->assertSame($widget, $widget->lock());
    }

    public function test_lock_succeeds_when_the_row_carries_datetime_columns(): void {
        $widget = Widget::forceCreate(['title' => 'alpha', 'enable_time' => now(), 'disable_time' => now()->addDay()]);
        $widget = Widget::query()->whereKey($widget->getKey())->firstOrFail();

        $this->assertSame($widget, $widget->lock());
    }

    public function test_lock_fails_when_the_row_changed_underneath(): void {
        $widget = Widget::forceCreate(['title' => 'alpha']);
        $widget = Widget::query()->whereKey($widget->getKey())->firstOrFail();

        DB::table('stub_widget')->where('id', $widget->getKey())->update(['title' => 'beta']);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('data-conflicted');

        $widget->lock();
    }

    public function test_dates_serialize_without_the_iso_format(): void {
        $widget = Widget::forceCreate(['title' => 'alpha', 'enable_time' => now()]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $widget->toArray()['enable_time']);
    }

    public function test_dates_serialize_with_the_configured_format(): void {
        $this->useCfgFixtures();

        $widget = Widget::forceCreate(['title' => 'alpha', 'enable_time' => '2026-08-12 13:45:07']);

        $this->assertSame('12/08/2026 13:45', $widget->toArray()['enable_time']);
    }

}
