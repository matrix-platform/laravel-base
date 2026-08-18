<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Support\Facades\DB;

class Resources {

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    private static function combine(array $base, array $patch): array {
        foreach ($patch as $key => $value) {
            $existing = array_get_value($base, $key);

            $base[$key] = is_array($value) && !array_is_list($value) && is_array($existing) && !array_is_list($existing) ? self::combine($existing, $value) : $value;
        }

        return $base;
    }

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $bundles = [];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $overrides = null;

    public function __construct(private PackageRegistry $packages) {}

    /**
     * @return list<string>
     */
    public function bundleNames(string $directory): array {
        $names = [];

        foreach ($this->packages->paths() as $path) {
            foreach (glob("{$path}/resources/{$directory}/*.php") ?: [] as $file) {
                $names[basename($file, '.php')] = true;
            }
        }

        ksort($names);

        return array_map(strval(...), array_keys($names));
    }

    public function config(string $token, mixed $default = null): mixed {
        [$name, $key] = $this->split($token);

        return array_get_value($this->getConfigBundle($name), $key, $default);
    }

    public function forget(): void {
        $this->bundles = [];
        $this->overrides = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBundle(string $name): ?array {
        if (!array_key_exists($name, $this->bundles)) {
            $defaults = $this->merge($name);
            $override = $this->getOverrides($name);

            $this->bundles[$name] = $defaults === null || $override === null ? $defaults : self::combine($defaults, $override);
        }

        return $this->bundles[$name];
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
    public function getDefaults(string $name): ?array {
        return $this->merge($name);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getI18nBundle(string $name, ?string $locale = null): ?array {
        $locale = $locale === null ? app()->getLocale() : $locale;

        return $this->getBundle("i18n/{$locale}/{$name}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMenuBundle(string $name): ?array {
        return $this->getBundle("menu/{$name}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOverrides(string $name): ?array {
        return array_get_value($this->overrides(), $name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStyleBundle(string $id): array {
        $bundle = $this->getBundle("style/{$id}");

        return $bundle === null ? [] : $bundle;
    }

    public function translate(string $token, ?string $locale = null): string {
        [$name, $key] = $this->split($token);

        $value = array_get_value($this->getI18nBundle($name, $locale), $key, $token);

        return is_string($value) ? $value : $token;
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
     * @return array<string, array<string, mixed>>
     */
    private function overrides(): array {
        if ($this->overrides === null) {
            $this->overrides = [];

            foreach (DB::table('base_resource_override')->get(['bundle', 'data']) as $row) {
                $data = json_decode(strval($row->data), true);

                if (is_array($data)) {
                    $this->overrides[strval($row->bundle)] = $data;
                }
            }
        }

        return $this->overrides;
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
