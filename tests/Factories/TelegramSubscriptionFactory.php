<?php //>

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MatrixPlatform\Models\TelegramSubscription;

/**
 * @extends Factory<TelegramSubscription>
 */
class TelegramSubscriptionFactory extends Factory {

    protected $model = TelegramSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => $this->faker->unique()->randomNumber(),
            'chat_id' => $this->faker->unique()->numerify('######')
        ];
    }

}
