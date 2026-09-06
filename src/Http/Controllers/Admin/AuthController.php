<?php //>

namespace MatrixPlatform\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MatrixPlatform\Attributes\Action;
use MatrixPlatform\Http\Controllers\BaseController;
use MatrixPlatform\Http\IdentityToken;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\UserLogType;
use MatrixPlatform\Services\Admin\AuthService;
use MatrixPlatform\Services\Admin\MfaService;
use MatrixPlatform\Services\Admin\Passkey\PasskeyService;
use MatrixPlatform\Services\Admin\PasswordService;

class AuthController extends BaseController {

    private const TRUST_COOKIE = 'matrix-mfa-trust';

    public function __construct(private AuthService $service, private MfaService $mfa, private PasswordService $passwords, private PasskeyService $passkeys) {}

    /**
     * @return array{token: string, image: string}
     */
    #[Action(scope: 'anonymous')]
    public function captcha(): array {
        return $this->service->captcha();
    }

    #[Action('mfa/confirm')]
    public function confirmMfa(Request $request): void {
        $request->validate(['code' => ['required']]);

        $user = actor()->requireUser();
        $this->mfa->confirm($user, $request->string('code')->value());

        $user->writeLog(UserLogType::MfaEnabled);
    }

    #[Action('mfa/disable')]
    public function disableMfa(Request $request): void {
        $request->validate(['password' => ['required']]);

        $user = actor()->requireUser();

        if (!$this->passwords->verify($user, $request->string('password')->value())) {
            invalid('password', 'invalid-password');
        }

        $this->mfa->disable($user);

        $user->writeLog(UserLogType::MfaDisabled);
    }

    #[Action(scope: 'anonymous', middleware: 'login-throttle-api:admin')]
    public function login(Request $request): JsonResponse {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
            'token' => ['required'],
            'code' => ['required']
        ]);

        $trust = $request->cookie(self::TRUST_COOKIE);

        $data = $this->service->login(
            $request->string('username')->value(),
            $request->string('password')->value(),
            $request->string('token')->value(),
            $request->string('code')->value(),
            is_string($trust) ? $trust : null
        );

        $response = response()->json(['success' => true, 'data' => $data]);

        return isset($data['token']) ? IdentityToken::attach($response, IdentityType::User, $data['token']) : $response;
    }

    #[Action]
    public function logout(Request $request): JsonResponse {
        $this->service->logout(IdentityToken::from($request, IdentityType::User));

        return response()->json(['success' => true])->withoutCookie(IdentityType::User->cookie());
    }

    #[Action(scope: 'anonymous', middleware: 'login-throttle-api:admin')]
    public function mfa(Request $request): JsonResponse {
        $request->validate([
            'username' => ['required'],
            'challenge' => ['required'],
            'code' => ['required'],
            'remember' => ['sometimes', 'boolean']
        ]);

        $data = $this->service->mfa(
            $request->string('username')->value(),
            $request->string('challenge')->value(),
            $request->string('code')->value(),
            $request->boolean('remember')
        );

        $trust = array_get_value($data, 'trust');
        unset($data['trust']);

        $response = IdentityToken::attach(response()->json(['success' => true, 'data' => $data]), IdentityType::User, $data['token']);

        if ($trust !== null) {
            IdentityToken::cookie($response, self::TRUST_COOKIE, $trust, (int) cfg('admin.mfa-trust-days') * 1440);
        }

        return $response;
    }

    #[Action('passkey/login', scope: 'anonymous', middleware: 'login-throttle-api:admin')]
    public function passkeyLogin(Request $request): JsonResponse {
        $request->validate([
            'challenge' => ['required'],
            'credential' => ['required', 'array']
        ]);

        $user = $this->passkeys->authenticate($request->string('challenge')->value(), $request->array('credential'));
        $token = $user->createToken();

        return IdentityToken::attach(response()->json(['success' => true, 'data' => ['token' => $token]]), IdentityType::User, $token);
    }

    /**
     * @return array{options: array<string, mixed>, challenge: string}
     */
    #[Action('passkey/options', scope: 'anonymous')]
    public function passkeyLoginOptions(): array {
        return $this->passkeys->authenticationOptions();
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

    /**
     * @return array{secret: string, uri: string}
     */
    #[Action('mfa/setup')]
    public function setupMfa(): array {
        return $this->mfa->setup(actor()->requireUser());
    }

}
