<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $chat_id
 * @property ?string $username
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
class TelegramSubscription extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'base_telegram_subscription';

}
