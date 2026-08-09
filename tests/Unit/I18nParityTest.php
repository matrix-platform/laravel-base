<?php //>

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class I18nParityTest extends TestCase {

    public function test_every_locale_declares_the_same_bundles(): void {
        $bundles = $this->bundles();

        $this->assertGreaterThan(1, count($bundles));

        $expected = array_keys(reset($bundles) ?: []);

        sort($expected);

        foreach ($bundles as $locale => $names) {
            $found = array_keys($names);

            sort($found);

            $this->assertSame($expected, $found, "locale '{$locale}' declares a different set of bundles");
        }
    }

    public function test_every_bundle_declares_the_same_keys_in_every_locale(): void {
        $bundles = $this->bundles();
        $reference = array_key_first($bundles);

        $this->assertNotNull($reference);

        foreach ($bundles[$reference] as $name => $keys) {
            foreach ($bundles as $locale => $names) {
                $this->assertArrayHasKey($name, $names, "bundle '{$name}' is missing for locale '{$locale}'");
                $this->assertSame($keys, $names[$name], "bundle '{$name}' differs between '{$reference}' and '{$locale}'");
            }
        }
    }

    public function test_the_scanner_actually_finds_the_shipped_bundles(): void {
        $bundles = $this->bundles();

        $this->assertArrayHasKey('en', $bundles);
        $this->assertArrayHasKey('errors', $bundles['en']);
        $this->assertContains('permission-denied', $bundles['en']['errors']);
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private function bundles(): array {
        $root = __DIR__ . '/../../resources/i18n';
        $bundles = [];

        foreach (array_merge(glob("{$root}/*/*.php") ?: [], glob("{$root}/*/*/*.php") ?: []) as $file) {
            $relative = substr($file, strlen($root) + 1);
            $locale = explode('/', $relative)[0];
            $messages = require $file;

            $this->assertIsArray($messages, "{$file} must return an array");

            $keys = array_map(strval(...), array_keys($messages));

            sort($keys);

            $bundles[$locale][substr($relative, strlen($locale) + 1, -4)] = $keys;
        }

        return $bundles;
    }

}
