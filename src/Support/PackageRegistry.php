<?php //>

namespace MatrixPlatform\Support;

use Generator;

class PackageRegistry {

    /**
     * @var array<string, string>
     */
    private const MODEL_ROOTS = [
        'src/Models' => 'MatrixPlatform\\Models',
        'app/Models' => 'App\\Models'
    ];

    /**
     * @var array<string, string>
     */
    private array $packages = [];

    /**
     * @return Generator<int, string>
     */
    public function models(): Generator {
        foreach ($this->paths() as $root) {
            foreach (self::MODEL_ROOTS as $directory => $namespace) {
                foreach (glob("{$root}/{$directory}/*.php") ?: [] as $file) {
                    yield "{$namespace}\\" . basename($file, '.php');
                }
            }
        }
    }

    public function path(string $name): string {
        if (!array_key_exists($name, $this->packages)) {
            error('unknown-package');
        }

        return $this->packages[$name];
    }

    /**
     * @return list<string>
     */
    public function paths(): array {
        $packages = config('matrix.packages');

        return array_map(fn (string $name): string => $this->path($name), tokenize(is_string($packages) ? $packages : null));
    }

    public function register(string $name, string $path): void {
        $this->packages[$name] = rtrim($path, '/');
    }

}
