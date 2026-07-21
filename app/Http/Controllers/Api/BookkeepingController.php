<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\BookkeepingEntry;
use Illuminate\Http\Request;
class BookkeepingController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = BookkeepingEntry::query();
        if (!$u->isAdmin()) { $q->where('location_id',$u->location_id); }
        if ($r->filled('fy')) { $q->where('fy',$r->fy); }
        return $q->get();
    }
    public function upsert(Request $r) {
        $u = $r->user();
        $data = $r->validate(['location_id'=>'required|exists:locations,id','fy'=>'required|string','q1'=>'nullable|string','q2'=>'nullable|string','q3'=>'nullable|string','q4'=>'nullable|string','annual'=>'nullable|string','dates'=>'nullable|array','files'=>'nullable|array']);
        if (!$u->isAdmin() && (int)$data['location_id'] !== (int)$u->location_id) abort(403);
        return BookkeepingEntry::updateOrCreate(['location_id'=>$data['location_id'],'fy'=>$data['fy']], $data);
    }
}
