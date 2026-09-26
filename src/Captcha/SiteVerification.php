<?php //>

namespace MatrixPlatform\Captcha;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait SiteVerification {

    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 5;

    private function permitted(string $bundle, mixed $hostname): bool {
        $allowed = tokenize(strval(cfg("{$bundle}.hostnames")));

        return $allowed === [] || in_array(strval($hostname), $allowed, true);
    }

    private function siteverify(string $bundle, string $token): ?Response {
        $endpoint = strval(cfg("{$bundle}.endpoint"));
        $secret = strval(cfg("{$bundle}.secret"));

        $response = Http::asForm()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->post($endpoint, ['secret' => $secret, 'response' => $token]);

        if ($response->failed()) {
            error('captcha-request-failed');
        }

        if ($response->json('success') !== true || $response->json('action') !== strval(cfg("{$bundle}.action"))) {
            return null;
        }

        if (!$this->permitted($bundle, $response->json('hostname'))) {
            return null;
        }

        return $response;
    }

}
