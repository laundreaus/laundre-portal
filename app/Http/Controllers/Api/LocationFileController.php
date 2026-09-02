<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\LocationFile;
use Illuminate\Http\Request;

class LocationFileController extends Controller {
    // Non-admins may only view insurance certificates for their own stores; admins see everything.
    public function index(Request $r) {
        $u = $r->user();
        $q = LocationFile::query()->orderByDesc('id');
        if ($r->filled('loc')) $q->where('location_id', (int) $r->query('loc'));
        if ($r->filled('category')) $q->where('category', $r->query('category'));
        if (!$u->isAdmin()) {
            $q->whereIn('location_id', $u->locationIds())->where('category', 'insurance');
        }
        return $q->get();
    }
    public function store(Request $r) { // admin only (route-gated)
        $d = $r->validate([
            'location_id'=>'required|exists:locations,id',
            'category'=>'required|in:lease,brochure,insurance,other',
            'name'=>'required|string',
            'file_path'=>'required|string',
            'file_name'=>'nullable|string',
            'size'=>'nullable|integer',
            'expiry'=>'nullable|date',
        ]);
        $d['uploaded_by'] = $r->user()->name;
        return LocationFile::create($d);
    }
    public function destroy(LocationFile $location_file) { // admin only
        $location_file->delete();
        return response()->noContent();
    }
}
