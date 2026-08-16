<?php //>

namespace MatrixPlatform\Http\Middleware;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\Member;

/**
 * @extends IdentityMiddleware<Member>
 */
class MemberMiddleware extends IdentityMiddleware {

    protected function assign(Model $subject): void {
        actor()->setMember($subject);
    }

    protected function subject(int $id): ?Member {
        $class = $this->configured('member-model', Member::class);

        return $class::query()
            ->whereKey($id)
            ->whereEnabled()
            ->first();
    }

    protected function type(): IdentityType {
        return IdentityType::Member;
    }

}
