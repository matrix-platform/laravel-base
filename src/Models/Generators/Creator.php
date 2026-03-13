<?php //>

namespace MatrixPlatform\Models\Generators;

class Creator {

    public function generate() {
        return user()->id ?? member()->id;
    }

}
