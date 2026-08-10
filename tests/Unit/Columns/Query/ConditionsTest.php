<?php //>

namespace Tests\Unit\Columns\Query;

use MatrixPlatform\Columns\Query\Conditions;
use MatrixPlatform\Columns\Syntax\Condition;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase {

    public function test_no_conditions_compile_to_an_empty_clause(): void {
        $this->assertSame(['', []], Conditions::compile([]));
    }

    public function test_a_plain_operator_binds_its_value(): void {
        $this->assertSame(
            ['trinkets.amount > ?', ['10']],
            Conditions::compile(['trinkets' => [new Condition('amount', '>', '10')]])
        );
    }

    public function test_null_and_not_null_bind_nothing(): void {
        $this->assertSame(
            ['trinkets.label IS NULL AND trinkets.amount IS NOT NULL', []],
            Conditions::compile(['trinkets' => [
                new Condition('label', 'NULL', null),
                new Condition('amount', 'NOT NULL', null)
            ]])
        );
    }

    public function test_in_expands_into_placeholders(): void {
        $this->assertSame(
            ['trinkets.amount IN (?,?,?)', ['1', '2', '3']],
            Conditions::compile(['trinkets' => [new Condition('amount', 'IN', ['1', '2', '3'])]])
        );
    }

    public function test_not_in_expands_into_placeholders(): void {
        $this->assertSame(
            ['trinkets.amount NOT IN (?,?)', ['1', '2']],
            Conditions::compile(['trinkets' => [new Condition('amount', 'NOT IN', ['1', '2'])]])
        );
    }

    public function test_the_three_like_operators_become_ilike(): void {
        $this->assertSame(
            ['trinkets.label ILIKE ? AND trinkets.label ILIKE ? AND trinkets.label ILIKE ?', ['a%', '%a', '%a%']],
            Conditions::compile(['trinkets' => [
                new Condition('label', '^=', 'a'),
                new Condition('label', '$=', 'a'),
                new Condition('label', '*=', 'a')
            ]])
        );
    }

    public function test_like_values_are_escaped(): void {
        $this->assertSame(
            ['trinkets.label ILIKE ?', ['100\\%\\_\\\\%']],
            Conditions::compile(['trinkets' => [new Condition('label', '^=', '100%_\\')]])
        );
    }

    public function test_conditions_across_two_aliases_are_joined_with_and(): void {
        $this->assertSame(
            ['trinkets.label = ? AND trinkets__widget.title = ?', ['a', 'b']],
            Conditions::compile([
                'trinkets' => [new Condition('label', '=', 'a')],
                'trinkets__widget' => [new Condition('title', '=', 'b')]
            ])
        );
    }

}
