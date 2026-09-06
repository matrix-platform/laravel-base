<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\PasskeyCredential;
use Symfony\Component\Uid\Uuid;

/**
 * @extends Factory<PasskeyCredential>
 */
class PasskeyCredentialFactory extends Factory {

    protected $model = PasskeyCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => $this->faker->unique()->randomNumber(),
            'credential_id' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'public_key' => base64_encode(random_bytes(77)),
            'aaguid' => (string) Uuid::v4(),
            'sign_count' => 0,
            'name' => $this->faker->words(2, true)
        ];
    }

}
