<?php //>

namespace Tests\Unit\Services\Admin\Crud;

use MatrixPlatform\Services\Admin\Crud\Ranking;
use PHPUnit\Framework\TestCase;

class RankingTest extends TestCase {

    /**
     * @param list<int> $rankings
     * @return list<int>
     */
    private function reassign(array $rankings): array {
        $new = Ranking::reassign($rankings);

        for ($index = 1; $index < count($new); $index++) {
            $this->assertGreaterThan($new[$index - 1], $new[$index], 'the result must be strictly increasing');
        }

        return $new;
    }

    public function test_a_strictly_increasing_input_is_left_untouched(): void {
        $this->assertSame([1, 2, 3], $this->reassign([1, 2, 3]));
    }

    public function test_the_gap_size_is_irrelevant_when_the_input_is_already_increasing(): void {
        $this->assertSame([-5, 3], $this->reassign([-5, 3]));
        $this->assertSame([100, 200, 300], $this->reassign([100, 200, 300]));
    }

    public function test_moving_the_last_row_to_the_front_changes_only_that_row(): void {
        $this->assertSame([99, 100, 200], $this->reassign([300, 100, 200]));
    }

    public function test_swapping_two_adjacent_rows_changes_only_one(): void {
        $this->assertSame([99, 100, 300], $this->reassign([200, 100, 300]));
    }

    public function test_a_gap_too_small_in_the_middle_renumbers_everything(): void {
        $this->assertSame([100, 200, 300], $this->reassign([10, 99, 11]));
    }

    public function test_no_room_below_the_first_anchor_renumbers_everything(): void {
        $this->assertSame([100, 200, 300], $this->reassign([3, 2, 1]));
    }

    public function test_appending_after_the_last_anchor_has_no_upper_bound(): void {
        $this->assertSame([100, 200, 201], $this->reassign([100, 200, 50]));
    }

    public function test_two_equal_values_force_one_of_them_to_move(): void {
        $this->assertSame([99, 100], $this->reassign([100, 100]));
    }

    public function test_every_redistributed_value_stays_above_zero(): void {
        $this->assertSame([100, 200, 300], $this->reassign([0, 0, 0]));
        $this->assertSame([100, 200, 300], $this->reassign([1, 1, 1]));
    }

    public function test_an_empty_input_yields_an_empty_result(): void {
        $this->assertSame([], Ranking::reassign([]));
    }

    public function test_a_single_row_is_left_untouched(): void {
        $this->assertSame([42], $this->reassign([42]));
    }

    public function test_the_anchor_tie_break_copies_the_suboptimal_choice_of_the_original(): void {
        $this->assertSame([100, 200], $this->reassign([5, 1]));
        $this->assertSame([100, 200, 300, 400], $this->reassign([100, 200, 102, 101]));
    }

}
