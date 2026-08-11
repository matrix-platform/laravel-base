<?php //>

namespace Tests\Stubs;

class Gizmo extends Gadget {

    protected $appends = ['shout'];

    public function getShoutAttribute(): string {
        return strtoupper(strval($this->title));
    }

}
