<?php //>

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\FeatureTestCase;
use ZipArchive;

class UpdateGeolocationDatabaseCommandTest extends FeatureTestCase {

    protected function setUp(): void {
        parent::setUp();

        Storage::fake('local');
    }

    private function dispatch(): PendingCommand {
        return $this->artisanCommand('geolocation:update-database');
    }

    private function zip(string $entry, string $contents): string {
        $path = tempnam(sys_get_temp_dir(), 'zip-fixture');

        $archive = new ZipArchive();

        $archive->open($path, ZipArchive::OVERWRITE);
        $archive->addFromString($entry, $contents);
        $archive->close();

        $bytes = strval(file_get_contents($path));

        unlink($path);

        return $bytes;
    }

    public function test_a_successful_download_replaces_the_stored_database_file(): void {
        $this->useCfg('ip2location-bin', ['bin-path' => 'ip2location-update-test.bin', 'download-token' => 'test-token']);

        Http::fake(['*' => Http::response($this->zip('IP2LOCATION-LITE-DB11.BIN', 'new-bin-contents'))]);

        $this->dispatch()->assertSuccessful();

        $this->assertSame('new-bin-contents', Storage::disk('local')->get('ip2location-update-test.bin'));
        $this->assertFalse(Storage::disk('local')->exists('ip2location-update-test.bin.tmp'));
    }

    public function test_a_download_that_is_not_a_zip_archive_leaves_the_existing_database_file_untouched(): void {
        $this->useCfg('ip2location-bin', ['bin-path' => 'ip2location-update-test.bin', 'download-token' => 'test-token']);

        Storage::disk('local')->put('ip2location-update-test.bin', 'old-bin-contents');

        Http::fake(['*' => Http::response('NO PERMISSION')]);

        $this->dispatch()->assertFailed();

        $this->assertSame('old-bin-contents', Storage::disk('local')->get('ip2location-update-test.bin'));
    }

    public function test_an_empty_download_token_fails_before_any_request_is_sent(): void {
        $this->useCfg('ip2location-bin', ['download-token' => '']);

        Http::fake();

        $this->dispatch()->assertFailed();

        Http::assertNothingSent();
    }

}
