<?php //>

namespace Tests\Feature\Console;

use Illuminate\Testing\PendingCommand;
use MatrixPlatform\Models\EncryptionKey;
use MatrixPlatform\Support\ApiEncryption;
use Tests\FeatureTestCase;

class RotateEncryptionKeyCommandTest extends FeatureTestCase {

    private function command(string $arguments = ''): PendingCommand {
        return $this->artisanCommand(trim("matrix:rotate-encryption-key {$arguments}"));
    }

    public function test_the_first_run_issues_an_active_key(): void {
        $this->command()->assertExitCode(0);

        $key = EncryptionKey::active();

        $this->assertNotNull($key);
        $this->assertNull($key->expire_time);
        $this->assertNotSame('', ApiEncryption::derive(ApiEncryption::generate()['private'], $key->public_key));
    }

    public function test_the_previous_key_keeps_working_for_the_grace_period(): void {
        $previous = EncryptionKey::issue();

        $this->command('--grace=3600')->assertExitCode(0);

        $expired = EncryptionKey::query()->findOrFail($previous->id);

        $this->assertTrue($expired->expire_time?->isAfter(now()->addMinutes(55)));
        $this->assertSame($previous->kid, EncryptionKey::findByKid($previous->kid)?->kid);
        $this->assertNotSame($previous->kid, EncryptionKey::active()?->kid);
    }

    public function test_the_grace_period_falls_back_to_the_configured_value(): void {
        $this->useCfg('encryption', ['grace-period' => 60]);

        $previous = EncryptionKey::issue();

        $this->command()->assertExitCode(0);

        $this->assertTrue(EncryptionKey::query()->findOrFail($previous->id)->expire_time?->isBefore(now()->addMinutes(2)));
    }

    public function test_a_key_past_its_grace_period_is_deleted_on_the_next_rotation(): void {
        $stale = EncryptionKey::issue();
        $stale->expire_time = now()->subSecond();
        $stale->save();

        $this->command()->assertExitCode(0);

        $this->assertNull(EncryptionKey::query()->find($stale->id));
    }

    public function test_a_non_numeric_grace_period_fails(): void {
        $this->command('--grace=soon')->assertExitCode(1);

        $this->assertNull(EncryptionKey::active());
    }

}
