<?php //>

namespace Tests\Feature\Console;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Models\User;
use Symfony\Component\Console\Exception\RuntimeException;
use Tests\FeatureTestCase;

class ResetUserPasswordCommandTest extends FeatureTestCase {

    private const CODE = '13579';
    private const PASSWORD = 'secret-Passw0rd';

    protected function setUp(): void {
        parent::setUp();

        (new UserSeeder())->run();
    }

    private function command(string $arguments): PendingCommand {
        return $this->artisanCommand("matrix:passwd {$arguments}");
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function login(string $username, string $password): TestResponse {
        return $this->postJson('admin/auth/login', [
            'username' => $username,
            'password' => $password,
            'token' => $this->captcha(self::CODE),
            'code' => self::CODE
        ]);
    }

    private function passwords(): int {
        return User::query()->whereNotNull('password')->count();
    }

    public function test_the_command_sets_a_password_that_can_log_in(): void {
        $this->command('root@matrix')
            ->expectsQuestion('New password', self::PASSWORD)
            ->expectsQuestion('Retype new password', self::PASSWORD)
            ->expectsOutputToContain('Password updated successfully')
            ->assertExitCode(0);

        $this->login('root@matrix', self::PASSWORD)->assertJson(['success' => true]);
    }

    public function test_an_unknown_account_fails_without_touching_any_password(): void {
        $this->command('ghost')
            ->expectsOutputToContain("User 'ghost' does not exist")
            ->assertExitCode(1);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(0, $this->passwords());
    }

    public function test_an_empty_password_fails_without_writing(): void {
        $this->command('admin')
            ->expectsQuestion('New password', '')
            ->expectsOutputToContain('No password supplied')
            ->assertExitCode(1);

        $this->assertSame(0, $this->passwords());
    }

    public function test_a_weak_password_fails_without_writing(): void {
        $this->command('admin')
            ->expectsQuestion('New password', 'short')
            ->expectsOutputToContain('Password does not satisfy the configured policy')
            ->assertExitCode(1);

        $this->assertSame(0, $this->passwords());
    }

    public function test_the_command_refuses_a_password_on_the_command_line(): void {
        try {
            $this->command('admin ' . self::PASSWORD)->run();

            $this->fail('the command accepted a password argument');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Too many arguments', $exception->getMessage());
        }

        $this->assertSame(0, $this->passwords());
    }

    public function test_a_mismatched_confirmation_fails_without_writing(): void {
        $this->command('admin')
            ->expectsQuestion('New password', self::PASSWORD)
            ->expectsQuestion('Retype new password', 'something-else')
            ->expectsOutputToContain('Passwords do not match')
            ->assertExitCode(1);

        $this->assertSame(0, $this->passwords());
    }

    public function test_resetting_a_password_revokes_existing_sessions(): void {
        $this->command('admin')
            ->expectsQuestion('New password', self::PASSWORD)
            ->expectsQuestion('Retype new password', self::PASSWORD)
            ->assertExitCode(0);

        $token = strval($this->login('admin', self::PASSWORD)->json('data.token'));
        $replacement = 'another-Passw0rd';

        $this->command('admin')
            ->expectsQuestion('New password', $replacement)
            ->expectsQuestion('Retype new password', $replacement)
            ->assertExitCode(0);

        $this->withToken($token)
            ->postJson('admin/auth/profile')
            ->assertJson(['success' => false, 'error' => 'invalid-token']);
    }

}
