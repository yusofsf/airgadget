<?php

namespace App\Console\Commands;

use App\Services\OrderReservationService;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Release inventory for unpaid orders after their ten-minute payment window';

    public function handle(OrderReservationService $reservations): int
    {
        $count = $reservations->expireUnpaidOrders();
        $this->info("{$count} unpaid order(s) expired.");

        return self::SUCCESS;
    }
}
