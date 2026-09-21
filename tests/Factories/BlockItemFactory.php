<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\BlockItem;

/**
 * @extends Factory<BlockItem>
 */
class BlockItemFactory extends Factory {

    protected $model = BlockItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'title' => $this->faker->unique()->word(),
            'enable_time' => now()->subDay()
        ];
    }

}
