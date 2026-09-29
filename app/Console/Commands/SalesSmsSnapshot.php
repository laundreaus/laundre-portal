<?php
namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Brief sales snapshot sent to admins by SMS (Twilio). Three cadences:
 *   daily   — yesterday's trading
 *   weekly  — the last 7 days (ending yesterday)
 *   monthly — the previous calendar month
 * Figures are ex GST (stored revenue is GST-inclusive, so ÷ 1.1).
 */
class SalesSmsSnapshot extends Command
{
    protected $signature = 'reports:sales-sms {period=daily : daily|weekly|monthly}';
    protected $description = 'Text admins a brief sales snapshot (daily / weekly / monthly)';

    public function handle(): int
    {
        $tz = 'Australia/Brisbane';
        $period = $this->argument('period');
        $now = Carbon::now($tz);

        if ($period === 'weekly') {
            $start = $now->copy()->subDays(7)->toDateString();
            $end   = $now->copy()->subDay()->toDateString();
            $label = 'Last 7 days';
        } elseif ($period === 'monthly') {
            $start = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $end   = $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $label = $now->copy()->subMonthNoOverflow()->format('F Y');
        } else {
            $start = $end = $now->copy()->subDay()->toDateString();
            $label = 'Yesterday (' . $now->copy()->subDay()->format('D j M') . ')';
        }

        $rows = Sale::whereBetween('date', [$start, $end])->get();
        $incGst = (float) $rows->sum('revenue');
        $exGst  = $incGst / 1.1;
        $txns   = (int) $rows->sum('txns');

        $msg = 'Sales — ' . $label . ': $' . number_format($exGst, 0) . ' ex GST'
             . ($txns ? ' · ' . number_format($txns) . ' txns' : '')
             . ' across ' . $rows->pluck('location_id')->unique()->count() . ' sites';

        $ok = AdminNotifier::sms($msg);
        $this->info(($ok ? 'Sent' : 'Skipped (Twilio not configured / no numbers)') . ': ' . $msg);
        return self::SUCCESS;
    }
}
