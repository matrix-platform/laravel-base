<?php //>

namespace MatrixPlatform\Support\Scaffold;

use Illuminate\Filesystem\Filesystem;

class FileWriter {

    public function __construct(private Filesystem $files) {}

    public function exists(string $path): bool {
        return $this->files->exists($path);
    }

    public function write(string $path, string $content, bool $force): bool {
        if ($this->files->exists($path) && !$force) {
            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return true;
    }

}
