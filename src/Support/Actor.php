<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;

class Actor {

    private ?Model $member = null;
    private ?Model $user = null;
    private ?Model $vendor = null;

    public function current(): ?Model {
        return $this->user ?: $this->member ?: $this->vendor;
    }

    public function member(): ?Model {
        return $this->member;
    }

    public function setMember(Model $member): void {
        if ($this->member !== null) {
            error('actor-already-assigned');
        }

        $this->member = $member;
    }

    public function setUser(Model $user): void {
        if ($this->user !== null) {
            error('actor-already-assigned');
        }

        $this->user = $user;
    }

    public function setVendor(Model $vendor): void {
        if ($this->vendor !== null) {
            error('actor-already-assigned');
        }

        $this->vendor = $vendor;
    }

    public function user(): ?Model {
        return $this->user;
    }

    public function vendor(): ?Model {
        return $this->vendor;
    }

}
