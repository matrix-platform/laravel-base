<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\Group;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory {

    protected $model = Group::class;

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
