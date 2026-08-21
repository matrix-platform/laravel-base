<?php //>

const LINE_FLOOR = 98.0;
const METHOD_FLOOR = 95.0;

function metrics(string $report): DOMElement {
    $document = new DOMDocument();

    if (!is_file($report) || !$document->load($report)) {
        echo "coverage-check: cannot read {$report}", PHP_EOL;

        exit(1);
    }

    $nodes = (new DOMXPath($document))->query('/coverage/project/metrics');
    $element = $nodes === false ? null : $nodes->item(0);

    if (!$element instanceof DOMElement) {
        echo "coverage-check: {$report} carries no project metrics", PHP_EOL;

        exit(1);
    }

    return $element;
}

function within(string $metric, int $covered, int $total, float $floor): bool {
    $actual = $total === 0 ? 100.0 : 100.0 * $covered / $total;
    $passed = round($actual, 2) >= $floor;

    echo sprintf('coverage-check: %-7s %6.2f%% (%d/%d) floor %.2f%% %s', $metric, $actual, $covered, $total, $floor, $passed ? 'ok' : 'below'), PHP_EOL;

    return $passed;
}

$element = metrics(dirname(__DIR__) . '/build/coverage/clover.xml');

$results = [
    within('lines', intval($element->getAttribute('coveredstatements')), intval($element->getAttribute('statements')), LINE_FLOOR),
    within('methods', intval($element->getAttribute('coveredmethods')), intval($element->getAttribute('methods')), METHOD_FLOOR)
];

$passed = !in_array(false, $results, true);

echo $passed ? 'coverage-check: passed' : 'coverage-check: below the floor', PHP_EOL;

exit($passed ? 0 : 1);
