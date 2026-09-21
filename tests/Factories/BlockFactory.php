<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\Block;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory {

    protected $model = Block::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'type' => 'editor',
            'title' => $this->faker->unique()->word(),
            'enable_time' => now()->subDay()
        ];
    }

}
