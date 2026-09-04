<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class UpdateGeolocationDatabaseCommand extends Command {

    private const ENDPOINT = 'https://www.ip2location.com/download';

    /**
     * The local file header signature every non-empty zip archive starts with.
     */
    private const ZIP_SIGNATURE = "PK\x03\x04";

    protected $description = 'Download the latest IP2Location .BIN database file';

    protected $signature = 'geolocation:update-database';

    public function handle(): int {
        $token = strval(cfg('ip2location-bin.download-token'));

        if ($token === '') {
            $this->error('The ip2location-bin.download-token setting is empty');

            return self::FAILURE;
        }

        $body = $this->download($token);

        if ($body === null) {
            return self::FAILURE;
        }

        $extracted = $this->extract($body);

        if ($extracted === null) {
            return self::FAILURE;
        }

        $this->replace($extracted);

        $this->info('Geolocation database updated');

        return self::SUCCESS;
    }

    private function download(string $token): ?string {
        $response = Http::get(self::ENDPOINT, ['token' => $token, 'file' => strval(cfg('ip2location-bin.db-code'))]);
        $body = $response->body();

        if ($response->failed() || !str_starts_with($body, self::ZIP_SIGNATURE)) {
            $this->error('The download did not return a valid archive: ' . trim(substr($body, 0, 200)));

            return null;
        }

        return $body;
    }

    private function entry(ZipArchive $archive): ?string {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);

            if ($name !== false && str_ends_with(strtoupper($name), '.BIN')) {
                return $name;
            }
        }

        return null;
    }

    private function extract(string $body): ?string {
        $zipPath = tempnam(sys_get_temp_dir(), 'ip2location');

        file_put_contents($zipPath, $body);

        $archive = new ZipArchive();

        if ($archive->open($zipPath) !== true) {
            unlink($zipPath);

            $this->error('The downloaded file is not a valid zip archive');

            return null;
        }

        $name = $this->entry($archive);
        $extracted = $name === null ? false : $archive->getFromName($name);

        $archive->close();
        unlink($zipPath);

        if ($name === null) {
            $this->error('No .BIN file was found inside the downloaded archive');

            return null;
        }

        if ($extracted === false) {
            $this->error('Failed to extract the .BIN file from the downloaded archive');

            return null;
        }

        return $extracted;
    }

    private function replace(string $contents): void {
        $disk = config()->string('matrix.file-private-disk');
        $path = strval(cfg('ip2location-bin.bin-path'));
        $temporary = "{$path}.tmp";

        Storage::disk($disk)->put($temporary, $contents);
        Storage::disk($disk)->move($temporary, $path);
    }

}
