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
    private const DEFAULT_ITEMS = ['Bins emptied','Lint traps emptied','Floors mopped','Surfaces wiped','Machines & benches wiped'];
    // Per-laundromat cleaning checklist. Each store starts from the shared template and can be customised.
    public function items(Request $r) {
        $u = $r->user();
        $loc = $r->query('loc');
        if (!$loc && !$u->isAdmin()) $loc = $u->location_id;
        if ($loc) {
            $items = Setting::get('cleaning_items_'.$loc, null);
            if (is_array($items) && count($items)) return response()->json(array_values($items));
        }
        // Fall back to the shared template (starting structure).
        return response()->json(array_values(Setting::get('cleaning_items', self::DEFAULT_ITEMS)));
    }
    public function saveItems(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $data = $r->validate(['items'=>'required|array|min:1','loc'=>'nullable']);
        $key = !empty($data['loc']) ? ('cleaning_items_'.$data['loc']) : 'cleaning_items';
        Setting::put($key, array_values($data['items']));
        return response()->json($data['items']);
    }
}
