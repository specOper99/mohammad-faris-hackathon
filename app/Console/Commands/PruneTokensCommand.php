<?php

namespace App\Console\Commands;

use App\Enums\EmailOutboxStatus;
use App\Models\EmailOutbox;
use App\Models\OneTimeToken;
use Illuminate\Console\Command;

final class PruneTokensCommand extends Command
{
    protected $signature = 'exoplanet:prune-tokens';

    protected $description = 'Prune consumed tokens and sent outbox rows';

    public function handle(): int
    {
        OneTimeToken::query()
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '<', now()->subDays(30))
            ->delete();

        EmailOutbox::query()
            ->where('status', EmailOutboxStatus::Sent)
            ->where('sent_at', '<', now()->subDays(14))
            ->delete();

        return self::SUCCESS;
    }
}
