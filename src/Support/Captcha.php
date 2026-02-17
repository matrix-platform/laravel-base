<?php //>

namespace MatrixPlatform\Support;

class Captcha {

    public static function generate($code) {
        $colors = [
            [122, 229, 112],
            [85, 178, 85],
            [226, 108, 97],
            [141, 214, 210],
            [214, 141, 205],
            [100, 138, 204]
        ];

        $image = imagecreatefrompng(__DIR__ . '/../../resources/captcha/noise.png');

        foreach (str_split($code) as $index => $letter) {
            $color = $colors[rand(0, count($colors) - 1)];
            $textColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            $font = __DIR__ . '/../../resources/captcha/' . rand(1, 10) . '.ttf';

            imagettftext($image, 20, rand(-15, 15),  10 + ($index * 28), 35, $textColor, $font, $letter);
        }

        ob_start();

        imagepng($image);

        $data = base64_encode(ob_get_clean());

        imagedestroy($image);

        return "data:image/png;base64,{$data}";
    }

}
