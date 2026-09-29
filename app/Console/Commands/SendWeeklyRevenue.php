<?php
namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Every Monday 3pm (Brisbane): email last week's revenue.
 *  - Admins (luke@ / adam@) get every laundromat.
 *  - Each franchisee / investor gets the laundromats assigned to them.
 * Revenue is the stored trading figure (matches the dashboard).
 */
class SendWeeklyRevenue extends Command
{
    protected $signature = 'reports:weekly-revenue {--to= : comma-separated test recipients (admin scope, all laundromats)}';
    protected $description = "Email last week's revenue to admins and to each assigned franchisee/investor";

    public function handle(): int
    {
        $tz = 'Australia/Brisbane';
        // Previous full week: Monday..Sunday ending yesterday when run on a Monday.
        $end = Carbon::now($tz)->subDay()->endOfDay();          // yesterday (Sunday)
        $start = $end->copy()->subDays(6)->startOfDay();          // previous Monday
        $s = $start->toDateString();
        $e = $end->toDateString();
        $label = $start->format('j M') . ' – ' . $end->format('j M Y');

        $sales = Sale::whereBetween('date', [$s, $e])->get();
        $byLoc = [];
        foreach ($sales as $sale) {
            $byLoc[$sale->location_id] = ($byLoc[$sale->location_id] ?? 0) + (float) $sale->revenue;
        }
        $names = Location::pluck('name', 'id');

        // ---- Admin email (all laundromats) ----
        $adminTo = $this->option('to')
            ? array_map('trim', explode(',', $this->option('to')))
            : (config('laundre_notify.recipients') ?? []);
        if (!empty($adminTo)) {
            $this->emailScope($adminTo, array_keys($byLoc), $byLoc, $names, $label, 'All laundromats');
        }

        // ---- Per franchisee / investor ----
        if (!$this->option('to')) {
            $users = User::whereIn('role', ['franchisee', 'investor'])->whereNotNull('email')->get();
            foreach ($users as $u) {
                $ids = array_values(array_filter($u->locationIds()));
                if (empty($ids)) continue;
                $ids = array_values(array_filter($ids, fn ($id) => isset($byLoc[$id])));
                if (empty($ids)) continue; // nothing to report for their stores
                $this->emailScope([$u->email], $ids, $byLoc, $names, $label, 'Your laundromat' . (count($ids) > 1 ? 's' : ''));
            }
        }

        $this->info("Weekly revenue emails sent for {$label}.");
        return self::SUCCESS;
    }

    private function emailScope(array $to, array $ids, array $byLoc, $names, string $label, string $scopeWord): void
    {
        $lines = [];
        $total = 0.0;
        // sort by revenue desc
        usort($ids, fn ($a, $b) => ($byLoc[$b] ?? 0) <=> ($byLoc[$a] ?? 0));
        foreach ($ids as $id) {
            $rev = (float) ($byLoc[$id] ?? 0);
            $total += $rev;
            $lines[] = ['label' => $names[$id] ?? ('#' . $id), 'value' => '$' . number_format($rev, 2)];
        }
        $lines[] = ['label' => 'Total', 'value' => '$' . number_format($total, 2)];

        AdminNotifier::sendTemplate('weekly_revenue', $to, [
            'subject' => "Laundré weekly revenue — {$label}: \$" . number_format($total, 2),
            'heading' => 'Last week: $' . number_format($total, 2),
            'summary' => $scopeWord . ' · trading revenue for ' . $label . '.',
            'lines'   => $lines,
            'note'    => 'Figures are the recorded trading revenue for each site. Open the dashboard for daily detail.',
        ]);
    }
}
