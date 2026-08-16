<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Facades\Hash;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\Vendor;
use MatrixPlatform\Models\VendorLog;
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
        $auth = AuthToken::query()->where('token', $token)->firstOrFail();

        $this->assertSame(IdentityType::Vendor, $auth->type);
        $this->assertNotNull($auth->update_time);
        $this->assertNotNull(AuthToken::findByToken($token, IdentityType::Vendor));
    }

    public function test_writing_a_log_records_the_address_and_the_user_agent(): void {
        $vendor = VendorFactory::new()->createOne();

        $vendor->writeLog('Login', ['source' => 'portal']);

        $log = VendorLog::query()->where('vendor_id', $vendor->id)->firstOrFail();

        $this->assertSame('Login', $log->type);
        $this->assertSame(['source' => 'portal'], $log->content);
        $this->assertNotNull($log->ip);
        $this->assertNotNull($log->user_agent);
    }

}
