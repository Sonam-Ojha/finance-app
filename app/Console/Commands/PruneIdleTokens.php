<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;
use Laravel\Sanctum\Sanctum;

/**
 * The daily clean-up after `sanctum:prune-expired` (routes/console.php):
 * app sign-ins unused for longer than sanctum.inactivity_expiration, and
 * phones whose sign-in no longer exists, so they get no more pushes.
 */
class PruneIdleTokens extends Command
{
    protected $signature = 'tokens:prune-idle {--hours=24 : Keep tokens this many hours past their expiry}';

    protected $description = 'Delete app sign-ins unused for too long, and push tokens whose sign-in is gone';

    public function handle(): int
    {
        $model = Sanctum::personalAccessTokenModel();
        $minutes = (int) config('sanctum.inactivity_expiration');

        $tokens = 0;
        if ($minutes > 0) {
            $cutoff = now()->subMinutes($minutes)->subHours((int) $this->option('hours'));
            $tokens = $model::query()
                ->where(fn ($q) => $q
                    ->where('last_used_at', '<', $cutoff)
                    ->orWhere(fn ($q) => $q->whereNull('last_used_at')->where('created_at', '<', $cutoff)))
                ->delete();
        }

        $phones = DeviceToken::query()
            ->whereNotNull('access_token_id')
            ->whereNotIn('access_token_id', $model::query()->select('id'))
            ->delete();

        $this->components->info("Pruned {$tokens} idle sign-ins and {$phones} signed-out phones.");

        return self::SUCCESS;
    }
}
