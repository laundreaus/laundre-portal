<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\Request;

class MilestoneController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $loc = (int) $r->query('loc');
        if ($loc) abort_unless($u->isAdmin() || in_array($loc, $u->locationIds()), 403);
        $q = Milestone::query()->orderBy('position')->orderBy('target_date');
        if ($loc) $q->where('location_id', $loc);
        elseif (!$u->isAdmin()) $q->whereIn('location_id', $u->locationIds());
        return $q->get();
    }
    public function store(Request $r) { // admin (route-gated)
        $d = $this->rules($r);
        $d['position'] = (int) (Milestone::where('location_id', $d['location_id'])->max('position') + 1);
        return Milestone::create($d);
    }
    public function update(Request $r, Milestone $milestone) { $milestone->update($this->rules($r)); return $milestone; }
    public function destroy(Milestone $milestone) { $milestone->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate([
            'location_id'=>'required|exists:locations,id',
            'title'=>'required|string','detail'=>'nullable|string',
            'target_date'=>'nullable|date','status'=>'nullable|in:pending,in_progress,done','position'=>'nullable|integer',
        ]);
    }
}
