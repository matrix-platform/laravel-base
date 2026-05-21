<?php //>

namespace MatrixPlatform\Models\Generators;

class Updater {

    public function regenerate() {
        return user()->id ?: member()->id;
    }

}
