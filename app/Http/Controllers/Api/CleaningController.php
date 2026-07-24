<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\CleaningLog;
use App\Models\Setting;
use Illuminate\Http\Request;
class CleaningController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = CleaningLog::query()->orderByDesc('date');
        if (!$u->isAdmin()) { $q->where('location_id',$u->location_id); }
        return $q->get();
    }
    public function submit(Request $r) {
        $u = $r->user();
        abort_if(!$u->location_id, 422, 'No store assigned');
        $data = $r->validate(['date'=>'required|date_format:Y-m-d','items'=>'array','labels'=>'array','notes'=>'nullable|string','issues'=>'nullable|string','photos'=>'array','geo'=>'nullable|array']);
        return CleaningLog::updateOrCreate(
            ['location_id'=>$u->location_id,'date'=>$data['date']],
            ['user_id'=>$u->id,'by'=>$u->name,'items'=>$data['items']??[], 'labels'=>$data['labels']??[], 'notes'=>$data['notes']??null,'issues'=>$data['issues']??null,'photos'=>$data['photos']??[],'geo'=>$data['geo']??null]
        );
    }
    public function items() { return response()->json(Setting::get('cleaning_items', ['Bins emptied','Lint traps emptied','Floors mopped','Surfaces wiped','Machines & benches wiped'])); }
    public function saveItems(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $items = $r->validate(['items'=>'required|array|min:1'])['items'];
        Setting::put('cleaning_items', array_values($items));
        return response()->json($items);
    }
}
