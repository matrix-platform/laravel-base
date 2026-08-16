<?php //>

namespace MatrixPlatform\Http\Middleware;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;

/**
 * @extends IdentityMiddleware<User>
 */
class UserMiddleware extends IdentityMiddleware {

    protected function assign(Model $subject): void {
        actor()->setUser($subject);
    }

    protected function subject(int $id): ?User {
        return User::query()
            ->whereKey($id)
            ->whereEnabled()
            ->first();
    }

    protected function type(): IdentityType {
        return IdentityType::User;
    }

}
