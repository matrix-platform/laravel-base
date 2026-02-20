<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Str;
use MatrixPlatform\Traits\Traceable;

class User extends BaseModel {

    use Traceable;

    protected $attributes = ['disabled' => false];
    protected $casts = ['password' => 'hashed', 'enable_time' => 'datetime', 'disable_time' => 'datetime'];
    protected $hidden = ['password'];
    protected $table = 'base_user';
    protected $untraceable = ['password'];

    public function createToken() {
        $token = Str::random(64);

        $auth = new AuthToken();
        $auth->token = hash('sha256', $token);
        $auth->type = 1;
        $auth->target_id = $this->id;
        $auth->save();

        return $token;
    }

    public function writeLog($type) {
        $log = new UserLog();
        $log->user_id = $this->id;
        $log->type = $type;
        $log->save();
    }

}
