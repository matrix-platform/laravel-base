<?php //>

namespace MatrixPlatform\Support;

class Resources {

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    private static function combine(array $base, array $patch): array {
        foreach ($patch as $key => $value) {
            $existing = array_get_value($base, $key);

            $base[$key] = is_array($value) && !array_is_list($value) && is_array($existing) && !array_is_list($existing)
                ? self::combine($existing, $value)
                : $value;
        }

        return $base;
    }

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $bundles = [];

    public function __construct(private PackageRegistry $packages) {}

    public function config(string $token, mixed $default = null): mixed {
        [$name, $key] = $this->split($token);

        return array_get_value($this->getConfigBundle($name), $key, $default);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfigBundle(string $name): ?array {
        return $this->getBundle("cfg/{$name}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getI18nBundle(string $name, ?string $locale = null): ?array {
        $locale = $locale === null ? app()->getLocale() : $locale;

        return $this->getBundle("i18n/{$locale}/{$name}");
    }

    public function translate(string $token, ?string $locale = null): string {
        [$name, $key] = $this->split($token);

        $value = array_get_value($this->getI18nBundle($name, $locale), $key, $token);

        return is_string($value) ? $value : $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getBundle(string $name): ?array {
        if (!array_key_exists($name, $this->bundles)) {
            $this->bundles[$name] = $this->merge($name);
        }

        return $this->bundles[$name];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function load(string $path): ?array {
        if (!is_file($path)) {
            return null;
        }

        $data = require $path;

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function merge(string $name): ?array {
        $bundle = null;

        foreach ($this->packages->paths() as $path) {
            $data = $this->load("{$path}/resources/{$name}.php");

            if ($data !== null) {
                $bundle = $bundle === null ? $data : self::combine($data, $bundle);
            }
        }

        return $bundle;
    }

    /**
     * @return array{string, string}
     */
    private function split(string $token): array {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            error('invalid-resource-token');
        }

        return [$parts[0], $parts[1]];
    }

}
