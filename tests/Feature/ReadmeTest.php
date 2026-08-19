<?php //>

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Support\PackageRegistry;
use Tests\FeatureTestCase;

class ReadmeTest extends FeatureTestCase {

    private const CHAPTERS = [
        '## 套件不提供什麼',
        '## 安裝',
        '## 五個必讀概念',
        '## 開一個自己的功能',
        '## 參考',
        '## 給前端',
        '## 已知限制與取捨',
        '## 從舊版升級'
    ];

    /**
     * @return list<string>
     */
    private function commands(): array {
        $names = [];

        foreach (array_keys(Artisan::all()) as $name) {
            if (str_starts_with(strval($name), 'matrix:') || str_starts_with(strval($name), 'messages:')) {
                $names[] = strval($name);
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function firstColumn(string $heading): array {
        $values = array_map(fn (array $row): string => $row[0], $this->rows($heading));

        sort($values);

        return $values;
    }

    /**
     * @return list<array<int, string>>
     */
    private function rows(string $heading): array {
        $body = strstr($this->readme(), "\n{$heading}");

        $this->assertNotFalse($body, "heading '{$heading}' is missing");

        $lines = [];

        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, '|')) {
                $lines[] = $line;
            } elseif ($lines !== []) {
                break;
            }
        }

        array_splice($lines, 0, 2);

        $this->assertNotSame([], $lines, "table under '{$heading}' is empty");

        $rows = [];

        foreach ($lines as $line) {
            $rows[] = array_map(fn (string $cell): string => trim($cell, " \t`"), explode('|', trim($line, '|')));
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function endpoints(): array {
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (!$route->isFallback) {
                $paths[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        sort($paths);

        return $paths;
    }

    private function path(string $relative): string {
        return app(PackageRegistry::class)->path('base') . $relative;
    }

    private function readme(): string {
        return File::get($this->path('/README.md'));
    }

    /**
     * @return list<string>
     */
    private function tables(): array {
        $names = [];

        foreach (array_merge(Schema::getTableListing(null, false), array_column(Schema::getViews(), 'name')) as $name) {
            if (str_starts_with($name, 'base_')) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    public function test_the_readme_declares_every_chapter(): void {
        $readme = $this->readme();

        foreach (self::CHAPTERS as $chapter) {
            $this->assertStringContainsString("\n{$chapter}\n", $readme);
        }
    }

    public function test_the_endpoint_table_matches_the_route_table(): void {
        $documented = [];

        foreach ($this->rows('### 端點') as $row) {
            $documented[] = "{$row[0]} {$row[1]}";
        }

        sort($documented);

        $this->assertSame($this->endpoints(), $documented);
    }

    public function test_the_configuration_table_matches_the_shipped_configuration(): void {
        $documented = [];

        foreach ($this->rows('### 設定鍵') as $row) {
            $documented[] = str_replace('matrix.', '', $row[0]);
        }

        sort($documented);

        $shipped = array_keys(config()->array('matrix'));

        sort($shipped);

        $this->assertSame($shipped, $documented);
    }

    public function test_the_command_table_matches_the_registered_commands(): void {
        $this->assertSame($this->commands(), $this->firstColumn('### 主控台指令'));
    }

    public function test_the_error_table_matches_the_shipped_slugs(): void {
        $shipped = array_keys(require $this->path('/resources/i18n/en/errors.php'));

        sort($shipped);

        $this->assertSame(array_map(strval(...), $shipped), $this->firstColumn('### 錯誤代碼'));
    }

    public function test_every_documented_cfg_key_exists(): void {
        foreach ($this->rows('### cfg 設定鍵') as $row) {
            [$bundle, $key] = explode('.', $row[0], 2);

            $this->assertArrayHasKey($key, require $this->path("/resources/cfg/{$bundle}.php"), "cfg('{$row[0]}') does not exist");
        }
    }

    public function test_the_table_list_matches_the_schema(): void {
        $this->assertSame($this->tables(), $this->firstColumn('### 資料表'));
    }

    public function test_every_documented_middleware_alias_is_registered(): void {
        $aliases = Route::getMiddleware();

        foreach ($this->rows('### 3. 身分') as $row) {
            foreach (explode(' / ', $row[0]) as $alias) {
                $this->assertArrayHasKey(explode(':', trim($alias, '`'))[0], $aliases, "middleware '{$alias}' is not registered");
            }
        }
    }

}
