<?php //>

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CfgReadonlyTest extends TestCase {

    private const GUARDED = ['driver', 'endpoint', 'host'];

    public function test_drivers_and_secret_destinations_cannot_be_edited_online(): void {
        foreach (glob(__DIR__ . '/../../resources/cfg/*.php') ?: [] as $file) {
            $bundle = basename($file, '.php');
            $values = require $file;

            $this->assertIsArray($values, "{$file} must return an array");

            foreach (array_intersect(self::GUARDED, array_keys($values)) as $key) {
                $this->assertTrue($this->readonly($bundle, $key), "cfg key '{$bundle}.{$key}' must be readonly");
            }
        }
    }

    public function test_the_captcha_action_cannot_be_edited_online(): void {
        foreach (['captcha-recaptcha', 'captcha-turnstile'] as $bundle) {
            $this->assertTrue($this->readonly($bundle, 'action'), "cfg key '{$bundle}.action' must be readonly");
        }
    }

    private function readonly(string $bundle, string $key): bool {
        $file = __DIR__ . "/../../resources/style/cfg/{$bundle}.php";
        $meta = array_get_value(is_file($file) ? require $file : [], $key);

        return is_array($meta) && array_get_value($meta, 'readonly') === true;
    }

}
