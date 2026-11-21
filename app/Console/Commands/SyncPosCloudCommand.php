<?php

namespace App\Console\Commands;

use App\Services\CloudSyncService;
use Illuminate\Console\Command;

class SyncPosCloudCommand extends Command
{
    protected $signature = 'pos:sync-cloud
                            {--days=7 : Number of recent business days to aggregate and sync}
                            {--ai : Also refresh AI performance insight report after sync}
                            {--manual : Mark trigger type as manual instead of scheduled}';

    protected $description = 'Aggregate local POS daily analytics snapshots and sync them to the live Cloud Hub server (25% Cloud AI layer)';

    public function handle(CloudSyncService $syncService): int
    {
        $days = max(1, min(90, (int) $this->option('days')));
        $trigger = $this->option('manual') ? 'manual' : 'scheduled';
        $refreshAi = (bool) $this->option('ai');

        $this->info("Running Hybrid POS Cloud Sync (lookback={$days}d, trigger={$trigger})...");

        $log = $syncService->syncOutbound(
            lookbackDays: $days,
            trigger: $trigger,
            generateAiAfter: $refreshAi
        );

        $this->line("Status: {$log->status} | Snapshots: {$log->snapshots_count} | Duration: {$log->duration_ms}ms");
        $this->line("Message: {$log->message}");

        return $log->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
