<?php //>

namespace MatrixPlatform\Models\Builders;

use MatrixPlatform\Models\Member;

/**
 * @extends BaseBuilder<Member>
 */
class MemberBuilder extends BaseBuilder {

    public function whereEnabled(): static {
        return $this->where('status', Member::ENABLED);
    }

}
