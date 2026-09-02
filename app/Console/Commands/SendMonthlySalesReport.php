<?php
namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Sale;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * On the 3rd of each month, email admin last month's sales (ex GST).
 * Stored `revenue` is GST-INCLUSIVE, so ex-GST = revenue / 1.1.
 *
 * SendGrid is paused, so this computes the figures and hands them to
 * AdminNotifier (which records them). Wire the actual email tomorrow.
 *
 * Scheduled from routes/console.php to run 07:00 on the 3rd (Brisbane).
 */
class SendMonthlySalesReport extends Command
{
    protected $signature = 'reports:monthly-sales {--month= : YYYY-MM to report on; defaults to last month}';
    protected $description = "Email admin last month's sales (ex GST) — runs on the 3rd";

    public function handle(): int
    {
        $tz = 'Australia/Brisbane';
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->subMonthNoOverflow()->startOfMonth();

        $start = $month->copy()->startOfMonth()->toDateString();
        $end   = $month->copy()->endOfMonth()->toDateString();
        $label = $month->format('F Y');

        $sales = Sale::whereBetween('date', [$start, $end])->get();

        $byLoc = [];
        foreach ($sales as $s) {
            $byLoc[$s->location_id] = ($byLoc[$s->location_id] ?? 0) + (float) $s->revenue;
        }

        $names = Location::pluck('name', 'id');
        $lines = [];
        $totalInc = 0.0;
        foreach ($byLoc as $locId => $inc) {
            $totalInc += $inc;
            $lines[] = [
                'location' => $names[$locId] ?? ('#' . $locId),
                'ex_gst'   => round($inc / 1.1, 2),
                'inc_gst'  => round($inc, 2),
            ];
        }
        usort($lines, fn ($a, $b) => $b['ex_gst'] <=> $a['ex_gst']);

        $totalEx = round($totalInc / 1.1, 2);

        $data = [
            'month'      => $label,
            'period'     => [$start, $end],
            'total_ex_gst'  => $totalEx,
            'total_inc_gst' => round($totalInc, 2),
            'locations'  => $lines,
        ];

        AdminNotifier::notify(
            'monthly_sales_report',
            "Monthly sales report — {$label}: \$" . number_format($totalEx, 2) . ' ex GST',
            $data
        );

        $this->info("Monthly sales report for {$label}: \$" . number_format($totalEx, 2) . ' ex GST across ' . count($lines) . ' laundromats.');
        foreach ($lines as $l) {
            $this->line('  ' . $l['location'] . ': $' . number_format($l['ex_gst'], 2) . ' ex GST');
        }
        return self::SUCCESS;
    }
}
