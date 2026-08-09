<?php //>

namespace MatrixPlatform\Models\Builders;

use MatrixPlatform\Models\User;

/**
 * @extends BaseBuilder<User>
 */
class UserBuilder extends BaseBuilder {

    public function whereEnabled(): static {
        return $this->where('disabled', false)->whereActive();
    }

}
