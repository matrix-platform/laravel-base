<?php //>

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\ManipulationLog;
use MatrixPlatform\Models\ManipulationType;
use MatrixPlatform\Models\MemberLog;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Models\UserLog;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Models\VendorLog;
use Tests\Factories\MemberFactory;
use Tests\Factories\UserFactory;
use Tests\Factories\VendorFactory;
use Tests\FeatureTestCase;

class PruneTokensCommandTest extends FeatureTestCase {

    private const IDLE = ['admin' => 10, 'member' => 20, 'vendor' => 30];

    private const LOGS = ['base_manipulation_log', 'base_user_log', 'base_member_log', 'base_vendor_log', 'base_mail_log', 'base_sms_log'];

    protected function setUp(): void {
        parent::setUp();

        foreach (self::IDLE as $bundle => $minutes) {
            $this->useCfg($bundle, ['token-idle-minutes' => $minutes]);
        }
    }

    private function command(string $arguments = ''): PendingCommand {
        return $this->artisanCommand(trim("matrix:prune-tokens {$arguments}"));
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array {
        $counts = [];

        foreach (self::LOGS as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function batches(callable $callback): int {
        return $this->queryCount('select * from "base_auth_token"', $callback);
    }

    private function feed(int $cap): void {
        $fed = 0;

        DB::listen(function ($query) use ($cap, &$fed): void {
            if ($fed < $cap && str_starts_with($query->sql, 'select * from "base_auth_token"')) {
                $fed++;

                $this->token(IdentityType::User, 400);
            }
        });
    }

    private function loggedOut(IdentityType $type): AuthToken {
        $token = $this->token($type, 0);

        $token->expire_time = now()->subSecond();

        $token->save();

        return $token;
    }

    private function logs(): void {
        $this->travelTo(now()->subYear());

        $manipulation = new ManipulationLog();

        $manipulation->type = ManipulationType::Created;
        $manipulation->data_type = 'stub';
        $manipulation->data_id = 1;

        $manipulation->save();

        UserFactory::new()->createOne(['id' => 1]);
        MemberFactory::new()->createOne(['id' => 1]);
        VendorFactory::new()->createOne(['id' => 1]);

        $user = new UserLog();

        $user->user_id = 1;
        $user->type = UserLogType::Login;

        $user->save();

        $member = new MemberLog();

        $member->member_id = 1;
        $member->type = 'login';

        $member->save();

        $vendor = new VendorLog();

        $vendor->vendor_id = 1;
        $vendor->type = 'login';

        $vendor->save();

        $mail = new MailLog();

        $mail->provider = 'gmail';
        $mail->sender = 'a@b.c';
        $mail->receiver = 'd@e.f';
        $mail->subject = 'Hi';
        $mail->content = 'Body';
        $mail->schedule_time = now();
        $mail->status = MessageStatus::Success;

        $mail->save();

        $sms = new SmsLog();

        $sms->provider = 'mitake';
        $sms->receiver = '0900000000';
        $sms->content = 'Body';
        $sms->schedule_time = now();
        $sms->status = MessageStatus::Success;

        $sms->save();

        $this->travelBack();
    }

    /**
     * @return list<int>
     */
    private function remaining(): array {
        return array_values(array_map('intval', AuthToken::query()->orderBy('id')->pluck('id')->all()));
    }

    private function token(IdentityType $type, int $idle): AuthToken {
        $this->travelTo(now()->subMinutes($idle));

        $token = $this->untouched($type);

        $token->keepAlive();

        $this->travelBack();

        return $token;
    }

    private function untouched(IdentityType $type): AuthToken {
        $token = new AuthToken();

        $token->token = strval(Str::uuid());
        $token->type = $type;
        $token->target_id = 1;

        $token->save();

        return $token;
    }

    public function test_a_token_idle_past_its_threshold_is_deleted(): void {
        $this->token(IdentityType::User, 400);

        $this->command()->assertExitCode(0);

        $this->assertSame([], $this->remaining());
    }

    public function test_a_token_still_within_its_threshold_survives(): void {
        $alive = $this->token(IdentityType::User, 5)->id;

        $this->command()->assertExitCode(0);

        $this->assertSame([$alive], $this->remaining());
    }

    public function test_a_token_that_logged_out_is_deleted_even_when_it_is_not_idle(): void {
        $this->loggedOut(IdentityType::User);

        $this->command()->assertExitCode(0);

        $this->assertSame([], $this->remaining());
    }

    public function test_a_token_without_an_update_time_is_deleted(): void {
        $this->untouched(IdentityType::User);

        $this->command()->assertExitCode(0);

        $this->assertSame([], $this->remaining());
    }

    public function test_each_identity_type_uses_its_own_threshold(): void {
        $this->token(IdentityType::User, 15);
        $member = $this->token(IdentityType::Member, 15)->id;
        $vendor = $this->token(IdentityType::Vendor, 15)->id;
        $slack = $this->token(IdentityType::Vendor, 25)->id;

        $this->command()->assertExitCode(0);

        $this->assertSame([$member, $vendor, $slack], $this->remaining());
    }

    public function test_every_dead_token_is_deleted_across_several_batches(): void {
        for ($index = 0; $index < 5; $index++) {
            $this->token(IdentityType::User, 400);
        }

        $this->command('--limit=2')->assertExitCode(0);

        $this->assertSame([], $this->remaining());
    }

    public function test_the_loop_stops_on_the_first_short_batch(): void {
        for ($index = 0; $index < 5; $index++) {
            $this->token(IdentityType::User, 400);
        }

        $batches = $this->batches(fn () => $this->command('--limit=2')->assertExitCode(0));

        $this->assertSame(5, $batches);
        $this->assertSame([], $this->remaining());
    }

    public function test_the_loop_stops_while_new_dead_tokens_keep_arriving(): void {
        for ($index = 0; $index < 4; $index++) {
            $this->token(IdentityType::User, 400);
        }

        $this->feed(1000);

        $batches = $this->batches(fn () => $this->command('--limit=2')->assertExitCode(0));

        $this->assertSame(7, $batches);
    }

    public function test_a_zero_limit_is_refused_without_deleting_anything(): void {
        $dead = $this->token(IdentityType::User, 400)->id;

        $this->command('--limit=0')
            ->expectsOutputToContain('The --limit option must be a positive integer')
            ->assertExitCode(1);

        $this->assertSame([$dead], $this->remaining());
    }

    public function test_a_negative_limit_is_refused_without_deleting_anything(): void {
        $dead = $this->token(IdentityType::User, 400)->id;

        $this->command('--limit=-1')->assertExitCode(1);

        $this->assertSame([$dead], $this->remaining());
    }

    public function test_a_non_numeric_limit_is_refused_without_deleting_anything(): void {
        $dead = $this->token(IdentityType::User, 400)->id;

        $this->command('--limit=abc')->assertExitCode(1);
        $this->command('--limit=2x')->assertExitCode(1);

        $this->assertSame([$dead], $this->remaining());
    }

    public function test_the_output_reports_each_identity_type_separately(): void {
        $this->token(IdentityType::User, 400);
        $this->token(IdentityType::Member, 400);
        $this->token(IdentityType::Member, 400);
        $this->token(IdentityType::Vendor, 400);
        $this->token(IdentityType::Vendor, 400);
        $this->token(IdentityType::Vendor, 400);

        $this->command()
            ->expectsOutputToContain('Deleted 1 dead admin tokens')
            ->expectsOutputToContain('Deleted 2 dead member tokens')
            ->expectsOutputToContain('Deleted 3 dead vendor tokens')
            ->assertExitCode(0);
    }

    public function test_the_command_is_registered_under_its_new_name_only(): void {
        $commands = Artisan::all();

        $this->assertArrayHasKey('matrix:prune-tokens', $commands);
        $this->assertArrayNotHasKey('matrix:prune', $commands);
    }

    public function test_no_other_table_is_touched(): void {
        $this->logs();
        $this->token(IdentityType::User, 400);

        $before = $this->counts();

        $this->command()->assertExitCode(0);

        $this->assertSame($before, $this->counts());
        $this->assertSame([], $this->remaining());
    }

}
