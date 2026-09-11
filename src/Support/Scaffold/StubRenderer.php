<?php //>

namespace MatrixPlatform\Support\Scaffold;

use RuntimeException;

class StubRenderer {

    /**
     * @param array<string, string> $tokens
     */
    public function render(string $stubPath, array $tokens): string {
        $content = file_get_contents($stubPath);

        if ($content === false) {
            throw new RuntimeException("Unable to read stub file: {$stubPath}");
        }

        foreach ($tokens as $token => $value) {
            $content = str_replace("{{ {$token} }}", $value, $content);
        }

        return $content;
    }

}
