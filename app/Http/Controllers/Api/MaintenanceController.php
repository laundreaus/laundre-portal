<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;
class MaintenanceController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = MaintenanceLog::query()->orderByDesc('date');
        if (!$u->isAdmin()) { $q->where('location_id',$u->location_id); }
        return $q->get();
    }
    public function submit(Request $r) {
        $u = $r->user();
        abort_if(!$u->location_id, 422, 'No store assigned');
        $data = $r->validate(['date'=>'required|date_format:Y-m-d','items'=>'array','notes'=>'nullable|string','issues'=>'nullable|string','photos'=>'array']);
        return MaintenanceLog::updateOrCreate(
            ['location_id'=>$u->location_id,'date'=>$data['date']],
            ['user_id'=>$u->id,'by'=>$u->name,'items'=>$data['items']??[], 'notes'=>$data['notes']??null,'issues'=>$data['issues']??null,'photos'=>$data['photos']??[]]
        );
    }
}
