<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessLeaveCredit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $schedule;

    public function __construct($schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get previous balance for the leave type for the total earned
        $total_earned = DB::table('employee_leave_credits_histories')
            ->where('leave_id', $this->schedule->leave_id)
            ->where('employee_id', $this->schedule->employee_id)
            ->orderByDesc('created_at')
            ->first();

        $previous_balance = $total_earned->balance ?? 0;


        DB::table('employee_leave_credits_histories')->insert([
            'employee_id' => $this->schedule->employee_id,
            'leave_id' => $this->schedule->leave_id,
            'total_earned' => $previous_balance,
            'credit_addition' => $this->schedule->credits_to_add,
            'credit_deduction' => 0,
            'balance' => $previous_balance + (float) $this->schedule->credits_to_add,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
