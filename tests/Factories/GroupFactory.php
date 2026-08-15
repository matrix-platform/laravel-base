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
            'title' => $this->faker->unique()->word()
        ];
    }

}
