<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\Member;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory {

    protected $model = Member::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'username' => $this->faker->unique()->userName(),
            'password' => 'secret-Passw0rd'
        ];
    }

}
