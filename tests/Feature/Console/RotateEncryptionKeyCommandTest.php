<?php //>

namespace Tests\Feature\Console;

use Illuminate\Testing\PendingCommand;
use MatrixPlatform\Models\EncryptionKey;
use MatrixPlatform\Support\ApiEncryption;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\FeatureTestCase;

class RotateEncryptionKeyCommandTest extends FeatureTestCase {

    /**
     * @return array<string, array{string}>
     */
    public static function invalidGracePeriods(): array {
        return [
            'a word' => ['soon'],
            'a negative number' => ['-1'],
            'a fraction' => ['1.5']
        ];
    }

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

    public function test_a_failed_rotation_leaves_the_previous_key_active(): void {
        $previous = EncryptionKey::issue();

        EncryptionKey::creating(function (): void {
            throw new RuntimeException('injected failure');
        });

        try {
            $this->command()->run();
            $this->fail('the rotation should have failed');
        } catch (RuntimeException) {
        }

        $this->assertSame($previous->kid, EncryptionKey::active()?->kid);
    }

    public function test_a_key_still_inside_its_grace_period_keeps_its_expiry_on_the_next_rotation(): void {
        $graced = EncryptionKey::issue();
        $expiry = now()->addHour()->startOfSecond();
        $graced->expire_time = $expiry;
        $graced->save();

        EncryptionKey::issue();

        $this->command('--grace=86400')->assertExitCode(0);

        $this->assertEquals($expiry, EncryptionKey::query()->findOrFail($graced->id)->expire_time);
    }

    #[DataProvider('invalidGracePeriods')]
    public function test_a_grace_period_that_is_not_a_non_negative_integer_fails(string $grace): void {
        $this->command("--grace={$grace}")
            ->expectsOutputToContain('The --grace option must be a non-negative integer')
            ->assertExitCode(1);

        $this->assertNull(EncryptionKey::active());
    }

}
