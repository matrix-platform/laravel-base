<?php //>

namespace MatrixPlatform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use MatrixPlatform\Http\IdentityToken;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\IdentityType;

/**
 * @template TSubject of Model
 */
abstract class IdentityMiddleware {

    public function handle(Request $request, Closure $next): mixed {
        $auth = AuthToken::findByToken(IdentityToken::from($request, $this->type()), $this->type());
        $subject = $auth === null ? null : $this->subject($auth->target_id);

        if ($auth === null || $subject === null) {
            return $this->refuse($request, $next);
        }

        $auth->keepAlive();

        $request->setUserResolver(fn () => $subject);

        $this->assign($subject);

        return $next($request);
    }

    /**
     * @param TSubject $subject
     */
    abstract protected function assign(Model $subject): void;

    /**
     * @template TModel of Model
     * @param class-string<TModel> $base
     * @return class-string<TModel>
     */
    protected function configured(string $key, string $base): string {
        $class = config("matrix.{$key}");

        if (!is_string($class) || !is_a($class, $base, true)) {
            error('invalid-identity-model');
        }

        return $class;
    }

    protected function refuse(Request $request, Closure $next): mixed {
        error('invalid-token', 401);
    }

    /**
     * @return TSubject|null
     */
    abstract protected function subject(int $id): ?Model;

    abstract protected function type(): IdentityType;

}
