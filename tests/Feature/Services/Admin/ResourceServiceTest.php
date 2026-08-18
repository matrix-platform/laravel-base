<?php //>

namespace Tests\Feature\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\ResourceOverride;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\ResourceService;
use MatrixPlatform\Support\ResourceGroup;
use MatrixPlatform\Support\Template;
use Tests\Factories\UserFactory;
use Tests\FeatureTestCase;

class ResourceServiceTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->useResourceFixtures();

        $this->useResourceWhitelist([
            'cfg' => ['admin', 'dotted', 'structured'],
            'i18n' => ['errors', 'widget'],
            'i18n/options' => ['color'],
            'i18n/template' => ['greeting']
        ]);

        actor()->setUser(UserFactory::new()->createOne(['id' => User::ROOT]));
    }

    private function override(string $bundle): mixed {
        $record = ResourceOverride::query()->where('bundle', $bundle)->first();

        return $record === null ? null : $record->data;
    }

    private function service(): ResourceService {
        return app(ResourceService::class);
    }

    public function test_a_bundle_reports_only_what_is_overridden_beside_the_file_values(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600]]);

        $payload = $this->service()->get(ResourceGroup::Cfg, 'admin');

        $this->assertSame(['captcha-ttl' => 600], array_get_value($payload, 'data'));
        $this->assertSame(300, array_get_value($payload, 'default')['captcha-ttl']);
    }

    public function test_a_bundle_nobody_edited_reports_no_data_at_all(): void {
        $this->assertSame([], array_get_value($this->service()->get(ResourceGroup::Cfg, 'admin'), 'data'));
    }

    public function test_a_column_takes_its_type_and_rules_from_the_schema(): void {
        $columns = array_column($this->service()->get(ResourceGroup::Cfg, 'admin')['columns'], null, 'name');

        $this->assertSame('integer', $columns['captcha-ttl']['type']);
        $this->assertSame(['integer', 'min:1'], $columns['captcha-ttl']['rule']);
    }

    public function test_a_declared_rule_replaces_the_type_rule(): void {
        $this->useResourceWhitelist(['cfg' => ['gmail']]);

        $columns = array_column($this->service()->get(ResourceGroup::Cfg, 'gmail')['columns'], null, 'name');

        $this->assertSame(['integer', 'min:1', 'max:65535'], $columns['port']['rule']);
        $this->assertSame(['string'], $columns['host']['rule']);
    }

    public function test_a_duration_the_framework_would_treat_as_expired_is_rejected(): void {
        $this->expectException(ValidationException::class);

        $this->service()->update(ResourceGroup::Cfg, 'admin', ['token-idle-minutes' => 0]);
    }

    public function test_a_field_without_a_schema_entry_falls_back_to_text(): void {
        $columns = array_column($this->service()->get(ResourceGroup::I18n, 'widget')['columns'], null, 'name');

        $this->assertSame('text', $columns['hello']['type']);
        $this->assertSame(['string'], $columns['hello']['rule']);
    }

    public function test_a_field_the_schema_marks_readonly_is_still_shown(): void {
        $this->useResourceWhitelist(['cfg' => ['gmail']]);

        $columns = array_column($this->service()->get(ResourceGroup::Cfg, 'gmail')['columns'], null, 'name');

        $this->assertTrue($columns['driver']['readonly']);
        $this->assertFalse($columns['host']['readonly']);
    }

    public function test_a_key_whose_value_is_an_array_gets_no_column(): void {
        $columns = array_column($this->service()->get(ResourceGroup::Cfg, 'structured')['columns'], 'name');

        $this->assertSame(['scalar'], $columns);
    }

    public function test_a_label_comes_from_the_resource_bundle_and_falls_back_to_the_key(): void {
        $columns = array_column($this->service()->get(ResourceGroup::Cfg, 'dotted')['columns'], null, 'name');

        $this->assertSame('Plain key', $columns['plain']['title']);
        $this->assertSame('cfg/dotted.nested.key', $columns['nested.key']['title']);
    }

    public function test_an_unknown_group_and_an_unknown_name_are_both_missing(): void {
        $this->refuses('data-not-found', fn () => $this->service()->get(ResourceGroup::Cfg, 'nope'));
        $this->refuses('data-not-found', fn () => $this->service()->get(ResourceGroup::Menu, 'admin'));
    }

    public function test_a_traversing_name_cannot_escape_the_group(): void {
        $this->refuses('data-not-found', fn () => $this->service()->get(ResourceGroup::Cfg, '../../composer'));
    }

    public function test_writing_one_field_leaves_the_other_overrides_alone(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600, 'token-idle-minutes' => 90]]);

        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 900]);

        $this->assertSame(['captcha-ttl' => 900, 'token-idle-minutes' => 90], $this->override('cfg/admin'));
    }

    public function test_clearing_a_field_removes_the_override_and_restores_the_file_value(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600, 'token-idle-minutes' => 90]]);

        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => '']);

        $this->assertSame(['token-idle-minutes' => 90], $this->override('cfg/admin'));
        $this->assertSame(300, cfg('admin.captcha-ttl'));
    }

    public function test_clearing_a_field_is_allowed_even_where_a_value_would_be_rejected(): void {
        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => null]);

        $this->assertNull($this->override('cfg/admin'));
    }

    public function test_writing_the_default_value_removes_the_key_from_the_override(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600, 'token-idle-minutes' => 90]]);

        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 300]);

        $this->assertSame(['token-idle-minutes' => 90], $this->override('cfg/admin'));
    }

    public function test_removing_the_last_overridden_key_removes_the_row(): void {
        ResourceOverride::forceCreate(['bundle' => 'cfg/admin', 'data' => ['captcha-ttl' => 600]]);

        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 300]);

        $this->assertNull($this->override('cfg/admin'));
        $this->assertSame(0, ResourceOverride::query()->count());
    }

    public function test_a_value_is_stored_in_the_type_its_column_declares(): void {
        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => '600']);

        $this->assertSame(['captcha-ttl' => 600], $this->override('cfg/admin'));
        $this->assertSame(600, cfg('admin.captcha-ttl'));
    }

    public function test_a_value_the_rules_reject_writes_nothing(): void {
        try {
            $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 'not-a-number']);
        } catch (ValidationException $exception) {
            $this->assertSame(0, ResourceOverride::query()->count());

            return;
        }

        $this->fail('expected the update to be rejected');
    }

    public function test_a_key_the_bundle_does_not_declare_is_dropped(): void {
        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 600, 'bogus' => 'x']);

        $this->assertSame(['captcha-ttl' => 600], $this->override('cfg/admin'));
    }

    public function test_a_readonly_key_is_dropped(): void {
        $this->useResourceWhitelist(['cfg' => ['gmail']]);

        $this->service()->update(ResourceGroup::Cfg, 'gmail', ['driver' => 'Evil', 'from-name' => 'Support']);

        $this->assertSame(['from-name' => 'Support'], $this->override('cfg/gmail'));
    }

    public function test_a_key_containing_a_literal_dot_is_validated_and_written(): void {
        $this->service()->update(ResourceGroup::Cfg, 'dotted', ['nested.key' => '42']);

        $this->assertSame(['nested.key' => 42], $this->override('cfg/dotted'));
        $this->assertSame(42, cfg('dotted.nested.key'));
    }

    public function test_a_key_containing_a_literal_dot_still_obeys_its_rules(): void {
        $this->expectException(ValidationException::class);

        $this->service()->update(ResourceGroup::Cfg, 'dotted', ['nested.key' => 'text']);
    }

    public function test_the_existing_override_is_read_under_a_row_lock(): void {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 900]);

        $reads = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strval($query['query']), 'select * from "base_resource_override"'));

        DB::disableQueryLog();

        $this->assertNotSame([], $reads);

        foreach ($reads as $read) {
            $this->assertStringContainsString('for update', strval($read['query']));
        }
    }

    public function test_a_written_value_is_visible_to_the_same_request(): void {
        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 900]);

        $this->assertSame(900, cfg('admin.captcha-ttl'));
    }

    public function test_writing_and_clearing_both_leave_an_audit_record(): void {
        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 900]);
        $this->service()->update(ResourceGroup::Cfg, 'admin', ['captcha-ttl' => 300]);

        $logs = ManipulationLog::query()
            ->where('data_type', 'base_resource_override')
            ->orderBy('id')
            ->get();

        $removed = $logs->last();

        $this->assertSame([ManipulationType::Created, ManipulationType::Deleted], $logs->pluck('type')->all());
        $this->assertNotNull($removed);
        $this->assertSame(['captcha-ttl' => 900], array_get_value($removed->before, 'data'));
    }

    public function test_a_template_edit_is_visible_to_the_renderer(): void {
        $this->service()->update(ResourceGroup::Template, 'greeting', ['subject' => 'Welcome {name}']);

        $this->assertSame('i18n/en/template/greeting', ResourceOverride::query()->value('bundle'));
        $this->assertSame('Welcome QA', Template::render('greeting', ['name' => 'QA'])['subject']);
    }

    public function test_the_edited_locale_follows_the_request(): void {
        app()->setLocale('tw');

        $this->service()->update(ResourceGroup::I18n, 'widget', ['hello' => '嗨']);

        $this->assertSame('i18n/tw/widget', ResourceOverride::query()->value('bundle'));
        $this->assertSame('Hello', i18n('widget.hello', 'en'));
    }

    public function test_the_package_ships_an_empty_whitelist_for_every_group(): void {
        $shipped = require __DIR__ . '/../../../../config/matrix.php';

        foreach (ResourceGroup::cases() as $group) {
            $this->assertSame([], array_get_value($shipped, $group->config()), $group->value);
        }
    }

    public function test_an_undeclared_whitelist_hides_everything_from_an_ordinary_administrator(): void {
        $this->useResourceWhitelist([]);

        $this->assertSame([], array_get_value($this->service()->list(ResourceGroup::Cfg, false), 'rows'));
        $this->assertNotSame([], array_get_value($this->service()->list(ResourceGroup::Cfg, true), 'rows'));
        $this->assertFalse($this->service()->whitelisted(ResourceGroup::Cfg, 'admin'));
    }

    public function test_a_group_whose_key_the_host_dropped_is_closed_not_open(): void {
        config()->set('matrix.' . ResourceGroup::Cfg->config(), null);

        $this->assertFalse($this->service()->whitelisted(ResourceGroup::Cfg, 'admin'));
        $this->assertSame([], array_get_value($this->service()->list(ResourceGroup::Cfg, false), 'rows'));
    }

    public function test_the_whitelist_answers_only_for_configured_names(): void {
        $this->assertTrue($this->service()->whitelisted(ResourceGroup::Cfg, 'admin'));
        $this->assertFalse($this->service()->whitelisted(ResourceGroup::Cfg, 'secret'));
        $this->assertFalse($this->service()->whitelisted(ResourceGroup::Menu, 'admin'));
    }

}
