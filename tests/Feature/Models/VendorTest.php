<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\Hash;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Vendor;
use MatrixPlatform\Models\VendorLog;
use MatrixPlatform\Models\VendorLogType;
use MatrixPlatform\Services\Admin\Crud\DeleteService;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;

class VendorTest extends FeatureTestCase {

    public function test_a_new_vendor_is_enabled_by_default(): void {
        $this->assertSame(Vendor::ENABLED, VendorFactory::new()->createOne()->status);
    }

    public function test_only_an_enabled_vendor_passes_the_builder_scope(): void {
        $vendor = VendorFactory::new()->createOne();

        $this->assertNotNull(Vendor::query()->whereEnabled()->whereKey($vendor->id)->first());

        $vendor->status = 2;
        $vendor->save();

        $this->assertNull(Vendor::query()->whereEnabled()->whereKey($vendor->id)->first());
    }

    public function test_the_password_is_hashed_and_hidden(): void {
        $vendor = VendorFactory::new()->createOne(['password' => 'secret-Passw0rd']);

        $this->assertNotSame('secret-Passw0rd', $vendor->password);
        $this->assertTrue(Hash::check('secret-Passw0rd', strval($vendor->password)));
        $this->assertArrayNotHasKey('password', $vendor->toArray());
    }

    public function test_the_password_never_reaches_the_audit_trail(): void {
        $vendor = VendorFactory::new()->createOne();

        $vendor->password = 'another-Passw0rd';
        $vendor->save();

        $log = ManipulationLog::query()
            ->where('data_type', 'base_vendor')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', (array) $log->after);
    }

    public function test_creating_a_token_issues_a_vendor_token_that_is_usable_at_once(): void {
        $token = VendorFactory::new()->createOne()->createToken();
        $auth = AuthToken::query()->where('token', AuthToken::digest($token))->firstOrFail();

        $this->assertSame(IdentityType::Vendor, $auth->type);
        $this->assertNotNull($auth->update_time);
        $this->assertNotNull(AuthToken::findByToken($token, IdentityType::Vendor));
    }

    public function test_writing_a_log_records_the_address_and_the_user_agent(): void {
        $vendor = VendorFactory::new()->createOne();

        $this->useRequestFrom('198.51.100.7', 'Probe/1.0');

        $vendor->writeLog('Login', ['source' => 'portal']);

        $log = VendorLog::query()->where('vendor_id', $vendor->id)->firstOrFail();

        $this->assertSame('Login', $log->type);
        $this->assertSame(['source' => 'portal'], $log->content);
        $this->assertSame('198.51.100.7', $log->ip);
        $this->assertSame('Probe/1.0', $log->user_agent);
    }

    public function test_a_vendor_that_has_logged_in_cannot_be_deleted(): void {
        $vendor = VendorFactory::new()->createOne();

        $vendor->writeLog(VendorLogType::Login->value);

        $this->refuses('data-in-use', fn () => (new DeleteService(Vendor::class))->standalone(true)->delete(['id' => $vendor->id]));

        $this->assertNotNull(Vendor::query()->find($vendor->id));
    }

    public function test_deleting_a_vendor_that_never_logged_in_takes_its_logs_along(): void {
        $vendor = VendorFactory::new()->createOne();

        $vendor->writeLog('ChangePassword');

        (new DeleteService(Vendor::class))->standalone(true)->delete(['id' => $vendor->id]);

        $this->assertNull(Vendor::query()->find($vendor->id));
        $this->assertSame(0, VendorLog::query()->where('vendor_id', $vendor->id)->count());
    }

}
