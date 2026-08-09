<?php //>

namespace Tests\Unit\Support;

use MatrixPlatform\Support\AdminLevel;
use PHPUnit\Framework\TestCase;

class AdminLevelTest extends TestCase {

    public function test_the_first_account_is_the_root_account(): void {
        $this->assertSame(AdminLevel::Root, AdminLevel::of(1));
    }

    public function test_the_low_id_range_is_reserved_for_administrators(): void {
        $this->assertSame(AdminLevel::Admin, AdminLevel::of(2));
        $this->assertSame(AdminLevel::Admin, AdminLevel::of(1000));
    }

    public function test_anything_above_the_range_is_a_regular_account(): void {
        $this->assertSame(AdminLevel::Regular, AdminLevel::of(1001));
    }

    public function test_accounts_created_through_the_shared_sequence_are_regular(): void {
        $this->assertSame(AdminLevel::Regular, AdminLevel::of(10000000));
    }

    public function test_the_levels_are_ordered_from_the_most_privileged(): void {
        $this->assertLessThan(AdminLevel::Admin->value, AdminLevel::Root->value);
        $this->assertLessThan(AdminLevel::Regular->value, AdminLevel::Admin->value);
    }

}
