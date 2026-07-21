<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\SiteScore;
use Illuminate\Http\Request;
class SiteScoreController extends Controller {
    public function index() { return SiteScore::orderByDesc('overall')->get(); }
    public function store(Request $r) { return SiteScore::create($this->rules($r)); }
    public function update(Request $r, SiteScore $site_score) { $site_score->update($this->rules($r)); return $site_score; }
    public function destroy(SiteScore $site_score) { $site_score->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate(['name'=>'required|string','address'=>'nullable|string','suburb'=>'nullable|string','status'=>'nullable|string','sqm'=>'nullable|integer','rent'=>'nullable|integer','parking'=>'nullable|integer','pop'=>'nullable|integer','notes'=>'nullable|string','scores'=>'nullable|array','overall'=>'nullable|numeric','attachments'=>'nullable|array']);
    }
}
