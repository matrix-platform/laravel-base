<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Models\EncryptionKey;

class RotateEncryptionKeyCommand extends Command {

    protected $description = 'Issue a new API encryption key and expire the previous one';

    protected $signature = 'matrix:rotate-encryption-key {--grace=}';

    public function handle(): int {
        $grace = $this->grace();

        if ($grace === null) {
            $this->error('The --grace option must be a non-negative integer');

            return self::FAILURE;
        }

        $key = EncryptionKey::rotate($grace);

        $this->info("Issued encryption key {$key->kid} with a {$grace} second grace period");

        return self::SUCCESS;
    }

    private function grace(): ?int {
        $option = array_get_value($this->options(), 'grace');

        if ($option === null) {
            return intval(cfg('encryption.grace-period'));
        }

        $grace = strval($option);

        return ctype_digit($grace) ? intval($grace) : null;
    }

}
