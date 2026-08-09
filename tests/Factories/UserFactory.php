<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory {

    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'username' => $this->faker->unique()->userName(),
            'password' => 'secret-Passw0rd',
            'enable_time' => now(),
            'disabled' => false
        ];
    }

}
