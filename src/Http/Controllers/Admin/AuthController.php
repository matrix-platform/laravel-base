<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\Request;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Services\Admin\AuthService;

class AuthController extends BaseController {

    private AuthService $service;

    public function __construct(AuthService $service) {
        $this->service = $service;
    }

    public function captcha() {
        return response()->success($this->service->captcha());
    }

    public function login(Request $request) {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'token' => 'required|string',
            'code' => 'required|string'
        ]);

        return response()->success($this->service->login($request->username, $request->password, $request->token, $request->code));
    }

    public function logout(Request $request) {
        $this->service->logout($request->bearerToken());

        return response()->success();
    }

    public function passwd(Request $request) {
        $request->validate([
            'current' => 'required|string',
            'password' => 'required|string'
        ]);

        $this->service->passwd($request->user()->id, $request->current, $request->password);

        return response()->success();
    }

    public function profile(Request $request) {
        return response()->success($this->service->profile($request->user()));
    }

}
