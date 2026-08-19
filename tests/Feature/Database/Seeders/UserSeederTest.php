<?php //>

namespace Tests\Feature\Database\Seeders;

use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Models\User;
use MatrixPlatform\Support\AdminLevel;
use Tests\FeatureTestCase;

class UserSeederTest extends FeatureTestCase {

    public function test_the_seeder_writes_the_two_bootstrap_accounts_on_fixed_identifiers(): void {
        (new UserSeeder())->run();

        $this->assertSame([1 => 'root@matrix', 2 => 'admin'], User::query()->pluck('username', 'id')->all());
    }

    public function test_the_two_accounts_land_on_the_root_and_admin_levels(): void {
        (new UserSeeder())->run();

        $this->assertSame(AdminLevel::Root, AdminLevel::of(1));
        $this->assertSame(AdminLevel::Admin, AdminLevel::of(2));
    }

    public function test_neither_account_carries_a_password(): void {
        (new UserSeeder())->run();

        $this->assertSame(2, User::query()->whereNull('password')->count());
    }

    public function test_both_accounts_are_enabled(): void {
        (new UserSeeder())->run();

        $this->assertSame(2, User::query()->whereEnabled()->count());
    }

}
