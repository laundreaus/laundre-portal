<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;
class MaintenanceController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = MaintenanceLog::query()->orderByDesc('date');
        if (!$u->isAdmin()) {
            $ids = $u->locationIds();
            if ($r->filled('loc') && in_array((int)$r->query('loc'), $ids)) $ids = [(int)$r->query('loc')];
            $q->whereIn('location_id', $ids);
        }
        return $q->get();
    }
    public function submit(Request $r) {
        $u = $r->user();
        $data = $r->validate(['date'=>'required|date_format:Y-m-d','location_id'=>'nullable|integer','items'=>'array','notes'=>'nullable|string','issues'=>'nullable|string','photos'=>'array','geo'=>'nullable|array']);
        $loc = (int)($data['location_id'] ?? 0);
        if (!$loc || !in_array($loc, $u->locationIds())) $loc = (int)$u->location_id;
        abort_if(!$loc, 422, 'No store assigned');
        $log = MaintenanceLog::updateOrCreate(
            ['location_id'=>$loc,'date'=>$data['date']],
            ['user_id'=>$u->id,'by'=>$u->name,'items'=>$data['items']??[], 'notes'=>$data['notes']??null,'issues'=>$data['issues']??null,'photos'=>$data['photos']??[],'geo'=>$data['geo']??null]
        );
        if ($log->wasRecentlyCreated) {
            \App\Services\AdminNotifier::notify('maintenance_completed',
                'Maintenance completed at '.(\App\Models\Location::find($loc)?->name ?? 'Laundromat').' by '.$u->name,
                ['location'=>\App\Models\Location::find($loc)?->name,'by'=>$u->name,'date'=>$data['date'],'issues'=>$data['issues']??null]);
        }
        return $log;
    }
}
