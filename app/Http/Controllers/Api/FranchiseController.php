<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Franchise;
use Illuminate\Http\Request;
class FranchiseController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = Franchise::query();
        if (!$u->isAdmin()) { $q->whereIn('source_location_id',$u->locationIds()); }
        return $q->orderByDesc('created_at')->get();
    }
    public function store(Request $r) { abort_unless($r->user()->isAdmin(),403); return Franchise::create($this->rules($r)); }
    public function update(Request $r, Franchise $franchise) {
        $u = $r->user();
        if (!$u->isAdmin() && !in_array($franchise->source_location_id, $u->locationIds())) abort(403);
        $franchise->update($this->rules($r)); return $franchise;
    }
    public function destroy(Request $r, Franchise $franchise) { abort_unless($r->user()->isAdmin(),403); $franchise->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate(['name'=>'required|string','location'=>'nullable|string','contact'=>'nullable|string','source_location_id'=>'nullable|exists:locations,id','sections'=>'nullable|array']);
    }
}
