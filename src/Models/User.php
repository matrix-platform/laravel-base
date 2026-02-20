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
        $token = Str::uuid();

        $auth = new AuthToken();
        $auth->token = $token;
        $auth->type = 'User';
        $auth->target_id = $this->id;
        $auth->save();

        return $token;
    }

    public function writeLog($type, $content = null) {
        $log = new UserLog();
        $log->user_id = $this->id;
        $log->type = $type;
        $log->content = $content;
        $log->save();
    }

}
