<?php //>

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ErrorSlugTest extends TestCase {

    public function test_every_error_slug_used_in_src_is_declared_in_every_locale(): void {
        $declared = $this->declared();

        $this->assertNotEmpty($declared);

        foreach ($this->used() as $file => $slugs) {
            foreach ($slugs as $slug) {
                foreach ($declared as $locale => $messages) {
                    $this->assertArrayHasKey($slug, $messages, "undeclared error slug '{$slug}' in {$file} for locale '{$locale}'");
                }
            }
        }
    }

    public function test_the_scanner_actually_finds_slugs(): void {
        $this->assertContains('invalid-resource-token', array_merge(...array_values($this->used())));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function declared(): array {
        $declared = [];

        foreach (glob(__DIR__ . '/../../resources/i18n/*/errors.php') ?: [] as $file) {
            $messages = require $file;

            $this->assertIsArray($messages, "{$file} must return an array");

            $declared[basename(dirname($file))] = $messages;
        }

        return $declared;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function invoked(array $tokens, int $index): bool {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) && in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
        }

        return false;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function literalArgument(array $tokens, int $index): ?string {
        $open = array_get_value($tokens, $index + 1);
        $argument = array_get_value($tokens, $index + 2);

        if ($open !== '(' || !is_array($argument) || $argument[0] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return trim($argument[1], "'\"");
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function lastLiteralArgument(array $tokens, int $index): ?string {
        if (array_get_value($tokens, $index + 1) !== '(') {
            return null;
        }

        $depth = 0;

        for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
            if ($tokens[$cursor] === '(') {
                $depth++;
            } elseif ($tokens[$cursor] === ')' && --$depth === 0) {
                for ($back = $cursor - 1; $back >= 0; $back--) {
                    $previous = $tokens[$back];

                    if (is_array($previous) && in_array($previous[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }

                    return is_array($previous) && $previous[0] === T_CONSTANT_ENCAPSED_STRING ? trim($previous[1], "'\"") : null;
                }

                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function used(): array {
        $found = [];
        $root = realpath(__DIR__ . '/../../src');

        $this->assertIsString($root);

        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);

        foreach (new RecursiveIteratorIterator($directory) as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $tokens = token_get_all((string) file_get_contents($file->getPathname()));
            $count = count($tokens);

            for ($index = 0; $index < $count; $index++) {
                $token = $tokens[$index];

                if (!is_array($token) || $token[0] !== T_STRING || $this->invoked($tokens, $index)) {
                    continue;
                }

                $slug = match ($token[1]) {
                    'error' => $this->literalArgument($tokens, $index),
                    'resolve_driver' => $this->lastLiteralArgument($tokens, $index),
                    default => null,
                };

                if ($slug !== null) {
                    $found[substr($file->getPathname(), strlen($root) + 1)][] = $slug;
                }
            }
        }

        return $found;
    }

}
