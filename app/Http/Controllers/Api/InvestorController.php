<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvestorController extends Controller
{
    /**
     * Revenue data for the current investor's assigned laundromats.
     * Admins get all locations (for preview / inspection).
     */
    public function data(Request $r)
    {
        $u = $r->user();
        abort_unless($u->isInvestor() || $u->isAdmin(), 403);

        $ids = $u->isAdmin() ? Location::pluck('id')->all() : $u->investorLocationIds();
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $locations = Location::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);

        $out = [];
        foreach ($locations as $loc) {
            $rows = Sale::where('location_id', $loc->id)->orderBy('date')->get(['date', 'revenue']);
            $s = $this->series($rows);
            $out[] = ['id' => $loc->id, 'name' => $loc->name] + $s;
        }

        $allRows = $ids ? Sale::whereIn('location_id', $ids)->orderBy('date')->get(['date', 'revenue']) : collect();
        $combined = $this->series($allRows);

        return response()->json([
            'is_admin_preview' => $u->isAdmin(),
            'count'            => $locations->count(),
            'locations'        => $out,
            'combined'         => $combined,
        ]);
    }

    /** Build a last-12-month + last-3-FY revenue series from Sale rows. */
    private function series($rows): array
    {
        $byMonth = [];
        $byFy = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $c = $row->date instanceof Carbon ? $row->date : Carbon::parse((string) $row->date);
            $ym = $c->format('Y-m');
            $rev = (float) $row->revenue;
            $byMonth[$ym] = ($byMonth[$ym] ?? 0) + $rev;
            // Australian financial year (Jul–Jun); label = the calendar year it ends in.
            $fy = $c->month >= 7 ? $c->year + 1 : $c->year;
            $byFy[$fy] = ($byFy[$fy] ?? 0) + $rev;
            $total += $rev;
        }

        // Last 12 months, ending at the most recent month that has data.
        if ($byMonth) {
            $keys = array_keys($byMonth);
            sort($keys);
            $endC = Carbon::createFromFormat('Y-m', end($keys))->startOfMonth();
        } else {
            $endC = Carbon::now()->startOfMonth();
        }
        $mLabels = [];
        $mValues = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $endC->copy()->subMonths($i);
            $mLabels[] = $m->format('M y');
            $mValues[] = round($byMonth[$m->format('Y-m')] ?? 0, 2);
        }

        // Last 3 financial years, ending at the most recent FY that has data.
        if ($byFy) {
            $fys = array_keys($byFy);
            sort($fys);
            $lastFy = end($fys);
        } else {
            $lastFy = $endC->month >= 7 ? $endC->year + 1 : $endC->year;
        }
        $aLabels = [];
        $aValues = [];
        for ($i = 2; $i >= 0; $i--) {
            $fy = $lastFy - $i;
            $aLabels[] = 'FY' . $fy;
            $aValues[] = round($byFy[$fy] ?? 0, 2);
        }

        return [
            'monthly' => ['labels' => $mLabels, 'values' => $mValues],
            'annual'  => ['labels' => $aLabels, 'values' => $aValues],
            'total'   => round($total, 2),
            'months'  => count($byMonth),
        ];
    }
}
