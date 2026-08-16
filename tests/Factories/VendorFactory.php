<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\Vendor;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory {

    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'username' => $this->faker->unique()->userName(),
            'password' => 'secret-Passw0rd',
            'title' => $this->faker->unique()->company()
        ];
    }

}
