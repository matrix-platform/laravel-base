<?php //>

namespace MatrixPlatform\Support;

class Template {

    /**
     * @param array<string, string> $vars
     * @param list<string> $escapedFields
     * @param list<string> $rawVars
     * @return array<string, mixed>
     */
    public static function render(string $name, array $vars = [], ?string $locale = null, array $escapedFields = [], array $rawVars = []): array {
        $template = self::resolve($name, $locale);

        if ($template === null) {
            error('message-template-not-found');
        }

        $fields = [];

        foreach ($template as $field => $value) {
            $fields[$field] = is_string($value) ? self::substitute($value, $vars, in_array($field, $escapedFields, true), $rawVars) : $value;
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
     * @param list<string> $rawVars
     */
    private static function substitute(string $text, array $vars, bool $escape, array $rawVars): string {
        return strval(preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($vars, $escape, $rawVars): string {
            if (!array_key_exists($matches[1], $vars)) {
                return $matches[0];
            }

            $value = $vars[$matches[1]];

            return !$escape || in_array($matches[1], $rawVars, true) ? $value : e($value);
        }, $text));
    }

}
