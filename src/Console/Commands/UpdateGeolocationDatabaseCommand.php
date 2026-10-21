<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use MatrixPlatform\Models\File;
use MatrixPlatform\Services\FileService;
use ZipArchive;

class UpdateGeolocationDatabaseCommand extends Command {

    private const CONNECT_TIMEOUT = 10;

    private const ENDPOINT = 'https://www.ip2location.com/download';

    /**
     * The number of leading bytes kept for the archive signature check and the failure message.
     */
    private const HEAD_LENGTH = 200;

    private const TIMEOUT = 600;

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

        try {
            $updated = $this->download($token, $zipPath) && $this->extract($zipPath);
        } finally {
            unlink($zipPath);
        }

        if (!$updated) {
            return self::FAILURE;
        }

        $this->info('Geolocation database updated');

        return self::SUCCESS;
    }

    private function download(string $token, string $path): bool {
        try {
            $response = Http::sink($path)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->get(self::ENDPOINT, ['token' => $token, 'file' => strval(cfg('ip2location-bin.db-code'))]);
        } catch (ConnectionException) {
            $this->error('Could not reach the IP2Location download server');

            return false;
        }

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

        try {
            $name = $this->entry($archive);

            if ($name === null) {
                $this->error('No .BIN file was found inside the downloaded archive');

                return false;
            }

            $stream = $archive->getStream($name);

            if ($stream === false) {
                $this->error('Failed to extract the .BIN file from the downloaded archive');

                return false;
            }

            $replaced = $this->replace($stream);

            fclose($stream);

            return $replaced;
        } finally {
            $archive->close();
        }
    }

    /**
     * @param resource $stream
     */
    private function replace($stream): bool {
        $storage = Storage::disk(app(FileService::class)->disk(File::PRIVATE));
        $path = strval(cfg('ip2location-bin.bin-path'));
        $temporary = "{$path}.tmp";

        if (!$storage->writeStream($temporary, $stream)) {
            $storage->delete($temporary);
            $this->error('Failed to write the .BIN file to the disk');

            return false;
        }

        if (!$storage->move($temporary, $path)) {
            $storage->delete($temporary);
            $this->error('Failed to replace the geolocation database with the new .BIN file');

            return false;
        }

        return true;
    }

}
