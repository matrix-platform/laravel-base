<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Str;
use MatrixPlatform\Models\Group;
use MatrixPlatform\Traits\Traceable;

class User extends BaseModel {

    use Traceable;

    const ROOT = 1;
    const ADMIN = 2;
    const REGULAR = 3;

    protected $attributes = ['disabled' => false];
    protected $casts = ['password' => 'hashed', 'enable_time' => 'datetime', 'disable_time' => 'datetime'];
    protected $hidden = ['password'];
    protected $table = 'base_user';
    protected $title = 'username';
    protected $untraceable = ['password'];

    public function createToken() {
        $token = Str::uuid();
        $ttl = cfg('admin.token-ttl-days');

        $auth = new AuthToken;
        $auth->token = $token;
        $auth->type = 'User';
        $auth->target_id = $this->id;
        $auth->expire_time = $ttl ? now()->addDays($ttl) : null;
        $auth->save();

        return $token;
    }

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function writeLog($type, $content = null) {
        $log = new UserLog;
        $log->user_id = $this->id;
        $log->type = $type;
        $log->content = $content;
        $log->save();
    }

}
