<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Support\Resources;

class ClearResourceCacheCommand extends Command {

    protected $description = 'Clear every cached cfg/i18n/menu/style resource bundle and the DB override cache';

    protected $signature = 'matrix:clear-resource-cache';

    public function handle(Resources $resources): int {
        $resources->forgetAll();

        $this->info('Resource cache cleared');

        return self::SUCCESS;
    }

}
