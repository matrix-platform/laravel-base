<?php //>

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<array{int, string}>
 */
function check_arrow_functions(array $tokens): array {
    $violations = [];

    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FN || !array_key_exists($index + 1, $tokens)) {
            continue;
        }

        $next = $tokens[$index + 1];

        if (!is_array($next) || $next[0] !== T_WHITESPACE || $next[1] !== ' ') {
            $violations[] = [$token[2], 'fn must be followed by a single space, see manual 02.5 rule 3'];
        }
    }

    return $violations;
}

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
        $violations[] = [$opening + 2, 'class body must open with a blank line, see manual 02.5 rule 2'];
    }

    $closing = count($lines) - 1;

    if ($closing > $opening && in_array($lines[$closing], ['}', '};'], true) && trim($lines[$closing - 1]) !== '') {
        $violations[] = [$closing, 'class body must close with a blank line, see manual 02.5 rule 2'];
    }

    return $violations;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<array{int, string}>
 */
function check_event_bypass(array $tokens): array {
    $quiet = ['deleteQuietly', 'flushEventListeners', 'forceDeleteQuietly', 'pushQuietly', 'replicateQuietly', 'restoreQuietly', 'saveQuietly', 'touchQuietly', 'unsetEventDispatcher', 'updateQuietly', 'withoutEvents'];
    $writes = ['decrement', 'increment', 'insert', 'insertGetId', 'insertOrIgnore', 'insertUsing', 'truncate', 'update', 'updateOrInsert', 'upsert'];
    $sequence = significant_tokens($tokens);
    $aliases = database_aliases($tokens, $sequence);
    $violations = [];
    $total = count($sequence);

    foreach ($sequence as $slot => $index) {
        $token = $tokens[$index];

        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        if (in_array($token[1], ['delete', 'insert', 'update'], true) && database_facade($tokens, $sequence, $slot, $aliases)) {
            $violations[] = [$token[2], "DB::{$token[1]}() must not write, go through the model, see manual 02.5 rule 6"];

            continue;
        }

        if (in_array($token[1], $quiet, true)) {
            $violations[] = [$token[2], "{$token[1]}() skips model events, see manual 02.5 rule 6"];

            continue;
        }

        if ($token[1] === 'delete') {
            if (invoked_on_a_call($tokens, $sequence, $slot) && $tokens[$sequence[$slot + 1]] === '(' && $tokens[$sequence[$slot + 2]] === ')') {
                $violations[] = [$token[2], 'delete() must be called on a model instance, not on a query, see manual 02.5 rule 6'];
            }

            continue;
        }

        if ($token[1] !== 'table' || !database_facade($tokens, $sequence, $slot, $aliases)) {
            continue;
        }

        for ($cursor = $slot + 1; $cursor < $total && $tokens[$sequence[$cursor]] !== ';'; $cursor++) {
            $call = $tokens[$sequence[$cursor]];

            if (is_array($call) && $call[0] === T_STRING && in_array($call[1], $writes, true)) {
                $violations[] = [$call[2], "DB::table() must not write ({$call[1]}), go through the model, see manual 02.5 rule 6"];
            }
        }
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
                $violations[] = [$member['line'], "'{$member['name']}' breaks the member group order, see manual 02.5 rule 8"];
            } elseif ($member['rank'] === $previous['rank'] && strcmp($member['name'], $previous['name']) < 0) {
                $violations[] = [$member['line'], "'{$member['name']}' should come before '{$previous['name']}', see manual 02.5 rule 8"];
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
function check_method_chains(array $tokens): array {
    $calls = collect_calls($tokens);
    $ends = [];
    $violations = [];

    foreach ($calls as $call) {
        $ends[$call['close']] = true;
    }

    foreach ($calls as $slot => $call) {
        if (array_key_exists($slot - 1, $ends)) {
            continue;
        }

        $chain = [];
        $cursor = $slot;

        while (array_key_exists($cursor, $calls)) {
            $chain[] = $calls[$cursor];
            $cursor = $calls[$cursor]['close'] + 1;
        }

        if (count($chain) < 3 || !$call['anchored'] || !$chain[count($chain) - 1]['terminated']) {
            continue;
        }

        $previous = $call['boundary'];

        foreach ($chain as $link) {
            if ($link['line'] === $previous) {
                $violations[] = [$link['line'], 'a chain of more than two calls must break every -> onto its own line, see manual 02.5 rule 7'];
            }

            $previous = $link['line'];
        }
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
            $violations[] = [$token[2], 'do not use ?? or ??=, see manual 02.5 rule 4'];
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

    return [[1, 'file must start with <?php //>, see manual 02.5 rule 1']];
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<array{int, string}>
 */
function check_trailing_commas(array $tokens, bool $comma): array {
    $line = 1;
    $starts = [];
    $ends = [];

    foreach ($tokens as $index => $token) {
        $text = is_array($token) ? $token[1] : $token;
        $starts[$index] = $line;
        $line += substr_count($text, "\n");
        $ends[$index] = $line;
    }

    $sequence = significant_tokens($tokens);
    $violations = [];

    foreach ($sequence as $slot => $index) {
        if ($tokens[$index] !== ']' || $slot === 0) {
            continue;
        }

        $previous = $sequence[$slot - 1];

        if ($ends[$previous] === $starts[$index] || in_array($tokens[$previous], ['[', '('], true)) {
            continue;
        }

        if (($tokens[$previous] === ',') !== $comma) {
            $violations[] = [$starts[$index], $comma ? 'multiline array must end with a trailing comma, see manual 02.5 rule 5' : 'multiline array must not end with a trailing comma, see manual 02.5 rule 5'];
        }
    }

    return $violations;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return array<int, array{line: int, close: int, boundary: int, anchored: bool, terminated: bool}>
 */
function collect_calls(array $tokens): array {
    $line = 1;
    $lines = [];

    foreach ($tokens as $index => $token) {
        $text = is_array($token) ? $token[1] : $token;
        $lines[$index] = $line;
        $line += substr_count($text, "\n");
    }

    $sequence = significant_tokens($tokens);
    $calls = [];
    $total = count($sequence);

    foreach ($sequence as $slot => $index) {
        $token = $tokens[$index];

        if (!is_array($token) || !in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true) || $slot === 0 || $slot + 2 >= $total) {
            continue;
        }

        $name = $tokens[$sequence[$slot + 1]];

        if (!is_array($name) || $name[0] !== T_STRING || $tokens[$sequence[$slot + 2]] !== '(') {
            continue;
        }

        $depth = 0;

        for ($cursor = $slot + 2; $cursor < $total; $cursor++) {
            $current = $tokens[$sequence[$cursor]];

            if ($current === '(') {
                $depth++;
            } elseif ($current === ')') {
                $depth--;

                if ($depth === 0) {
                    $receiver = $tokens[$sequence[$slot - 1]];
                    $start = receiver_start($tokens, $sequence, $slot);

                    $calls[$slot] = [
                        'line' => $lines[$index],
                        'close' => $cursor,
                        'boundary' => is_array($receiver) && $receiver[0] === T_VARIABLE ? 0 : $lines[$sequence[$slot - 1]],
                        'anchored' => $start === 0 || statement_opener($tokens[$sequence[$start - 1]]),
                        'terminated' => $cursor + 1 < $total && $tokens[$sequence[$cursor + 1]] === ';'
                    ];

                    break;
                }
            }
        }
    }

    return $calls;
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
    $signature = false;

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        if (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $depth++;

            continue;
        }

        if (!is_array($token)) {
            if ($token === '(') {
                $paren++;
            } elseif ($token === ')') {
                $paren--;
            } elseif ($token === '{') {
                $signature = false;
                $depth++;
            } elseif ($token === '}') {
                if ($depth === $bodyDepth) {
                    $bodyDepth = null;
                }

                $depth--;
            } elseif ($token === ';' && $depth === $bodyDepth) {
                $modifiers = [];
                $signature = false;
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
                $members[] = ['name' => $token[1], 'rank' => $awaiting, 'line' => $token[2]];
                $awaiting = null;
                $modifiers = [];
                $signature = true;
            } elseif ($paren === 0 && !$signature && in_array($token[0], [T_ABSTRACT, T_FINAL, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC], true)) {
                $modifiers[] = $token[0];
            } elseif ($token[0] === T_CONST) {
                $awaiting = 0;
            } elseif ($token[0] === T_FUNCTION) {
                $awaiting = match (true) {
                    in_array(T_STATIC, $modifiers, true) => 1,
                    in_array(T_PRIVATE, $modifiers, true) => 7,
                    in_array(T_PROTECTED, $modifiers, true) => 6,
                    default => 5
                };
            } elseif ($token[0] === T_VARIABLE && $paren === 0 && $modifiers !== []) {
                $rank = match (true) {
                    in_array(T_STATIC, $modifiers, true) => 1,
                    in_array(T_PRIVATE, $modifiers, true) => 4,
                    in_array(T_PROTECTED, $modifiers, true) => 3,
                    default => 2
                };

                $members[] = ['name' => substr($token[1], 1), 'rank' => $rank, 'line' => $token[2]];
            }
        }

        $afterNew = $token[0] === T_NEW;
    }

    return $members;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @param list<int> $sequence
 * @return list<string>
 */
function database_aliases(array $tokens, array $sequence): array {
    $aliases = [];
    $total = count($sequence);

    foreach ($sequence as $slot => $index) {
        $token = $tokens[$index];

        if (!is_array($token) || $token[0] !== T_USE || $slot + 3 >= $total) {
            continue;
        }

        [$name, $as, $alias] = [$tokens[$sequence[$slot + 1]], $tokens[$sequence[$slot + 2]], $tokens[$sequence[$slot + 3]]];

        if (is_array($name) && in_array($name[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && ltrim($name[1], '\\') === 'Illuminate\\Support\\Facades\\DB'
            && is_array($as) && $as[0] === T_AS && is_array($alias) && $alias[0] === T_STRING) {
            $aliases[] = $alias[1];
        }
    }

    return $aliases;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @param list<int> $sequence
 * @param list<string> $aliases
 */
function database_facade(array $tokens, array $sequence, int $slot, array $aliases): bool {
    $facade = receiver_before($tokens, $sequence, $slot, [T_DOUBLE_COLON]);

    return is_array($facade) && ($facade[1] === 'DB' || str_ends_with($facade[1], '\\DB') || in_array($facade[1], $aliases, true));
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @param list<int> $sequence
 */
function invoked_on_a_call(array $tokens, array $sequence, int $slot): bool {
    if ($slot + 2 >= count($sequence)) {
        return false;
    }

    return receiver_before($tokens, $sequence, $slot, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR]) === ')';
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @param list<int> $sequence
 * @param list<int> $operators
 * @return array{int, string, int}|string|null
 */
function receiver_before(array $tokens, array $sequence, int $slot, array $operators): array|string|null {
    if ($slot < 2) {
        return null;
    }

    $operator = $tokens[$sequence[$slot - 1]];

    return is_array($operator) && in_array($operator[0], $operators, true) ? $tokens[$sequence[$slot - 2]] : null;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @param list<int> $sequence
 * @param int<0, max> $slot
 * @return int<0, max>
 */
function receiver_start(array $tokens, array $sequence, int $slot): int {
    $cursor = $slot;

    while ($cursor > 0) {
        $token = $tokens[$sequence[$cursor - 1]];

        if ($token === ')' || $token === ']') {
            $open = $token === ')' ? '(' : '[';
            $depth = 0;
            $group = null;

            for ($scan = $cursor - 1; $scan >= 0; $scan--) {
                $current = $tokens[$sequence[$scan]];

                if ($current === $token) {
                    $depth++;
                } elseif ($current === $open) {
                    $depth--;

                    if ($depth === 0) {
                        $group = $scan;

                        break;
                    }
                }
            }

            if ($group === null) {
                break;
            }

            if ($group === 0) {
                return 0;
            }

            $before = $tokens[$sequence[$group - 1]];

            if (!is_array($before) || !in_array($before[0], [T_STRING, T_VARIABLE], true)) {
                return $group;
            }

            $cursor = $group;

            continue;
        }

        if (!is_array($token) || !in_array($token[0], [T_DOUBLE_COLON, T_NS_SEPARATOR, T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR, T_STATIC, T_STRING, T_VARIABLE], true)) {
            break;
        }

        $cursor--;
    }

    return $cursor;
}

/**
 * @param list<array{int, string, int}|string> $tokens
 * @return list<int>
 */
function significant_tokens(array $tokens): array {
    $sequence = [];

    foreach ($tokens as $index => $token) {
        if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $sequence[] = $index;
        }
    }

    return $sequence;
}

/**
 * @param array{int, string, int}|string $token
 */
function statement_opener(array|string $token): bool {
    if (is_array($token)) {
        return in_array($token[0], [T_OPEN_TAG, T_RETURN], true);
    }

    return in_array($token, ['=', ';', '{', '}'], true);
}

$root = rtrim(isset($argv[1]) ? $argv[1] : (string) getcwd(), '/');
$targets = ['app', 'bin', 'config', 'database', 'resources', 'routes', 'src', 'tests'];
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
            check_arrow_functions($tokens),
            check_null_coalescing($tokens),
            check_trailing_commas($tokens, $comma),
            str_starts_with($name, 'bin/') || str_starts_with($name, 'config/') || str_starts_with($name, 'resources/') ? [] : check_event_bypass($tokens),
            str_starts_with($name, 'app/') || str_starts_with($name, 'src/') || str_starts_with($name, 'tests/') ? check_method_chains($tokens) : [],
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
