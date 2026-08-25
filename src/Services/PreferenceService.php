<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\Preference;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\Vendor;

class PreferenceService {

    /**
     * @return array<string, mixed>
     */
    public function get(Model $identity): array {
        $preference = $this->find($identity);

        return $preference === null ? [] : $preference->data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(Model $identity, array $data, bool $merge): array {
        $preference = $this->find($identity);

        if ($preference === null) {
            $preference = new Preference();

            $preference->identity_type = $this->type($identity);
            $preference->identity_id = $identity->getKey();
        }

        $preference->data = $merge ? array_merge($preference->data, $data) : $data;

        $preference->save();

        return $preference->data;
    }

    private function find(Model $identity): ?Preference {
        return Preference::query()
            ->where('identity_type', $this->type($identity))
            ->where('identity_id', $identity->getKey())
            ->first();
    }

    private function type(Model $identity): IdentityType {
        return match (true) {
            $identity instanceof User => IdentityType::User,
            $identity instanceof Member => IdentityType::Member,
            $identity instanceof Vendor => IdentityType::Vendor,
            default => error('invalid-identity-type')
        };
    }

}
