<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\Page;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory {

    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'path' => $this->faker->unique()->slug(2),
            'title' => $this->faker->unique()->word(),
            'enable_time' => now()->subDay()
        ];
    }

}
