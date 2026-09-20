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

        Storage::fake(config()->string('matrix.file-private-disk'));
    }

    private function archive(?string $name, string $contents): string {
        $path = tempnam(sys_get_temp_dir(), 'archive') . '.zip';
        $archive = new ZipArchive();

        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $archive->addFromString($name === null ? 'readme.txt' : $name, $contents);

        $archive->close();

        $bytes = strval(file_get_contents($path));

        unlink($path);

        return $bytes;
    }

    private function command(): PendingCommand {
        return $this->artisanCommand('geolocation:update-database');
    }

    private function disk(): string {
        return config()->string('matrix.file-private-disk');
    }

    public function test_a_download_that_is_not_an_archive_fails(): void {
        $this->useCfg('ip2location-bin', ['download-token' => 'a-token']);

        Http::fake(['www.ip2location.com/*' => Http::response('INVALID DOWNLOAD TOKEN')]);

        $this->command()->assertExitCode(1);

        Storage::disk($this->disk())->assertMissing(strval(cfg('ip2location-bin.bin-path')));
    }

    public function test_a_failed_request_fails(): void {
        $this->useCfg('ip2location-bin', ['download-token' => 'a-token']);

        Http::fake(['www.ip2location.com/*' => Http::response('nope', 503)]);

        $this->command()->assertExitCode(1);

        Storage::disk($this->disk())->assertMissing(strval(cfg('ip2location-bin.bin-path')));
    }

    public function test_an_archive_without_a_bin_file_fails(): void {
        $this->useCfg('ip2location-bin', ['download-token' => 'a-token']);

        Http::fake(['www.ip2location.com/*' => Http::response($this->archive(null, 'nothing useful here'))]);

        $this->command()->assertExitCode(1);

        Storage::disk($this->disk())->assertMissing(strval(cfg('ip2location-bin.bin-path')));
    }

    public function test_an_empty_download_token_fails(): void {
        $this->useCfg('ip2location-bin', ['download-token' => '']);

        Http::fake();

        $this->command()->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_the_bin_file_is_written_to_the_configured_path(): void {
        $this->useCfg('ip2location-bin', ['download-token' => 'a-token']);

        $contents = random_bytes(4096);

        Http::fake(['www.ip2location.com/*' => Http::response($this->archive('IP2LOCATION-LITE-DB11.BIN', $contents))]);

        $this->command()->assertExitCode(0);

        $path = strval(cfg('ip2location-bin.bin-path'));

        Storage::disk($this->disk())->assertExists($path);
        Storage::disk($this->disk())->assertMissing("{$path}.tmp");

        $this->assertSame($contents, Storage::disk($this->disk())->get($path));
    }

    public function test_the_configured_token_and_db_code_are_sent(): void {
        $this->useCfg('ip2location-bin', ['download-token' => 'a-token', 'db-code' => 'DB99BIN']);

        Http::fake(['www.ip2location.com/*' => Http::response($this->archive('DB99.BIN', 'payload'))]);

        $this->command()->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request['token'] === 'a-token' && $request['file'] === 'DB99BIN');
    }

}
