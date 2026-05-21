<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Models\AuthToken;

class PruneCommand extends Command {

    protected $signature = 'matrix:prune {--limit=1000}';

    public function handle() {
        $this->pruneAuthTokens();

        return Command::SUCCESS;
    }

    protected function pruneAuthTokens() {
        $total = 0;

        while (true) {
            $deleted = AuthToken::where('expire_time', '<', now())->limit($this->option('limit'))->delete();

            if ($deleted) {
                $total += $deleted;
            } else {
                break;
            }
        }

        $this->info("Deleted {$total} expired auth tokens");
    }

}
