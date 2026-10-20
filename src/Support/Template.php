<?php //>

namespace MatrixPlatform\Support;

class Template {

    /**
     * @param array<string, string> $vars
     * @param list<string> $escaped fields whose variable values are HTML-escaped
     * @param list<string> $raw variables that are never escaped
     * @return array<string, mixed>
     */
    public static function render(string $name, array $vars = [], ?string $locale = null, array $escaped = [], array $raw = []): array {
        $template = self::resolve($name, $locale);

        if ($template === null) {
            error('message-template-not-found');
        }

        $fields = [];

        foreach ($template as $field => $value) {
            $fields[$field] = is_string($value) ? self::substitute($value, $vars, in_array($field, $escaped, true) ? $raw : null) : $value;
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolve(string $name, ?string $locale = null): ?array {
        return app(Resources::class)->getI18nBundle("template/{$name}", $locale);
    }

    /**
     * @param array<string, string> $vars
     * @param list<string>|null $raw null leaves every variable unescaped
     */
    private static function substitute(string $text, array $vars, ?array $raw): string {
        return strval(preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($vars, $raw): string {
            if (!array_key_exists($matches[1], $vars)) {
                return $matches[0];
            }

            $value = $vars[$matches[1]];

            return $raw === null || in_array($matches[1], $raw, true) ? $value : e($value);
        }, $text));
    }

}
