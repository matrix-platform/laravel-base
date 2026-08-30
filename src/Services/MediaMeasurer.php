<?php //>

namespace MatrixPlatform\Services;

use getID3;

class MediaMeasurer {

    private const EMPTY = ['width' => null, 'height' => null, 'seconds' => null];

    /**
     * @return array{width: ?int, height: ?int, seconds: ?int}
     */
    public function measure(?string $mimeType, string $path): array {
        return match (strtok(strval($mimeType), '/')) {
            'image' => $this->measureImage($path),
            'audio', 'video' => $this->measureMedia($path),
            default => self::EMPTY
        };
    }

    /**
     * @return array{width: ?int, height: ?int, seconds: ?int}
     */
    private function measureImage(string $path): array {
        $info = getimagesize($path);

        if ($info === false) {
            return self::EMPTY;
        }

        return ['width' => $info[0], 'height' => $info[1], 'seconds' => null];
    }

    /**
     * @return array{width: ?int, height: ?int, seconds: ?int}
     */
    private function measureMedia(string $path): array {
        $id3 = new getID3();

        $id3->option_tags_process = false;

        $info = $id3->analyze($path);
        $width = data_get($info, 'video.resolution_x');
        $height = data_get($info, 'video.resolution_y');
        $seconds = array_get_value($info, 'playtime_seconds');
        $resolved = is_numeric($width) && is_numeric($height);

        return [
            'width' => $resolved ? intval($width) : null,
            'height' => $resolved ? intval($height) : null,
            'seconds' => is_numeric($seconds) ? intval($seconds) : null
        ];
    }

}
