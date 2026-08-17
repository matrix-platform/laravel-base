<?php //>

namespace MatrixPlatform\Support;

class Template {

    /**
     * @param array<string, string> $vars
     * @return array<string, mixed>
     */
    public static function render(string $channel, string $name, array $vars = [], ?string $locale = null): array {
        $template = self::resolve($channel, $name, $locale);

        if ($template === null) {
            error('message-template-not-found');
        }

        return array_map(fn (mixed $value): mixed => is_string($value) ? self::substitute($value, $vars) : $value, $template);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolve(string $channel, string $name, ?string $locale = null): ?array {
        return app(Resources::class)->getI18nBundle("template/{$channel}/{$name}", $locale);
    }

    /**
     * @param array<string, string> $vars
     */
    private static function substitute(string $text, array $vars): string {
        return strval(preg_replace_callback('/\{(\w+)\}/', fn (array $matches): string => array_get_value($vars, $matches[1], $matches[0]), $text));
    }

}
