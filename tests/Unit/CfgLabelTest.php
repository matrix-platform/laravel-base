<?php //>

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CfgLabelTest extends TestCase {

    public function test_every_cfg_bundle_has_a_title(): void {
        $labels = $this->labels();

        foreach ($this->bundles() as $bundle => $keys) {
            $this->assertArrayHasKey("cfg/{$bundle}", $labels, "cfg bundle '{$bundle}' has no title");
        }
    }

    public function test_every_cfg_key_has_a_title(): void {
        $labels = $this->labels();

        foreach ($this->bundles() as $bundle => $keys) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey("cfg/{$bundle}.{$key}", $labels, "cfg key '{$bundle}.{$key}' has no title");
            }
        }
    }

    public function test_the_scanner_actually_finds_the_shipped_cfg_bundles(): void {
        $bundles = $this->bundles();

        $this->assertArrayHasKey('admin', $bundles);
        $this->assertContains('password-pattern', $bundles['admin']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function bundles(): array {
        $bundles = [];

        foreach (glob(__DIR__ . '/../../resources/cfg/*.php') ?: [] as $file) {
            $values = require $file;

            $this->assertIsArray($values, "{$file} must return an array");

            $bundles[basename($file, '.php')] = array_map(strval(...), array_keys($values));
        }

        return $bundles;
    }

    /**
     * @return array<string, mixed>
     */
    private function labels(): array {
        $labels = require __DIR__ . '/../../resources/i18n/tw/resource.php';

        $this->assertIsArray($labels);

        return $labels;
    }

}
