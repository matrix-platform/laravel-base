<?php //>

namespace MatrixPlatform\Models\Generators;

class Creator {

    public function generate() {
        if (defined('USER_ID')) {
            return USER_ID;
        }

        if (defined('MEMBER_ID')) {
            return MEMBER_ID;
        }

        return null;
    }

}
