<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\AuthService;

class AuthController extends BaseController {

    public function __construct(private AuthService $service) {}

    #[Action('anonymous')]
    public function captcha() {
        return $this->service->captcha();
    }

    #[Action('anonymous')]
    public function login(Request $request) {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
            'token' => ['required'],
            'code' => ['required']
        ]);

        return $this->service->login($request->username, $request->password, $request->token, $request->code);
    }

    #[Action]
    public function logout(Request $request) {
        $this->service->logout($request->bearerToken());
    }

    #[Action]
    public function passwd(Request $request) {
        $request->validate([
            'current' => ['required'],
            'password' => ['required', 'different:current', 'regex:' . cfg('admin.password-pattern')]
        ]);

        $this->service->passwd(USER_ID, $request->current, $request->password);
    }

    #[Action]
    public function profile(Request $request) {
        return $this->service->profile($request->user());
    }

}
