<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\CostProject;
use Illuminate\Http\Request;
class CostProjectController extends Controller {
    public function index() { return CostProject::orderBy('name')->get(); }
    public function store(Request $r) { return CostProject::create($this->rules($r)); }
    public function update(Request $r, CostProject $cost_project) { $cost_project->update($this->rules($r)); return $cost_project; }
    public function destroy(CostProject $cost_project) { $cost_project->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate(['name'=>'required|string','location'=>'nullable|string','sqm'=>'nullable|integer','margin_pct'=>'nullable|numeric','gst_pct'=>'nullable|numeric','items'=>'nullable|array']);
    }
}
