<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceDoc;
use Illuminate\Http\Request;
class MaintenanceDocController extends Controller {
    public function index() { return MaintenanceDoc::orderByDesc('created_at')->get(); }
    public function store(Request $r) { return MaintenanceDoc::create($this->rules($r)); }
    public function destroy(MaintenanceDoc $maintenance_doc) { $maintenance_doc->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate([
            'title'=>'required|string','category'=>'nullable|string','machine'=>'nullable|string',
            'note'=>'nullable|string','file_path'=>'nullable|string','file_name'=>'nullable|string','file_size'=>'nullable|integer',
        ]);
    }
}
