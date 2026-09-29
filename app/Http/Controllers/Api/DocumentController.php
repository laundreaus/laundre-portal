<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
class DocumentController extends Controller {
    public function index(Request $r) {
        $u = $r->user();
        $q = Document::query()->orderByDesc('created_at');
        if (!$u->isAdmin()) { $q->where(fn($w)=>$w->where('visibility','all')->orWhere('visibility',(string)$u->location_id)); }
        return $q->get();
    }
    public function store(Request $r) { return Document::create($this->rules($r)); }
    public function update(Request $r, Document $document) { $document->update($this->rules($r)); return $document; }
    public function destroy(Document $document) { $document->delete(); return response()->noContent(); }

    // Stream a document inline (used by the secure viewer for view-only/protected docs).
    public function open(Request $r, Document $document) {
        $u = $r->user();
        $allowed = $u->isAdmin() || $document->visibility === 'all' || (string)$document->visibility === (string)$u->location_id;
        abort_unless($allowed, 403);
        abort_unless($document->file_path, 404);
        $path = public_path(ltrim($document->file_path, '/'));
        abort_unless(is_file($path), 404);
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/pdf') : 'application/pdf';
        $headers = ['Content-Type'=>$mime, 'Content-Disposition'=>'inline; filename="'.addslashes($document->file_name ?: basename($path)).'"'];
        if ($document->protected) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, private';
            $headers['Pragma'] = 'no-cache';
            $headers['X-Content-Type-Options'] = 'nosniff';
        }
        return response()->file($path, $headers);
    }

    private function rules(Request $r): array {
        $d = $r->validate(['title'=>'required|string','category'=>'nullable|string','visibility'=>'required|string','link'=>'nullable|string','file_path'=>'nullable|string','file_name'=>'nullable|string','note'=>'nullable|string','protected'=>'nullable|boolean']);
        $d['protected'] = (bool)($d['protected'] ?? false);
        return $d;
    }
}
