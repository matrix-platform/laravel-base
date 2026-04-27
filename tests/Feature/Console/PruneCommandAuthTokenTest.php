<?php //>

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class PruneCommandAuthTokenTest extends FeatureTestCase {

    private function insertTokens(int $count, bool $expired) {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'token'       => Str::uuid(),
                'type'        => 'User',
                'target_id'   => 1,
                'create_time' => now()->startOfMinute(),
                'modify_time' => now()->startOfMinute(),
                'expire_time' => $expired ? now()->subDay() : now()->addDay(),
            ];
        }

        DB::table('base_auth_token')->insert($rows);
    }

    public function test_prune_deletes_expired_tokens_and_keeps_valid() {
        $this->insertTokens(10, expired: true);
        $this->insertTokens(5, expired: false);

        $this->artisan('matrix:prune')->assertExitCode(0);

        $this->assertEquals(0, DB::table('base_auth_token')->where('expire_time', '<', now())->count());
        $this->assertEquals(5, DB::table('base_auth_token')->count());
    }

    public function test_prune_deletes_in_batches() {
        $this->insertTokens(2500, expired: true);

        DB::enableQueryLog();

        $this->artisan('matrix:prune')->assertExitCode(0);

        $deletes = array_filter(DB::getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'delete'));

        $this->assertCount(4, array_values($deletes));
        $this->assertEquals(0, DB::table('base_auth_token')->count());
    }

}
