<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Http\IdentityToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\AuthService;

class AuthController extends BaseController {

    public function __construct(private AuthService $service) {}

    /**
     * @return array{token: string, image: string}
     */
    #[Action(scope: 'anonymous')]
    public function captcha(): array {
        return $this->service->captcha();
    }

    #[Action(scope: 'anonymous', middleware: 'login-throttle-api:admin')]
    public function login(Request $request): JsonResponse {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
            'token' => ['required'],
            'code' => ['required']
        ]);

        $data = $this->service->login(
            $request->string('username')->value(),
            $request->string('password')->value(),
            $request->string('token')->value(),
            $request->string('code')->value()
        );

        return IdentityToken::attach(response()->json(['success' => true, 'data' => $data]), IdentityType::User, $data['token']);
    }

    #[Action]
    public function logout(Request $request): JsonResponse {
        $this->service->logout(IdentityToken::from($request, IdentityType::User));

        return response()->json(['success' => true])->withoutCookie(IdentityType::User->cookie());
    }

    #[Action]
    public function passwd(Request $request): void {
        $request->validate([
            'current' => ['required'],
            'password' => ['required', 'different:current', 'regex:' . cfg('admin.password-pattern')]
        ]);

        $this->service->passwd(actor()->requireUser(), $request->string('current')->value(), $request->string('password')->value(), IdentityToken::from($request, IdentityType::User));
    }

    /**
     * @return array{nodes: array<string, array<string, mixed>>, profile: ?User}
     */
    #[Action(scope: 'user-aware')]
    public function profile(): array {
        return $this->service->profile(actor()->user());
    }

}
