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
        if (!$u->isAdmin()) {
            $ids = $u->locationIds();
            if ($r->filled('loc') && in_array((int)$r->query('loc'), $ids)) $ids = [(int)$r->query('loc')];
            $q->whereIn('location_id', $ids);
        }
        return $q->get();
    }
    public function submit(Request $r) {
        $u = $r->user();
        $data = $r->validate(['date'=>'required|date_format:Y-m-d','location_id'=>'nullable|integer','items'=>'array','labels'=>'array','notes'=>'nullable|string','issues'=>'nullable|string','photos'=>'array','geo'=>'nullable|array']);
        // Multi-site staff submit for the active store (must be one of theirs); otherwise their primary store.
        $loc = (int)($data['location_id'] ?? 0);
        if (!$loc || !in_array($loc, $u->locationIds())) $loc = (int)$u->location_id;
        abort_if(!$loc, 422, 'No store assigned');
        $log = CleaningLog::updateOrCreate(
            ['location_id'=>$loc,'date'=>$data['date']],
            ['user_id'=>$u->id,'by'=>$u->name,'items'=>$data['items']??[], 'labels'=>$data['labels']??[], 'notes'=>$data['notes']??null,'issues'=>$data['issues']??null,'photos'=>$data['photos']??[],'geo'=>$data['geo']??null]
        );
        if ($log->wasRecentlyCreated) {
            \App\Services\AdminNotifier::notify('laundromat_cleaned',
                (\App\Models\Location::find($loc)?->name ?? 'Laundromat').' cleaned by '.$u->name,
                ['location'=>\App\Models\Location::find($loc)?->name,'by'=>$u->name,'date'=>$data['date'],'issues'=>$data['issues']??null]);
        }
        return $log;
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
