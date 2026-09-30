<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller {
    // ?loc=ID -> that laundromat's customers; ?global=1 -> global admin contacts (admin only).
    public function index(Request $r) {
        $u = $r->user();
        $q = Contact::query()->orderBy('name');
        if ($r->boolean('global')) {
            abort_unless($u->isAdmin(), 403);
            $q->whereNull('location_id');
        } elseif ($r->filled('loc')) {
            $loc = (int) $r->query('loc');
            abort_unless($u->isAdmin() || in_array($loc, $u->locationIds()), 403);
            $q->where('location_id', $loc);
        } else {
            // default: everything the user may see
            if (!$u->isAdmin()) $q->whereIn('location_id', $u->locationIds());
        }
        if ($r->filled('q')) {
            $t = $r->query('q');
            $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('company', 'like', "%$t%")
                ->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")->orWhere('notes', 'like', "%$t%"));
        }
        if ($r->filled('category')) $q->where('category', $r->query('category'));
        return $q->limit((int) ($r->query('limit') ?: 500))->get();
    }
    public function store(Request $r) {
        $u = $r->user();
        $d = $this->rules($r);
        $this->authorizeScope($u, $d['location_id'] ?? null);
        $d['created_by'] = $u->name;
        return Contact::create($d);
    }
    public function update(Request $r, Contact $contact) {
        $this->authorizeScope($r->user(), $contact->location_id);
        $contact->update($this->rules($r));
        return $contact;
    }
    public function destroy(Request $r, Contact $contact) {
        $this->authorizeScope($r->user(), $contact->location_id);
        $contact->delete();
        return response()->noContent();
    }
    // Bulk-create customers for a store from a parsed CSV (rows of name/email/phone/notes).
    public function bulkImport(Request $r) {
        $u = $r->user();
        $d = $r->validate([
            'location_id'=>'required|integer',
            'rows'=>'required|array|max:5000',
            'rows.*.name'=>'nullable|string',
            'rows.*.email'=>'nullable|string',
            'rows.*.phone'=>'nullable|string',
            'rows.*.notes'=>'nullable|string',
        ]);
        $loc = (int) $d['location_id'];
        $this->authorizeScope($u, $loc);
        $created = 0;
        foreach ($d['rows'] as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') continue;
            Contact::create([
                'location_id' => $loc,
                'name'        => $name,
                'email'       => trim((string)($row['email'] ?? '')) ?: null,
                'phone'       => trim((string)($row['phone'] ?? '')) ?: null,
                'notes'       => trim((string)($row['notes'] ?? '')) ?: null,
                'category'    => 'customer',
                'source'      => 'csv-import',
                'created_by'  => $u->name,
            ]);
            $created++;
        }
        return response()->json(['ok'=>true, 'created'=>$created]);
    }
    private function authorizeScope($u, $locationId): void {
        if ($u->isAdmin()) return;
        if ($locationId === null || !in_array((int) $locationId, $u->locationIds())) abort(403);
    }
    private function rules(Request $r): array {
        return $r->validate([
            'location_id'=>'nullable|integer',
            'name'=>'required|string',
            'company'=>'nullable|string','title'=>'nullable|string',
            'email'=>'nullable|string','phone'=>'nullable|string',
            'category'=>'nullable|string','notes'=>'nullable|string',
        ]);
    }
}
