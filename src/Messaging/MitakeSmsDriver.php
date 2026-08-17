<?php //>

namespace MatrixPlatform\Messaging;

use Illuminate\Support\Facades\Http;
use MatrixPlatform\Models\MessageLog;
use MatrixPlatform\Models\SmsLog;

/**
 * @implements Driver<SmsLog>
 */
class MitakeSmsDriver implements Driver {

    private const CHANNEL = 'sms';

    private const NEWLINE = "\x06";

    private const PATH = '/api/mtk/SmSend?CharsetURL=UTF-8';

    public function send(MessageLog $log): string {
        $bundle = self::CHANNEL . '/' . $log->provider;
        $endpoint = strval(cfg("{$bundle}.endpoint"));

        if ($endpoint === '') {
            error('invalid-message-provider');
        }

        $sandbox = Sandbox::recipient($bundle);

        $response = Http::asForm()->post(rtrim($endpoint, '/') . self::PATH, [
            'username' => strval(cfg("{$bundle}.username")),
            'password' => strval(cfg("{$bundle}.password")),
            'dstaddr' => $sandbox === null ? $log->receiver : $sandbox,
            'smbody' => str_replace(["\r\n", "\n", "\r"], self::NEWLINE, $log->content),
            'clientid' => strval($log->id)
        ]);

        if (!$response->successful()) {
            error('request-failed');
        }

        $body = $response->body();
        $fields = $this->parse($body);
        $status = strval(array_get_value($fields, 'statuscode'));
        $reply = Sandbox::response($sandbox, $body);

        $log->response = $reply;

        if (!in_array($status, $this->accepted($bundle), true)) {
            $log->error = array_get_value($fields, 'Error');

            error('message-refused-by-provider');
        }

        return $reply;
    }

    /**
     * @return list<string>
     */
    private function accepted(string $bundle): array {
        $accepted = cfg("{$bundle}.accepted-status");

        return is_array($accepted) ? array_values(array_map(strval(...), $accepted)) : [];
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $body): array {
        $fields = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $pair = explode('=', trim($line), 2);

            if (count($pair) === 2) {
                $fields[trim($pair[0])] = trim($pair[1]);
            }
        }

        return $fields;
    }

}
