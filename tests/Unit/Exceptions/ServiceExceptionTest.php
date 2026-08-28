<?php //>

namespace Tests\Unit\Exceptions;

use MatrixPlatform\Exceptions\ServiceException;
use PHPUnit\Framework\TestCase;

class ServiceExceptionTest extends TestCase {

    public function test_carries_error_slug_and_defaults_to_500(): void {
        $exception = new ServiceException('data-not-found');

        $this->assertSame('data-not-found', $exception->getError());
        $this->assertSame(500, $exception->getCode());
    }

    public function test_accepts_explicit_code(): void {
        $exception = new ServiceException('permission-denied', 403);

        $this->assertSame(403, $exception->getCode());
    }

    public function test_message_is_the_untranslated_slug(): void {
        $exception = new ServiceException('data-not-found');

        $this->assertSame('data-not-found', $exception->getMessage());
    }

    public function test_extra_defaults_to_empty(): void {
        $exception = new ServiceException('data-not-found');

        $this->assertSame([], $exception->getExtra());
    }

    public function test_accepts_extra_payload(): void {
        $exception = new ServiceException('invalid-password', 422, ['fields' => ['current' => ['invalid-password']]]);

        $this->assertSame(['fields' => ['current' => ['invalid-password']]], $exception->getExtra());
    }

}
