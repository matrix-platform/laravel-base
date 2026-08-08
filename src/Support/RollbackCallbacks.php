<?php //>

namespace MatrixPlatform\Support;

use Closure;

class RollbackCallbacks {

    /**
     * @var list<Closure>
     */
    private array $callbacks = [];

    public function register(Closure $callback): void {
        $this->callbacks[] = $callback;
    }

    public function run(): void {
        $callbacks = $this->callbacks;

        $this->callbacks = [];

        foreach ($callbacks as $callback) {
            $callback();
        }
    }

}
