<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatrixPlatform\Models\User;
use MatrixPlatform\Services\Admin\PasswordService;

class ResetUserPasswordCommand extends Command {

    protected $description = 'Reset the password of an administrator account';

    protected $signature = 'matrix:passwd {username}';

    public function handle(): int {
        $username = strval(array_get_value($this->arguments(), 'username'));
        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            $this->error("User '{$username}' does not exist");

            return self::FAILURE;
        }

        $password = strval($this->secret('New password'));

        if ($password === '') {
            $this->error('No password supplied');

            return self::FAILURE;
        }

        if (preg_match(strval(cfg('admin.password-pattern')), $password) !== 1) {
            $this->error('Password does not satisfy the configured policy');

            return self::FAILURE;
        }

        if ($password !== $this->secret('Retype new password')) {
            $this->error('Passwords do not match');

            return self::FAILURE;
        }

        DB::transaction(fn () => app(PasswordService::class)->replace($user, $password));

        $this->info('Password updated successfully');

        return self::SUCCESS;
    }

}
