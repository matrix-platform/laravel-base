<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Models\AuthToken;
use MatrixPlatform\Models\Builders\BaseBuilder;
use MatrixPlatform\Models\IdentityType;

class PruneTokensCommand extends Command {

    protected $description = 'Delete auth tokens that can no longer authenticate';

    protected $signature = 'matrix:prune-tokens {--limit=1000}';

    public function handle(): int {
        $limit = $this->limit();

        if ($limit === null) {
            $this->error('The --limit option must be a positive integer');

            return self::FAILURE;
        }

        foreach (IdentityType::cases() as $type) {
            $deleted = $this->prune($type, $limit);

            $this->info("Deleted {$deleted} dead {$type->bundle()} tokens");
        }

        return self::SUCCESS;
    }

    private function limit(): ?int {
        $limit = strval(array_get_value($this->options(), 'limit'));

        return ctype_digit($limit) && intval($limit) > 0 ? intval($limit) : null;
    }

    private function prune(IdentityType $type, int $limit): int {
        $idle = AuthToken::idleSince($type);
        $total = 0;

        while (true) {
            $tokens = AuthToken::query()
                ->where('type', $type)
                ->where(fn (BaseBuilder $query) => $query->whereExpired()->orWhere('update_time', '<', $idle)->orWhereNull('update_time'))
                ->limit($limit)
                ->get();

            foreach ($tokens as $token) {
                $token->delete();
            }

            $total += $tokens->count();

            if ($tokens->count() < $limit) {
                return $total;
            }
        }
    }

}
