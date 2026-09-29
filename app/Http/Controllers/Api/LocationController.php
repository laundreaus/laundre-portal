<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class LocationController extends Controller {
    public function index() { return Location::orderBy('name')->get(); }
    public function store(Request $r) {
        $data = $this->validated($r);
        // Geocode from the address so the new store lands on the postcode/radius map straight away.
        if ((empty($data['lat']) || empty($data['lng'])) && !empty($data['address'])) {
            $c = $this->geocode($data['address']);
            if ($c) { $data['lat'] = $c['lat']; $data['lng'] = $c['lng']; }
        }
        $loc = Location::create($data);
        // A newly created laundromat is now live — notify admins of the status change.
        \App\Services\AdminNotifier::adminAlert('laundromat_status', 'Laundromat is now live: '.$loc->name,
            $loc->name.' has moved into Live Laundromats.',
            [['Laundromat', $loc->name], ['Address', $loc->address ?: '—'], ['Status', $loc->status ?: 'active']]);
        return $loc;
    }
    public function update(Request $r, Location $location) {
        $data = $this->validated($r);
        if ((empty($data['lat']) || empty($data['lng'])) && !empty($data['address'])) {
            $c = $this->geocode($data['address']);
            if ($c) { $data['lat'] = $c['lat']; $data['lng'] = $c['lng']; }
        }
        $wasStatus = $location->status;
        $location->update($data);
        if (array_key_exists('status', $data) && $data['status'] !== $wasStatus) {
            \App\Services\AdminNotifier::adminAlert('laundromat_status', 'Laundromat status changed: '.$location->name,
                $location->name.' changed from '.($wasStatus ?: '—').' to '.($location->status ?: '—').'.',
                [['Laundromat', $location->name], ['New status', $location->status ?: '—']]);
        }
        return $location;
    }
    public function destroy(Location $location) { $location->delete(); return response()->noContent(); }

    // Backfill coordinates for any location missing them. Falls back to the town name
    // (stripping the "Laundré" prefix) when there is no street address — enough for the weather overlay.
    public function backfillGeocode(): int {
        $n = 0;
        foreach (Location::whereNull('lat')->orWhereNull('lng')->get() as $loc) {
            $q = $loc->address ?: (trim(preg_replace('/^laundr[eé]\s*/i', '', $loc->name)) . ' QLD Australia');
            if (!trim($q)) continue;
            $c = $this->geocode($q);
            if ($c) { $loc->lat = $c['lat']; $loc->lng = $c['lng']; $loc->save(); $n++; usleep(1100000); }
        }
        return $n;
    }

    private function geocode(string $address): ?array {
        try {
            $res = Http::withHeaders(['User-Agent' => 'LaundreFranchisePortal/1.0 (admin@laundre.com.au)'])
                ->timeout(12)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'au',
                ]);
            if ($res->ok()) {
                $j = $res->json();
                if (is_array($j) && count($j) && isset($j[0]['lat'], $j[0]['lon'])) {
                    return ['lat' => (float) $j[0]['lat'], 'lng' => (float) $j[0]['lon']];
                }
            }
        } catch (\Throwable $e) { /* leave coords null; admin can set them manually */ }
        return null;
    }

    private function validated(Request $r): array {
        return $r->validate([
            'name'=>'required|string','address'=>'nullable|string',
            'lat'=>'nullable|numeric','lng'=>'nullable|numeric',
            'radius'=>'nullable|numeric','unit'=>'nullable|in:km,m',
            'status'=>'nullable|in:active,inactive','date_approved'=>'nullable|date','notes'=>'nullable|string',
            'keys_date'=>'nullable|date','opening_date'=>'nullable|date',
            'about'=>'nullable|array','modules'=>'nullable|array',
        ]);
    }
}
