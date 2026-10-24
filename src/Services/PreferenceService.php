<?php //>

namespace MatrixPlatform\Services;

use Illuminate\Database\Eloquent\Model;
use MatrixPlatform\Models\IdentityType;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\Preference;
use MatrixPlatform\Models\User;
use MatrixPlatform\Models\Vendor;
use MatrixPlatform\Support\CfgSchema;
use MatrixPlatform\Support\ResourceGroup;
use MatrixPlatform\Support\Resources;

/**
 * @phpstan-import-type Column from CfgSchema
 */
class PreferenceService {

    public function __construct(private Resources $resources, private CfgSchema $schema) {}

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
    public function mask(Model $identity, array $data): array {
        foreach ($this->columns($identity) as $column) {
            $key = $column['name'];

            if ($column['secret'] && array_key_exists($key, $data) && !$this->schema->cleared($data[$key])) {
                $data[$key] = CfgSchema::MASK;
            }
        }

        return $data;
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

        $columns = $this->columns($identity);
        $defaults = $this->defaults($identity);
        $base = $merge ? $preference->data : [];

        foreach ($columns as $column) {
            $key = $column['name'];

            if ($column['secret'] && array_get_value($data, $key) === CfgSchema::MASK && array_key_exists($key, $preference->data)) {
                $base[$key] = $preference->data[$key];
            }
        }

        $preference->data = $this->schema->apply(array_merge($base, array_diff_key($data, $defaults)), $columns, $data, $defaults);

        $preference->save();

        return $preference->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(Model $identity): array {
        $columns = $this->columns($identity);
        $stored = $this->mask($identity, $this->get($identity));
        $data = [];
        $defaults = [];

        foreach ($columns as $column) {
            $key = $column['name'];

            $data[$key] = array_get_value($stored, $key, $column['default']);
            $defaults[$key] = $column['default'];
        }

        return [
            'columns' => $columns,
            'data' => $data,
            'default' => $defaults
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Model $identity, mixed $input): array {
        $values = is_array($input) ? $input : [];

        $this->save($identity, array_intersect_key($values, $this->defaults($identity)), true);

        return $this->settings($identity);
    }

    public function value(Model $identity, string $key): mixed {
        $defaults = $this->defaults($identity);

        if (!array_key_exists($key, $defaults)) {
            error('invalid-preference-key');
        }

        return array_get_value($this->get($identity), $key, $defaults[$key]);
    }

    private function bundle(Model $identity): string {
        return 'preference-' . strtolower($this->type($identity)->value);
    }

    /**
     * @return list<Column>
     */
    private function columns(Model $identity): array {
        $columns = $this->schema->columns(ResourceGroup::Cfg, 'cfg/' . $this->bundle($identity), $this->defaults($identity));

        if (!$this->hasDrivePermissions($identity)) {
            foreach ($columns as $column) {
                if (in_array($column['presentation'], CfgSchema::DRIVES, true)) {
                    error('invalid-preference-column');
                }
            }
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(Model $identity): array {
        $defaults = $this->resources->getConfigBundle($this->bundle($identity));

        return $defaults === null ? [] : $defaults;
    }

    private function find(Model $identity): ?Preference {
        return Preference::query()
            ->where('identity_type', $this->type($identity))
            ->where('identity_id', $identity->getKey())
            ->first();
    }

    private function hasDrivePermissions(Model $identity): bool {
        return $identity instanceof User;
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
