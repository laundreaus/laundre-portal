<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\SiteProspect;
use Illuminate\Http\Request;

class SiteProspectController extends Controller {
    // Fixed acquisition pipeline stages.
    public const STAGES = [
        ['id'=>'prospect',      'name'=>'Prospect',           'color'=>'#8a9790'],
        ['id'=>'offer_received','name'=>'Offer received',     'color'=>'#6b8e9e'],
        ['id'=>'counter_offer', 'name'=>'Counter offer sent', 'color'=>'#C4703F'],
        ['id'=>'passed',        'name'=>'Passed on',          'color'=>'#B4472F'],
        ['id'=>'accepted',      'name'=>'Accepted',           'color'=>'#2f6b46'],
        ['id'=>'in_design',     'name'=>'In design',          'color'=>'#6b4d7a'],
    ];

    public function index() {
        return response()->json([
            'stages' => self::STAGES,
            'prospects' => SiteProspect::orderBy('position')->orderBy('id')->get(),
        ]);
    }
    public function store(Request $r) {
        $d = $this->rules($r);
        $d['position'] = (int) (SiteProspect::where('stage', $d['stage'] ?? 'prospect')->max('position') + 1);
        return SiteProspect::create($d);
    }
    public function update(Request $r, SiteProspect $site_prospect) {
        $site_prospect->update($this->rules($r));
        return $site_prospect;
    }
    public function move(Request $r, SiteProspect $site_prospect) {
        $d = $r->validate(['stage'=>'required|string','position'=>'nullable|integer']);
        $site_prospect->stage = $d['stage'];
        if (isset($d['position'])) $site_prospect->position = $d['position'];
        $site_prospect->save();
        return $site_prospect;
    }
    public function destroy(SiteProspect $site_prospect) {
        $site_prospect->delete();
        return response()->noContent();
    }
    private function rules(Request $r): array {
        $v = $r->validate([
            'name'=>'required|string','stage'=>'nullable|string','target'=>'nullable|string','amount'=>'nullable|string',
            'notes'=>'nullable|string','agent_name'=>'nullable|string','agent_email'=>'nullable|string','agent_phone'=>'nullable|string',
            'centre_name'=>'nullable|string','centre_email'=>'nullable|string','centre_phone'=>'nullable|string',
            'lat'=>'nullable|numeric','lng'=>'nullable|numeric',
        ]);
        if (empty($v['stage'])) $v['stage'] = 'prospect';
        return $v;
    }
}
