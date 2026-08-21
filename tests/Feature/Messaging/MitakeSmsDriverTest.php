<?php //>

namespace Tests\Feature\Messaging;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MatrixPlatform\Messaging\MessageStatus;
use MatrixPlatform\Messaging\MitakeSmsDriver;
use MatrixPlatform\Models\SmsLog;
use Tests\FeatureTestCase;

class MitakeSmsDriverTest extends FeatureTestCase {

    private const ACCEPTED = "msgid=0355319484\r\nstatuscode=1\r\nAccountPoint=292\r\n";

    private const REFUSED = "[1]\r\n\r\nstatuscode=e\r\n\r\nError=Account or password incorrect\r\n";

    protected function setUp(): void {
        parent::setUp();

        $this->useMessagingFixtures();
    }

    private function log(string $provider = 'mitake'): SmsLog {
        $log = new SmsLog();

        $log->provider = $provider;
        $log->receiver = '0912345678';
        $log->content = 'Your code is 123456';
        $log->schedule_time = now();
        $log->status = MessageStatus::Scheduled;

        $log->save();

        return $log;
    }

    public function test_the_request_follows_the_shape_the_vendor_documents(): void {
        Http::fake(['*' => Http::response(self::ACCEPTED)]);

        (new MitakeSmsDriver())->send($this->log());

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://sms.example.test/api/mtk/SmSend?CharsetURL=UTF-8', $request->url());
            $this->assertStringContainsString('application/x-www-form-urlencoded', implode(' ', $request->header('Content-Type')));

            $this->assertSame('mitake-account', $request['username']);
            $this->assertSame('mitake-secret', $request['password']);
            $this->assertSame('0912345678', $request['dstaddr']);
            $this->assertSame('Your code is 123456', $request['smbody']);

            return true;
        });
    }

    public function test_the_record_id_is_sent_as_the_vendor_deduplication_key(): void {
        Http::fake(['*' => Http::response(self::ACCEPTED)]);

        $log = $this->log();

        (new MitakeSmsDriver())->send($log);

        Http::assertSent(fn (Request $request) => $request['clientid'] === strval($log->id));
    }

    public function test_newlines_are_sent_as_the_control_character_the_vendor_requires(): void {
        Http::fake(['*' => Http::response(self::ACCEPTED)]);

        $log = $this->log();

        $log->content = "first line\r\nsecond line\nthird line";

        (new MitakeSmsDriver())->send($log);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame("first line\x06second line\x06third line", $request['smbody']);
            $this->assertStringNotContainsString("\n", strval($request['smbody']));

            return true;
        });
    }

    public function test_every_delivery_state_the_vendor_documents_as_reached_is_accepted(): void {
        $this->assertSame('0 1 2 4', cfg('mitake.accepted-status'));
    }

    public function test_a_message_the_vendor_reports_as_delivered_to_the_handset_is_not_a_failure(): void {
        Http::fake(['*' => Http::response("statuscode=4\r\n")]);

        $log = $this->log();

        $this->assertSame("statuscode=4\r\n", (new MitakeSmsDriver())->send($log));
        $this->assertNull($log->error);
    }

    public function test_an_accepted_message_returns_the_whole_vendor_reply(): void {
        Http::fake(['*' => Http::response(self::ACCEPTED)]);

        $log = $this->log();

        $this->assertSame(self::ACCEPTED, (new MitakeSmsDriver())->send($log));
        $this->assertSame(self::ACCEPTED, $log->response);
        $this->assertNull($log->error);
    }

    public function test_a_refused_message_keeps_the_reply_and_records_the_vendor_message(): void {
        Http::fake(['*' => Http::response(self::REFUSED)]);

        $log = $this->log();

        $this->refuses('message-refused-by-provider', fn () => (new MitakeSmsDriver())->send($log));

        $this->assertSame(self::REFUSED, $log->response);
        $this->assertSame('Account or password incorrect', $log->error);
    }

    public function test_a_refusal_the_vendor_did_not_explain_falls_back_to_the_package_slug(): void {
        Http::fake(['*' => Http::response("statuscode=8\r\n")]);

        $log = $this->log();

        $this->refuses('message-refused-by-provider', fn () => (new MitakeSmsDriver())->send($log));

        $this->assertNull($log->error);
        $this->assertSame("statuscode=8\r\n", $log->response);
    }

    public function test_which_status_codes_count_as_accepted_is_configuration_not_code(): void {
        Http::fake(['*' => Http::response(self::REFUSED)]);

        $this->refuses('message-refused-by-provider', fn () => (new MitakeSmsDriver())->send($this->log()));

        $lenient = (new MitakeSmsDriver())->send($this->log('mitake-lenient'));

        $this->assertSame(self::REFUSED, $lenient);
    }

    public function test_the_section_header_and_blank_lines_the_vendor_sends_are_ignored(): void {
        Http::fake(['*' => Http::response(self::REFUSED)]);

        $log = $this->log();

        $this->refuses('message-refused-by-provider', fn () => (new MitakeSmsDriver())->send($log));

        $this->assertSame('Account or password incorrect', $log->error);
    }

    public function test_a_transport_level_failure_is_reported_as_a_failed_request(): void {
        Http::fake(['*' => Http::response('', 500)]);

        $this->refuses('request-failed', fn () => (new MitakeSmsDriver())->send($this->log()));
    }

    public function test_a_provider_without_an_endpoint_is_refused_before_any_request(): void {
        Http::fake();

        $this->refuses('invalid-message-provider', fn () => (new MitakeSmsDriver())->send($this->log('mitake-bare')));

        Http::assertNothingSent();
    }

    public function test_a_sandboxed_provider_sends_to_the_sink_instead(): void {
        Http::fake(['*' => Http::response(self::ACCEPTED)]);

        $response = (new MitakeSmsDriver())->send($this->log('mitake-sandboxed'));

        $this->assertStringStartsWith('sandbox:', $response);

        Http::assertSent(fn (Request $request) => $request['dstaddr'] === '0900000000');
    }

}
