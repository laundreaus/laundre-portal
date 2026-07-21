<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;
class GuideController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = Guide::query()->orderByDesc('created_at');
        if (!$u->isAdmin()) { $q->where(fn($w)=>$w->where('visibility','all')->orWhere('visibility',(string)$u->location_id)); }
        return $q->get();
    }
    public function store(Request $r) { return Guide::create($this->rules($r)); }
    public function update(Request $r, Guide $guide) { $guide->update($this->rules($r)); return $guide; }
    public function destroy(Guide $guide) { $guide->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate(['title'=>'required|string','category'=>'nullable|string','visibility'=>'required|string','link'=>'nullable|string','file_path'=>'nullable|string','file_name'=>'nullable|string','note'=>'nullable|string']);
    }
}
