<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Spec section 5.7: "Sale closes -- the report for that sale is finalised (the
 * figures stop changing) and the creator is emailed."
 */
class CloseEndedSales extends Command
{
    protected $signature = 'sales:close-ended
                            {--dry-run : List what would be closed without closing it}';

    protected $description = 'Finalise reports and email creators for any sale that has passed its end date';

    public function handle(SettlementService $settlements): int
    {
        $due = Sale::query()
            ->whereNull('closed_at')
            ->where('ends_at', '<', Carbon::now())
            ->orderBy('ends_at')
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($due as $sale) {
            if ($this->option('dry-run')) {
                $this->line("Would close: {$sale->name} (ended {$sale->ends_at->format('j M Y H:i')})");

                continue;
            }

            $count = $settlements->closeSale($sale);

            $this->info("Closed {$sale->name}: {$count} creator reports finalised and emailed.");
        }

        return self::SUCCESS;
    }
}
