<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\Hash;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\MemberLog;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use Tests\Factories\MemberFactory;
use Tests\FeatureTestCase;

class MemberTest extends FeatureTestCase {

    public function test_a_new_member_is_enabled_by_default(): void {
        $this->assertSame(Member::ENABLED, MemberFactory::new()->createOne()->status);
    }

    public function test_only_an_enabled_member_passes_the_builder_scope(): void {
        $member = MemberFactory::new()->createOne();

        $this->assertNotNull(Member::query()->whereEnabled()->whereKey($member->id)->first());

        $member->status = 2;
        $member->save();

        $this->assertNull(Member::query()->whereEnabled()->whereKey($member->id)->first());
    }

    public function test_the_password_is_hashed_and_hidden(): void {
        $member = MemberFactory::new()->createOne(['password' => 'secret-Passw0rd']);

        $this->assertNotSame('secret-Passw0rd', $member->password);
        $this->assertTrue(Hash::check('secret-Passw0rd', strval($member->password)));
        $this->assertArrayNotHasKey('password', $member->toArray());
    }

    public function test_the_password_never_reaches_the_audit_trail(): void {
        $member = MemberFactory::new()->createOne();

        $member->password = 'another-Passw0rd';
        $member->save();

        $log = ManipulationLog::query()
            ->where('data_type', 'base_member')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', (array) $log->after);
    }

    public function test_creating_a_token_issues_a_member_token_that_is_usable_at_once(): void {
        $token = MemberFactory::new()->createOne()->createToken();
        $auth = AuthToken::query()->where('token', AuthToken::digest($token))->firstOrFail();

        $this->assertSame(IdentityType::Member, $auth->type);
        $this->assertNotNull($auth->update_time);
        $this->assertNotNull(AuthToken::findByToken($token, IdentityType::Member));
    }

    public function test_writing_a_log_records_the_address_and_the_user_agent(): void {
        $member = MemberFactory::new()->createOne();

        $this->useRequestFrom('198.51.100.7', 'Probe/1.0');

        $member->writeLog('Login', ['source' => 'web']);

        $log = MemberLog::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame('Login', $log->type);
        $this->assertSame(['source' => 'web'], $log->content);
        $this->assertSame('198.51.100.7', $log->ip);
        $this->assertSame('Probe/1.0', $log->user_agent);
    }

    public function test_a_member_cannot_be_deleted(): void {
        $member = MemberFactory::new()->createOne();

        $this->refuses('member-not-deletable', fn () => (new DeleteService(Member::class))->standalone(true)->delete(['id' => $member->id]));

        $this->assertNotNull(Member::query()->find($member->id));
    }

}
