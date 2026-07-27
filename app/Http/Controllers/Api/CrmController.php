<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PipelineCard;
use App\Models\Setting;
use Illuminate\Http\Request;
class CrmController extends Controller {
    // The first two columns are fixed/auto-driven; the admin builds the rest.
    public const LOCKED = [
        ['id'=>'nda_sent','name'=>'NDA sent','color'=>'#8a9790','locked'=>true],
        ['id'=>'reviewing_documents','name'=>'Reviewing Documents','color'=>'#6b8e9e','locked'=>true],
    ];
    private function columns(): array {
        $cols = Setting::get('pipeline_columns', null);
        if (!is_array($cols) || !count($cols)) { $cols = self::LOCKED; Setting::put('pipeline_columns', $cols); }
        // guarantee the two locked columns are present and first, in order
        $ids = array_column($cols, 'id');
        $out = [];
        foreach (self::LOCKED as $lc) { $out[] = $lc; }
        foreach ($cols as $c) { if (!in_array($c['id'], ['nda_sent','reviewing_documents'])) { $c['locked'] = false; $out[] = $c; } }
        return $out;
    }
    public function index() {
        return response()->json(['columns'=>$this->columns(), 'cards'=>PipelineCard::orderBy('position')->orderBy('id')->get()]);
    }
    public function storeCard(Request $r) {
        $d = $this->rules($r);
        $d['position'] = (int) (PipelineCard::where('stage',$d['stage'])->max('position') + 1);
        return PipelineCard::create($d);
    }
    public function updateCard(Request $r, PipelineCard $card) {
        $card->update($this->rules($r));
        return $card;
    }
    public function moveCard(Request $r, PipelineCard $card) {
        $d = $r->validate(['stage'=>'required|string','position'=>'nullable|integer']);
        $card->stage = $d['stage'];
        if (isset($d['position'])) $card->position = $d['position'];
        $card->save();
        return $card;
    }
    public function destroyCard(PipelineCard $card) { $card->delete(); return response()->noContent(); }
    public function saveColumns(Request $r) {
        $d = $r->validate(['columns'=>'required|array','columns.*.id'=>'required|string','columns.*.name'=>'required|string','columns.*.color'=>'nullable|string']);
        // always keep the two locked columns at the front
        $custom = array_values(array_filter($d['columns'], fn($c)=>!in_array($c['id'],['nda_sent','reviewing_documents'])));
        $cols = array_merge(self::LOCKED, array_map(fn($c)=>['id'=>$c['id'],'name'=>$c['name'],'color'=>$c['color']??'#6E8B7B','locked'=>false], $custom));
        Setting::put('pipeline_columns', $cols);
        return response()->json(['columns'=>$cols]);
    }
    private function rules(Request $r): array {
        return $r->validate([
            'name'=>'required|string','contact'=>'nullable|string','email'=>'nullable|string',
            'phone'=>'nullable|string','city'=>'nullable|string','notes'=>'nullable|string','stage'=>'required|string',
        ]);
    }
}
