<?php //>

namespace MatrixPlatform\Support;

class PackageRegistry {

    /**
     * @var array<string, string>
     */
    private array $packages = [];

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
