<?php

namespace App\Console\Commands;

use App\Actions\ExpireStalePaymentRequests;
use Illuminate\Console\Command;

class ExpirePaymentRequests extends Command
{
    protected $signature = 'payment-requests:expire';

    protected $description = 'Expire pending payment requests older than the 48h TTL (US-C1).';

    public function handle(ExpireStalePaymentRequests $expire): int
    {
        $count = $expire();

        $this->info("Expired {$count} pending payment request(s).");

        return self::SUCCESS;
    }
}
