<?php //>

namespace Tests\Unit\Support\Scaffold;

use MatrixPlatform\Support\Scaffold\StubRenderer;
use PHPUnit\Framework\TestCase;

class StubRendererTest extends TestCase {

    public function test_every_occurrence_of_a_token_placeholder_is_replaced(): void {
        $stub = tempnam(sys_get_temp_dir(), 'stub');
        file_put_contents($stub, "<?php //>\n\nnamespace {{ namespace }};\n\nuse {{ namespace }}\\Base;\n\n{{ unknown }}\n");

        $content = (new StubRenderer())->render($stub, ['namespace' => 'App\\Models']);

        unlink($stub);

        $this->assertSame("<?php //>\n\nnamespace App\\Models;\n\nuse App\\Models\\Base;\n\n{{ unknown }}\n", $content);
    }

}
