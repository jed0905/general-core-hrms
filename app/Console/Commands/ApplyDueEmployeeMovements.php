<?php

namespace App\Console\Commands;

use App\Services\EmployeeMovementService;
use Illuminate\Console\Command;

class ApplyDueEmployeeMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee-movements:apply-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply scheduled employee movements whose effective date has arrived (safe to re-run)';

    /**
     * Execute the console command.
     */
    public function handle(EmployeeMovementService $movements): int
    {
        $applied = $movements->applyDue();

        $this->info("Applied {$applied} employee movement(s).");

        return self::SUCCESS;
    }
}
