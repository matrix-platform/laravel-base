<?php //>

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase {

    public function test_tokenize_splits_by_space() {
        $this->assertEquals(['a', 'b', 'c'], tokenize('a b c'));
    }

    public function test_tokenize_splits_by_semicolon() {
        $this->assertEquals(['a', 'b', 'c'], tokenize('a;b;c'));
    }

    public function test_tokenize_splits_by_comma() {
        $this->assertEquals(['a', 'b', 'c'], tokenize('a,b,c'));
    }

    public function test_tokenize_splits_by_mixed_delimiters() {
        $this->assertEquals(['a', 'b', 'c', 'd'], tokenize('a b;c,d'));
    }

    public function test_tokenize_removes_empty_strings_from_consecutive_delimiters() {
        $this->assertEquals(['a', 'b'], tokenize('a  b'));
    }

    public function test_tokenize_returns_empty_array_for_empty_string() {
        $this->assertEquals([], tokenize(''));
    }

}
