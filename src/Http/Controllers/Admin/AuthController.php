<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\AuthService;

class AuthController extends BaseController {

    public function __construct(private AuthService $service) {}

    #[Action(scope: 'anonymous')]
    public function captcha() {
        return $this->service->captcha();
    }

    #[Action(scope: 'anonymous', middleware: 'throttle:matrix-login')]
    public function login(Request $request) {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
            'token' => ['required'],
            'code' => ['required']
        ]);

        $data = $this->service->login($request->username, $request->password, $request->token, $request->code);

        return response()
            ->json(['success' => true, 'data' => $data])
            ->cookie('matrix-user', $data['token'], 0, '/', null, true, true, false, 'lax');
    }

    #[Action]
    public function logout(Request $request) {
        $this->service->logout($request->cookie('matrix-user') ?? $request->bearerToken());

        return response()
            ->json(['success' => true])
            ->withoutCookie('matrix-user');
    }

    #[Action]
    public function passwd(Request $request) {
        $request->validate([
            'current' => ['required'],
            'password' => ['required', 'different:current', 'regex:' . cfg('admin.password-pattern')]
        ]);

        $this->service->passwd($request->user()->id, $request->current, $request->password);
    }

    #[Action]
    public function profile(Request $request) {
        return $this->service->profile($request->user());
    }

}
