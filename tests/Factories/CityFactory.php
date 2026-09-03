<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\City;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory {

    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'title__tw' => $this->faker->unique()->word(),
            'title__en' => $this->faker->unique()->word()
        ];
    }

}
