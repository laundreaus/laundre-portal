<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
class SaleController extends Controller {
    // Daily rows, scoped: admins see all; everyone else only their own store.
    public function index(Request $r) {
        $q = Sale::query()->with('location:id,name');
        $u = $r->user();
        if (!$u->isAdmin()) { $q->where('location_id', $u->location_id); }
        elseif ($r->filled('location_id')) { $q->where('location_id', $r->location_id); }
        if ($r->filled('from')) { $q->whereDate('date','>=',$r->from); }
        if ($r->filled('to'))   { $q->whereDate('date','<=',$r->to); }
        return $q->orderBy('date')->get(['id','location_id','date','revenue','txns']);
    }
    // Admin import of a month's aggregated daily rows for one store (client parses the CSV).
    public function import(Request $r) {
        $data = $r->validate([
            'location_id'=>'required|exists:locations,id',
            'rows'=>'required|array|min:1',
            'rows.*.date'=>'required|date_format:Y-m-d',
            'rows.*.revenue'=>'required|numeric',
            'rows.*.txns'=>'required|integer',
        ]);
        $months = collect($data['rows'])->map(fn($x)=>substr($x['date'],0,7))->unique()->values();
        // replace existing data for this store + these months
        Sale::where('location_id',$data['location_id'])
            ->where(function($q) use ($months){ foreach($months as $m){ $q->orWhere('date','like',$m.'%'); } })
            ->delete();
        foreach ($data['rows'] as $row) {
            Sale::create(['location_id'=>$data['location_id'],'date'=>$row['date'],'revenue'=>$row['revenue'],'txns'=>$row['txns']]);
        }
        return response()->json(['imported'=>count($data['rows']),'months'=>$months]);
    }
    public function destroyMonth(Request $r) {
        $r->validate(['location_id'=>'required','month'=>'required']);
        Sale::where('location_id',$r->location_id)->where('date','like',$r->month.'%')->delete();
        return response()->noContent();
    }
}
