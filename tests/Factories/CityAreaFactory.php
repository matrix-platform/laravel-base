<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\CityArea;

/**
 * @extends Factory<CityArea>
 */
class CityAreaFactory extends Factory {

    protected $model = CityArea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'title__tw' => $this->faker->unique()->word(),
            'title__en' => $this->faker->unique()->word(),
            'post_code' => $this->faker->unique()->postcode()
        ];
    }

}
