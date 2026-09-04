<?php //>

namespace Tests\Unit;

use MatrixPlatform\Exceptions\ServiceException;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase {

    public function test_error_throws_service_exception_with_slug_and_default_code(): void {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('data-not-found');
        $this->expectExceptionCode(500);

        error('data-not-found');
    }

    public function test_error_accepts_explicit_code(): void {
        $this->expectException(ServiceException::class);
        $this->expectExceptionCode(403);

        error('permission-denied', 403);
    }

    public function test_invalid_throws_a_422_validation_failed_exception_reporting_the_field(): void {
        $this->expectException(ServiceException::class);

        try {
            invalid('current', 'invalid-password');
        } catch (ServiceException $exception) {
            $this->assertSame('validation-failed', $exception->getError());
            $this->assertSame(422, $exception->getCode());
            $this->assertSame(['fields' => ['current' => ['invalid-password']]], $exception->getExtra());

            throw $exception;
        }
    }

    public function test_array_get_value_returns_value_when_key_exists(): void {
        $this->assertSame('v', array_get_value(['k' => 'v'], 'k'));
    }

    public function test_array_get_value_distinguishes_null_value_from_missing_key(): void {
        $this->assertNull(array_get_value(['k' => null], 'k', 'DEFAULT'));
        $this->assertSame('DEFAULT', array_get_value([], 'k', 'DEFAULT'));
    }

    public function test_array_get_value_treats_the_key_literally(): void {
        $data = ['a.b' => 'LITERAL', 'a' => ['b' => 'NESTED']];

        $this->assertSame('LITERAL', array_get_value($data, 'a.b'));
        $this->assertSame('NESTED', data_get($data, 'a.b'));
    }

    public function test_array_get_value_returns_default_for_null_array(): void {
        $this->assertSame('DEFAULT', array_get_value(null, 'k', 'DEFAULT'));
    }

    public function test_array_get_value_accepts_integer_keys(): void {
        $this->assertSame('first', array_get_value(['first', 'second'], 0));
    }

    public function test_tokenize_splits_on_whitespace_comma_and_semicolon(): void {
        $this->assertSame(['a', 'b', 'c'], tokenize('a b,c'));
        $this->assertSame(['a', 'b'], tokenize('a;;b'));
        $this->assertSame(['solo'], tokenize('solo'));
    }

    public function test_tokenize_returns_empty_list_for_empty_and_null(): void {
        $this->assertSame([], tokenize(''));
        $this->assertSame([], tokenize(null));
    }

}
