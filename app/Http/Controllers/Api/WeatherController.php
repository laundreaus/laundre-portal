<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

/**
 * Daily weather for a laundromat's location (Open-Meteo — free, no API key).
 * Returns { date, tmax, tmin, precip } per day so the dashboard can overlay it on revenue.
 */
class WeatherController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $loc = (int) $r->query('loc');
        abort_if(!$loc, 422, 'No laundromat');
        abort_unless($u->isAdmin() || in_array($loc, $u->locationIds()), 403);

        $location = Location::find($loc);
        if (!$location || $location->lat === null || $location->lng === null) {
            return response()->json(['ok'=>false, 'reason'=>'no_coords', 'days'=>[]]);
        }

        $tz = 'Australia/Brisbane';
        $to = $r->filled('to') ? Carbon::parse($r->query('to'), $tz) : Carbon::now($tz);
        $from = $r->filled('from') ? Carbon::parse($r->query('from'), $tz) : $to->copy()->subDays(30);
        $pastDays = min(92, max(1, $to->copy()->startOfDay()->diffInDays(Carbon::now($tz)->startOfDay()) + $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1));

        $key = "weather_{$location->lat}_{$location->lng}_{$from->toDateString()}_{$to->toDateString()}";
        $days = Cache::remember($key, 3600, function () use ($location, $pastDays) {
            try {
                $resp = Http::timeout(12)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $location->lat, 'longitude' => $location->lng,
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
                    'timezone' => 'auto', 'past_days' => $pastDays, 'forecast_days' => 1,
                ]);
                if (!$resp->successful()) return [];
                $d = $resp->json('daily') ?? [];
                $out = [];
                foreach (($d['time'] ?? []) as $i => $date) {
                    $out[] = [
                        'date'   => $date,
                        'tmax'   => $d['temperature_2m_max'][$i] ?? null,
                        'tmin'   => $d['temperature_2m_min'][$i] ?? null,
                        'precip' => $d['precipitation_sum'][$i] ?? null,
                    ];
                }
                return $out;
            } catch (\Throwable $e) { return []; }
        });

        $f = $from->toDateString(); $t = $to->toDateString();
        $days = array_values(array_filter($days, fn ($x) => $x['date'] >= $f && $x['date'] <= $t));
        return response()->json(['ok'=>true, 'days'=>$days]);
    }
}
