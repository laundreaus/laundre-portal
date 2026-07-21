<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
class SupplierController extends Controller {
    public function index() { return Supplier::orderBy('category')->orderBy('name')->get(); }
    public function store(Request $r) { return Supplier::create($this->rules($r)); }
    public function update(Request $r, Supplier $supplier) { $supplier->update($this->rules($r)); return $supplier; }
    public function destroy(Supplier $supplier) { $supplier->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate(['name'=>'required|string','category'=>'nullable|string','contact'=>'nullable|string','phone'=>'nullable|string','email'=>'nullable|string','website'=>'nullable|string','notes'=>'nullable|string','locations'=>'nullable|array']);
    }
}
