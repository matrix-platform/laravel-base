<?php //>

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/bin', __DIR__ . '/config', __DIR__ . '/resources', __DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php')
    ->notPath('#(^|/)menu/[^/]+\.php$#');

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setRiskyAllowed(false)
    ->setRules([
        'array_indentation' => true,
        'blank_line_after_namespace' => true,
        'braces_position' => [
            'anonymous_classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line'
        ],
        'elseif' => true,
        'indentation_type' => true,
        'no_closing_tag' => true,
        'no_trailing_whitespace' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_blank_line_at_eof' => true,
        'single_line_empty_body' => true,
        'single_quote' => true
    ]);
