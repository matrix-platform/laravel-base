<?php //>

namespace MatrixPlatform\Support;

class RollbackCallbacks {

    private array $callbacks = [];

    public function register($callback) {
        $this->callbacks[] = $callback;
    }

    public function run() {
        foreach ($this->callbacks as $callback) {
            $callback();
        }

        $this->callbacks = [];
    }

}
