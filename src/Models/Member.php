<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Support\Str;
use MatrixPlatform\Traits\Traceable;

class Member extends BaseModel {

    use Traceable;

    protected $attributes = ['status' => 1];
    protected $casts = ['password' => 'hashed'];
    protected $hidden = ['password'];
    protected $table = 'base_member';
    protected $untraceable = ['password'];

    public function createToken() {
        $token = Str::uuid();
        $ttl = cfg('member.token-ttl-days');

        $auth = new AuthToken;
        $auth->token = $token;
        $auth->type = 'Member';
        $auth->target_id = $this->id;
        $auth->expire_time = $ttl ? now()->addDays($ttl) : null;
        $auth->save();

        return $token;
    }

    public function writeLog($type, $content = null) {
        $log = new MemberLog;
        $log->member_id = $this->id;
        $log->type = $type;
        $log->content = $content;
        $log->save();
    }

}
