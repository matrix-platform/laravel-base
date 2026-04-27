<?php //>

namespace MatrixPlatform\Support;

class ActorProxy {

    public function __construct(private $model = null) {}

    public function __get($name) {
        return $this->model?->$name;
    }

    public function __set($name, $value) {
        error('unsupported-operation');
    }

}
