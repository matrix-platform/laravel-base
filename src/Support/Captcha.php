<?php //>

namespace MatrixPlatform\Support;

class Captcha {

    /**
     * @var list<array{int, int, int}>
     */
    private const COLORS = [
        [122, 229, 112],
        [85, 178, 85],
        [226, 108, 97],
        [141, 214, 210],
        [214, 141, 205],
        [100, 138, 204]
    ];

    public static function generate(string $code): string {
        $image = imagecreatefrompng(__DIR__ . '/../../resources/captcha/noise.png');

        if ($image === false) {
            error('server-error');
        }

        foreach (str_split($code) as $index => $letter) {
            $color = self::COLORS[rand(0, count(self::COLORS) - 1)];
            $allocated = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            $font = __DIR__ . '/../../resources/captcha/' . rand(1, 10) . '.ttf';

            if ($allocated !== false) {
                imagettftext($image, 20, rand(-15, 15), 10 + ($index * 28), 35, $allocated, $font, $letter);
            }
        }

        ob_start();

        imagepng($image);

        $data = base64_encode((string) ob_get_clean());

        imagedestroy($image);

        return "data:image/png;base64,{$data}";
    }

}
