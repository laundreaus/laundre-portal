<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
class ExpenseController extends Controller {
    private function scopeIds(Request $r) {
        $u = $r->user();
        if ($u->isAdmin()) return $r->filled('loc') ? [(int)$r->query('loc')] : null;
        $ids = $u->locationIds();
        if ($r->filled('loc') && in_array((int)$r->query('loc'), $ids)) $ids = [(int)$r->query('loc')];
        return $ids;
    }
    public function index(Request $r) {
        $q = Expense::query()->orderBy('month');
        $ids = $this->scopeIds($r);
        if ($ids !== null) $q->whereIn('location_id', $ids);
        return $q->get();
    }
    public function upsert(Request $r) {
        $u = $r->user();
        $d = $r->validate(['id'=>'nullable|integer','location_id'=>'required|integer','month'=>'required|string','category'=>'nullable|string','amount'=>'required|numeric','note'=>'nullable|string']);
        if (!$u->isAdmin() && !in_array((int)$d['location_id'], $u->locationIds())) abort(403);
        $e = !empty($d['id']) ? Expense::findOrFail($d['id']) : new Expense();
        $e->fill(['location_id'=>$d['location_id'],'month'=>$d['month'],'category'=>$d['category']??null,'amount'=>$d['amount'],'note'=>$d['note']??null,'source'=>'manual']);
        $e->save();
        return $e;
    }
    public function destroy(Request $r, Expense $expense) {
        $u = $r->user();
        if (!$u->isAdmin() && !in_array((int)$expense->location_id, $u->locationIds())) abort(403);
        $expense->delete();
        return response()->noContent();
    }
}
