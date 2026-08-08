<?php //>

/**
 * @param list<string> $lines
 * @return list<array{int, string}>
 */
function check_class_blank_lines(array $lines): array {
    $opening = null;

    foreach ($lines as $index => $line) {
        if (str_ends_with($line, '{') && $line[0] !== ' ' && preg_match('/\b(class|enum|interface|trait)\b/', $line)) {
            $opening = $index;

            break;
        }
    }

    if ($opening === null) {
        return [];
    }

    $violations = [];

    if (array_key_exists($opening + 1, $lines) && trim($lines[$opening + 1]) !== '') {
        $violations[] = [$opening + 2, 'class body must open with a blank line'];
    }

    $closing = count($lines) - 1;

    if ($closing > $opening && in_array($lines[$closing], ['}', '};'], true) && trim($lines[$closing - 1]) !== '') {
        $violations[] = [$closing, 'class body must close with a blank line'];
    }

    return $violations;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<array{int, string}>
 */
function check_member_order(array $tokens): array {
    $violations = [];
    $previous = null;

    foreach (collect_members($tokens) as $member) {
        if ($previous !== null) {
            if ($member['rank'] < $previous['rank']) {
                $violations[] = [$member['line'], "'{$member['name']}' breaks the member group order, see CLAUDE.md 7.5"];
            } elseif ($member['rank'] === $previous['rank'] && strcmp($member['name'], $previous['name']) < 0) {
                $violations[] = [$member['line'], "'{$member['name']}' should come before '{$previous['name']}', see CLAUDE.md 7.5"];
            }
        }

        $previous = $member;
    }

    return $violations;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<array{int, string}>
 */
function check_null_coalescing(array $tokens): array {
    $violations = [];

    foreach ($tokens as $token) {
        if (is_array($token) && ($token[0] === T_COALESCE || $token[0] === T_COALESCE_EQUAL)) {
            $violations[] = [$token[2], 'do not use ?? or ??=, see CLAUDE.md 7.7'];
        }
    }

    return $violations;
}

/**
 * @param list<string> $lines
 * @return list<array{int, string}>
 */
function check_opening_tag(array $lines): array {
    if (array_key_exists(0, $lines) && $lines[0] === '<?php //>') {
        return [];
    }

    return [[1, 'file must start with <?php //>']];
}

/**
 * @param list<string> $lines
 * @return list<array{int, string}>
 */
function check_trailing_commas(array $lines, bool $comma): array {
    $violations = [];

    foreach ($lines as $index => $line) {
        if (!str_starts_with(trim($line), ']')) {
            continue;
        }

        $previous = previous_content($lines, $index);

        if ($previous === null || str_ends_with($previous, '[') || str_ends_with($previous, '(')) {
            continue;
        }

        if (str_ends_with($previous, ',') !== $comma) {
            $violations[] = [$index + 1, $comma ? 'multiline array must end with a trailing comma' : 'multiline array must not end with a trailing comma'];
        }
    }

    return $violations;
}

/**
 * @return string[]
 */
function collect_files(string $path): array {
    if (!is_dir($path)) {
        return [];
    }

    $files = [];
    $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

    foreach (new RecursiveIteratorIterator($directory) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<array{name: string, rank: int, line: int}>
 */
function collect_members(array $tokens): array {
    $afterNew = false;
    $awaiting = null;
    $bodyDepth = null;
    $depth = 0;
    $members = [];
    $modifiers = [];
    $paren = 0;

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        if (!is_array($token)) {
            if ($token === '(') {
                $paren++;
            } elseif ($token === ')') {
                $paren--;
            } elseif ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                if ($depth === $bodyDepth) {
                    $bodyDepth = null;
                }

                $depth--;
            } elseif ($token === ';' && $depth === $bodyDepth) {
                $modifiers = [];
            }

            $afterNew = false;

            continue;
        }

        if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            if ($bodyDepth === null && !$afterNew) {
                $bodyDepth = $depth + 1;
            }
        } elseif ($depth === $bodyDepth) {
            if ($awaiting !== null) {
                if ($token[0] === T_STRING) {
                    $members[] = ['name' => $token[1], 'rank' => $awaiting, 'line' => $token[2]];
                    $awaiting = null;
                    $modifiers = [];
                }
            } elseif ($paren === 0 && in_array($token[0], [T_ABSTRACT, T_FINAL, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC], true)) {
                $modifiers[] = $token[0];
            } elseif ($token[0] === T_CONST) {
                $awaiting = 0;
            } elseif ($token[0] === T_FUNCTION) {
                $awaiting = match (true) {
                    in_array(T_STATIC, $modifiers, true) => 1,
                    in_array(T_PRIVATE, $modifiers, true) => 5,
                    in_array(T_PROTECTED, $modifiers, true) => 4,
                    default => 3
                };
            } elseif ($token[0] === T_VARIABLE && $paren === 0 && $modifiers !== []) {
                $members[] = ['name' => substr($token[1], 1), 'rank' => 2, 'line' => $token[2]];
            }
        }

        $afterNew = $token[0] === T_NEW;
    }

    return $members;
}

/**
 * @param list<string> $lines
 */
function previous_content(array $lines, int $index): ?string {
    for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
        $line = trim($lines[$cursor]);

        if ($line !== '') {
            return $line;
        }
    }

    return null;
}

$root = dirname(__DIR__);
$targets = ['bin', 'config', 'database', 'resources', 'routes', 'src', 'tests'];
$violations = [];

foreach ($targets as $target) {
    foreach (collect_files("{$root}/{$target}") as $file) {
        $source = (string) file_get_contents($file);
        $tokens = token_get_all($source);
        $lines = explode("\n", rtrim($source, "\n"));
        $name = substr($file, strlen($root) + 1);
        $comma = preg_match('#(^|/)(config|resources)/#', $name) === 1;

        $checks = array_merge(
            check_opening_tag($lines),
            check_class_blank_lines($lines),
            check_null_coalescing($tokens),
            check_trailing_commas($lines, $comma),
            str_starts_with($name, 'tests/') ? [] : check_member_order($tokens)
        );

        foreach ($checks as [$line, $message]) {
            $violations[] = "{$name}:{$line}  {$message}";
        }
    }
}

foreach ($violations as $violation) {
    echo $violation, PHP_EOL;
}

echo $violations === [] ? 'style-check: passed' : 'style-check: ' . count($violations) . ' violation(s)', PHP_EOL;

exit($violations === [] ? 0 : 1);
