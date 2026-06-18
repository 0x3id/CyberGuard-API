<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrganizationSubscription;

class ResetOrganizationScans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:reset-scans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the monthly scans used counter for all active organization subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting monthly scans for organizations...');

        $updated = OrganizationSubscription::where('status', 'active')
            ->update(['scans_used_this_month' => 0]);

        $this->info("Successfully reset scans for {$updated} active subscriptions.");
    }
}
