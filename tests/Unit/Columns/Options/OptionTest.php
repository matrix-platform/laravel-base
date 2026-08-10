<?php //>

namespace Tests\Unit\Columns\Options;

use MatrixPlatform\Columns\Options\Option;
use MatrixPlatform\Columns\Options\StaticOptions;
use PHPUnit\Framework\TestCase;

class OptionTest extends TestCase {

    public function test_children_are_always_an_array(): void {
        $this->assertSame([], (new Option([], 1, 0, 'Root'))->children);
    }

    public function test_a_tree_keeps_its_depth(): void {
        $leaf = new Option([], 3, 0, 'Leaf');
        $branch = new Option([$leaf], 2, 0, 'Branch');
        $root = new Option([$branch], 1, 0, 'Root');

        $this->assertSame('Leaf', $root->children[0]->children[0]->title);
    }

    public function test_static_options_are_returned_as_given(): void {
        $options = [new Option([], 'a', 0, 'A')];

        $this->assertSame($options, (new StaticOptions($options))->options());
    }

}
