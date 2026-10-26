<?php //>

namespace Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use MatrixPlatform\Models\Page;
use Tests\Factories\PageFactory;
use Tests\FeatureTestCase;

class PageTest extends FeatureTestCase {

    private function page(string $path, ?Carbon $enable = null, ?Carbon $disable = null): Page {
        return PageFactory::new()->createOne([
            'path' => $path,
            'title' => $path,
            'enable_time' => $enable,
            'disable_time' => $disable,
            'ranking' => 0
        ]);
    }

    public function test_the_data_column_is_stored_as_jsonb(): void {
        $this->assertSame('jsonb', Schema::getColumnType('base_page', 'data'));
    }

    public function test_the_data_column_reads_back_as_an_array(): void {
        $created = $this->page('about-us');

        $created->data = ['seo_title' => ['en' => 'About Us'], 'fluid' => true];

        $created->save();

        $data = Page::query()->firstOrFail()->data;

        $this->assertNotNull($data);
        $this->assertTrue(array_get_value($data, 'fluid'));
        $this->assertSame(['en' => 'About Us'], array_get_value($data, 'seo_title'));
    }

    public function test_the_schedule_columns_read_back_as_dates(): void {
        $this->page('about-us', Carbon::parse('2026-01-02 03:04:05'), Carbon::parse('2026-02-03 04:05:06'));

        $page = Page::query()->firstOrFail();

        $this->assertInstanceOf(Carbon::class, $page->enable_time);
        $this->assertInstanceOf(Carbon::class, $page->disable_time);
        $this->assertSame('2026-01-02 03:04:05', $page->enable_time->format('Y-m-d H:i:s'));
    }

    public function test_the_path_column_carries_a_unique_index(): void {
        $found = [];

        foreach (Schema::getIndexes('base_page') as $index) {
            if ($index['columns'] === ['path']) {
                $found[] = $index;
            }
        }

        $this->assertCount(1, $found);
        $this->assertTrue($found[0]['unique']);
    }

}
