<?php //>

namespace MatrixPlatform\Translation;

use Illuminate\Support\Facades\Log;
use MatrixPlatform\Exceptions\ServiceException;

class TranslationService {

    private const PLACEHOLDER_PATTERN = '/<[^>]*>|\{\{\w+\}\}|\{\w+\}/';

    public function translate(string $text, string $sourceLocale, string $targetLocale): ?string {
        if (!in_array($sourceLocale, locales(), true) || !in_array($targetLocale, locales(), true) || $sourceLocale === $targetLocale) {
            error('invalid-locale');
        }

        $bundle = config()->string('matrix.translation-provider');
        $driver = resolve_driver($bundle, Driver::class, 'invalid-translation-driver');

        if ($driver === null) {
            return null;
        }

        $placeholders = [];
        $protected = $this->protect($text, $placeholders);

        try {
            $translated = $driver->translate($protected, $sourceLocale, $targetLocale);
        } catch (ServiceException $exception) {
            $this->record('failed', ['source' => $sourceLocale, 'target' => $targetLocale, 'length' => strlen($text), 'error' => $exception->getError()]);

            throw $exception;
        }

        $restored = [];
        $translation = $this->restore($translated, $placeholders, $restored);

        if (count($restored) !== count($placeholders)) {
            $this->record('failed', ['source' => $sourceLocale, 'target' => $targetLocale, 'length' => strlen($text), 'error' => 'placeholder-lost']);

            return null;
        }

        $this->record('translated', ['source' => $sourceLocale, 'target' => $targetLocale, 'length' => strlen($text)]);

        return $translation;
    }

    /**
     * @param array<string, string> $placeholders
     */
    private function protect(string $text, array &$placeholders): string {
        return strval(preg_replace_callback(self::PLACEHOLDER_PATTERN, function (array $matches) use (&$placeholders): string {
            if ($matches[0][0] === '<') {
                return $matches[0];
            }

            $key = 'placeholder-' . count($placeholders);
            $placeholders[$key] = $matches[0];

            return "<span data-token=\"{$key}\"></span>";
        }, $text));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(string $action, array $context): void {
        Log::info("translation.{$action}", array_merge(['action' => $action], $context));
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, true> $restored
     */
    private function restore(string $text, array $placeholders, array &$restored): string {
        return strval(preg_replace_callback('/<span\b[^>]*\bdata-token\s*=\s*["\']?([\w-]+)["\']?[^>]*>\s*<\/span>/i', function (array $matches) use ($placeholders, &$restored): string {
            $value = array_get_value($placeholders, $matches[1]);

            if ($value === null) {
                return $matches[0];
            }

            $restored[$matches[1]] = true;

            return strval($value);
        }, $text));
    }

}
