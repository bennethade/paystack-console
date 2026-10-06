<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Support\Settings;
use Illuminate\Console\Command;

class PruneHistoryCommand extends Command
{
    protected $signature = 'paystack:prune-history {--days= : Override the configured retention}';

    protected $description = 'Delete request history older than the retention period';

    public function handle(Settings $settings): int
    {
        $days = (int) ($this->option('days') ?? $settings->get('history_retention_days', config('paystack.history.retention_days')));

        $deleted = ApiRequestLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} request log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
