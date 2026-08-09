<?php //>

namespace MatrixPlatform\Support;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\User;

class Actor {

    private ?Model $member = null;
    private ?User $user = null;
    private ?Model $vendor = null;

    public function current(): ?Model {
        return $this->user ?: $this->member ?: $this->vendor;
    }

    public function member(): ?Model {
        return $this->member;
    }

    public function requireUser(): User {
        if ($this->user === null) {
            error('invalid-token', 401);
        }

        return $this->user;
    }

    public function setMember(Model $member): void {
        if ($this->member !== null) {
            error('actor-already-assigned');
        }

        $this->member = $member;
    }

    public function setUser(User $user): void {
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

    public function user(): ?User {
        return $this->user;
    }

    public function vendor(): ?Model {
        return $this->vendor;
    }

}
