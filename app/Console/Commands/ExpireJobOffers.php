<?php

namespace App\Console\Commands;

use App\Services\Recruitment\OfferService;
use Illuminate\Console\Command;

class ExpireJobOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recruitment:expire-offers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark issued job offers whose expiry date has passed as expired (safe to re-run)';

    /**
     * Execute the console command.
     */
    public function handle(OfferService $offers): int
    {
        $expired = $offers->expireDue();

        $this->info("Expired {$expired} job offer(s).");

        return self::SUCCESS;
    }
}
