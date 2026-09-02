<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\BookkeepingEntry;
use Illuminate\Http\Request;
class BookkeepingController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = BookkeepingEntry::query();
        if (!$u->isAdmin()) { $ids=$u->locationIds(); if($r->filled('loc')&&in_array((int)$r->query('loc'),$ids))$ids=[(int)$r->query('loc')]; $q->whereIn('location_id',$ids); }
        if ($r->filled('fy')) { $q->where('fy',$r->fy); }
        return $q->get();
    }
    public function upsert(Request $r) {
        $u = $r->user();
        $data = $r->validate(['location_id'=>'required|exists:locations,id','fy'=>'required|string','q1'=>'nullable|string','q2'=>'nullable|string','q3'=>'nullable|string','q4'=>'nullable|string','annual'=>'nullable|string','dates'=>'nullable|array','files'=>'nullable|array']);
        if (!$u->isAdmin() && !in_array((int)$data['location_id'], $u->locationIds())) abort(403);
        $entry = BookkeepingEntry::updateOrCreate(['location_id'=>$data['location_id'],'fy'=>$data['fy']], $data);
        if (!empty($data['files'])) {
            \App\Services\AdminNotifier::notify('accounts_uploaded',
                'BAS / accounts uploaded for '.(\App\Models\Location::find($data['location_id'])?->name ?? 'Laundromat').' (FY '.$data['fy'].') by '.$u->name,
                ['location'=>\App\Models\Location::find($data['location_id'])?->name,'fy'=>$data['fy'],'by'=>$u->name]);
        }
        return $entry;
    }
}
