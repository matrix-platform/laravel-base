<?php //>

namespace MatrixPlatform\Support;

class Actor {

    private $guest;
    private $member;
    private $user;

    public function __construct() {
        $this->guest = new ActorProxy();
    }

    public function __get($name) {
        return match($name) {
            'member' => $this->member ?? $this->guest,
            'user'   => $this->user ?? $this->guest,
            default  => error('unsupported-operation')
        };
    }

    public function __set($name, $value) {
        match($name) {
            'member' => $this->writeMember($value),
            'user'   => $this->writeUser($value),
            default  => error('unsupported-operation')
        };
    }

    private function writeMember($model) {
        if ($this->member) {
            error('unsupported-operation');
        }

        $this->member = new ActorProxy($model);
    }

    private function writeUser($model) {
        if ($this->user) {
            error('unsupported-operation');
        }

        $this->user = new ActorProxy($model);
    }

}
