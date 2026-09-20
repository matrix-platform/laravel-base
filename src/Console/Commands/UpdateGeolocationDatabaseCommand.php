<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Services\FileStorage;
use ZipArchive;

class UpdateGeolocationDatabaseCommand extends Command {

    private const ENDPOINT = 'https://www.ip2location.com/download';

    /**
     * The number of leading bytes kept for the archive signature check and the failure message.
     */
    private const HEAD_LENGTH = 200;

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

        $zipPath = tempnam(sys_get_temp_dir(), 'ip2location');

        if ($zipPath === false) {
            $this->error('Failed to create a temporary file for the download');

            return self::FAILURE;
        }

        $updated = $this->download($token, $zipPath) && $this->extract($zipPath);

        unlink($zipPath);

        if (!$updated) {
            return self::FAILURE;
        }

        $this->info('Geolocation database updated');

        return self::SUCCESS;
    }

    private function download(string $token, string $path): bool {
        $response = Http::sink($path)->get(self::ENDPOINT, ['token' => $token, 'file' => strval(cfg('ip2location-bin.db-code'))]);
        $head = strval(file_get_contents($path, false, null, 0, self::HEAD_LENGTH));

        if ($response->failed() || !str_starts_with($head, self::ZIP_SIGNATURE)) {
            $this->error('The download did not return a valid archive: ' . trim($head));

            return false;
        }

        return true;
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

    private function extract(string $path): bool {
        $archive = new ZipArchive();

        if ($archive->open($path) !== true) {
            $this->error('The downloaded file is not a valid zip archive');

            return false;
        }

        $name = $this->entry($archive);

        if ($name === null) {
            $archive->close();

            $this->error('No .BIN file was found inside the downloaded archive');

            return false;
        }

        $stream = $archive->getStream($name);

        if ($stream === false) {
            $archive->close();

            $this->error('Failed to extract the .BIN file from the downloaded archive');

            return false;
        }

        $this->replace($stream);

        fclose($stream);
        $archive->close();

        return true;
    }

    /**
     * @param resource $stream
     */
    private function replace($stream): void {
        $disk = app(FileStorage::class)->requireLocal(config()->string('matrix.file-private-disk'));
        $path = strval(cfg('ip2location-bin.bin-path'));
        $temporary = "{$path}.tmp";

        Storage::disk($disk)->writeStream($temporary, $stream);
        Storage::disk($disk)->move($temporary, $path);
    }

}
