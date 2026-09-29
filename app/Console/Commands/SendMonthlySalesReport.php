<?php
namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * On the 3rd of each month, email last month's sales (ex GST).
 *  - Admins (luke@ / adam@): every laundromat.
 *  - Each franchisee / investor: the laundromats assigned to them.
 *  - Custom recipients (config: custom_monthly), e.g. CBRE centre management: one laundromat only.
 * Stored `revenue` is GST-INCLUSIVE, so ex-GST = revenue / 1.1.
 * Scheduled from routes/console.php to run 07:00 on the 3rd (Brisbane).
 */
class SendMonthlySalesReport extends Command
{
    protected $signature = 'reports:monthly-sales {--month= : YYYY-MM to report on; defaults to last month} {--to= : test — send the admin (all-laundromat) email to this address only}';
    protected $description = "Email last month's sales (ex GST) to admins, franchisees/investors and custom recipients";

    private $names;
    private $byLoc = [];
    private $label = '';

    public function handle(): int
    {
        $tz = 'Australia/Brisbane';
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->subMonthNoOverflow()->startOfMonth();

        $start = $month->copy()->startOfMonth()->toDateString();
        $end   = $month->copy()->endOfMonth()->toDateString();
        $this->label = $month->format('F Y');

        foreach (Sale::whereBetween('date', [$start, $end])->get() as $s) {
            $this->byLoc[$s->location_id] = ($this->byLoc[$s->location_id] ?? 0) + (float) $s->revenue;
        }
        $this->names = Location::pluck('name', 'id');

        // 1) Admins — all laundromats.
        $adminTo = $this->option('to')
            ? array_map('trim', explode(',', $this->option('to')))
            : (config('laundre_notify.recipients') ?? []);
        if (!empty($adminTo)) $this->emailScope($adminTo, array_keys($this->byLoc), 'All laundromats');

        if (!$this->option('to')) {
            // 2) Franchisees / investors — their assigned laundromats.
            foreach (User::whereIn('role', ['franchisee', 'investor'])->whereNotNull('email')->get() as $u) {
                $ids = array_values(array_filter($u->locationIds(), fn ($id) => isset($this->byLoc[$id])));
                if (!empty($ids)) $this->emailScope([$u->email], $ids, 'Your laundromat' . (count($ids) > 1 ? 's' : ''));
            }
            // 3) Custom recipients — a single named laundromat only.
            foreach ((config('laundre_notify.custom_monthly') ?? []) as $c) {
                if (empty($c['email']) || empty($c['location'])) continue;
                $ids = [];
                foreach ($this->names as $id => $name) {
                    if (stripos($name, $c['location']) !== false && isset($this->byLoc[$id])) $ids[] = $id;
                }
                if (!empty($ids)) $this->emailScope([$c['email']], $ids, $c['label'] ?? $c['location']);
            }
        }

        $this->info("Monthly sales report for {$this->label} sent.");
        return self::SUCCESS;
    }

    private function emailScope(array $to, array $ids, string $scopeWord): void
    {
        $lines = [];
        $totalInc = 0.0;
        usort($ids, fn ($a, $b) => ($this->byLoc[$b] ?? 0) <=> ($this->byLoc[$a] ?? 0));
        foreach ($ids as $id) {
            $inc = (float) ($this->byLoc[$id] ?? 0);
            $totalInc += $inc;
            $lines[] = ['label' => $this->names[$id] ?? ('#' . $id), 'value' => '$' . number_format($inc / 1.1, 2) . ' ex GST'];
        }
        $totalEx = $totalInc / 1.1;
        if (count($ids) > 1) $lines[] = ['label' => 'Total', 'value' => '$' . number_format($totalEx, 2) . ' ex GST'];

        AdminNotifier::sendTemplate('monthly_sales_report', $to, [
            'subject' => "Laundré monthly sales — {$this->label}: \$" . number_format($totalEx, 2) . ' ex GST',
            'heading' => $this->label . ' — $' . number_format($totalEx, 2) . ' ex GST',
            'summary' => $scopeWord . ' · sales for ' . $this->label . ' (ex GST).',
            'lines'   => $lines,
            'note'    => 'Figures are ex GST (recorded trading revenue ÷ 1.1).',
        ]);
    }
}
