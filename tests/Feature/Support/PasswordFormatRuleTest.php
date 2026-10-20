<?php //>

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Tests\FeatureTestCase;

class PasswordFormatRuleTest extends FeatureTestCase {

    public function test_a_password_is_checked_against_the_pattern_of_the_named_bundle(): void {
        $this->assertTrue(Validator::make(['p' => 'secret-Passw0rd'], ['p' => 'password_format:admin'])->passes());
        $this->assertFalse(Validator::make(['p' => 'short'], ['p' => 'password_format:admin'])->passes());
    }

    public function test_a_bundle_without_a_pattern_is_reported_clearly(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cfg nonexistent.password-pattern is not defined');

        Validator::make(['p' => 'secret-Passw0rd'], ['p' => 'password_format:nonexistent'])->passes();
    }

    public function test_the_rule_without_a_bundle_is_reported_clearly(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('password_format requires a cfg bundle');

        Validator::make(['p' => 'secret-Passw0rd'], ['p' => 'password_format'])->passes();
    }

}
