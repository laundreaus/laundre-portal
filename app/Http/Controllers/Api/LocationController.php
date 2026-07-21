<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
class LocationController extends Controller {
    public function index() { return Location::orderBy('name')->get(); }
    public function store(Request $r) {
        $data = $this->validated($r);
        return Location::create($data);
    }
    public function update(Request $r, Location $location) {
        $location->update($this->validated($r));
        return $location;
    }
    public function destroy(Location $location) { $location->delete(); return response()->noContent(); }
    private function validated(Request $r): array {
        return $r->validate([
            'name'=>'required|string','address'=>'nullable|string',
            'lat'=>'nullable|numeric','lng'=>'nullable|numeric',
            'radius'=>'nullable|numeric','unit'=>'nullable|in:km,m',
            'status'=>'nullable|in:active,inactive','date_approved'=>'nullable|date','notes'=>'nullable|string',
        ]);
    }
}
