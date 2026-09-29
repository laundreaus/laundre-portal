<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\Request;
class MachineController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = Machine::query()->orderBy('name');
        if (!$u->isAdmin()) {
            $ids = $u->locationIds();
            if ($r->filled('loc') && in_array((int)$r->query('loc'), $ids)) $ids = [(int)$r->query('loc')];
            $q->whereIn('location_id', $ids);
        } elseif ($r->filled('loc')) {
            $q->where('location_id', (int)$r->query('loc'));
        }
        return $q->get();
    }
    public function store(Request $r) { return Machine::create($this->rules($r) + ['source'=>'manual']); }
    public function update(Request $r, Machine $machine) { $machine->update($this->rules($r)); return $machine; }
    public function destroy(Machine $machine) { $machine->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate(['location_id'=>'required|exists:locations,id','name'=>'nullable|string','type'=>'nullable|string','model'=>'nullable|string','serial'=>'nullable|string','status'=>'nullable|string']);
    }
}
