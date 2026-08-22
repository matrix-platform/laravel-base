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
    ->setRules(require __DIR__ . '/.php-cs-fixer.rules.php');
