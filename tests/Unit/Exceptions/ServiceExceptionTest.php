<?php //>

namespace Tests\Unit\Exceptions;

use MatrixPlatform\Exceptions\ServiceException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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

    public function test_source_never_reaches_for_the_resource_system(): void {
        $file = (new ReflectionClass(ServiceException::class))->getFileName();

        $this->assertIsString($file);

        $source = (string) file_get_contents($file);

        $this->assertStringNotContainsString('i18n(', $source);
        $this->assertStringNotContainsString('cfg(', $source);
        $this->assertStringNotContainsString('app(', $source);
    }

}
