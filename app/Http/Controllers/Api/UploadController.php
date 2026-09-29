<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class UploadController extends Controller {
    public function store(Request $r) {
        $dir = public_path('uploads');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // Preferred path: a real multipart file upload. This is NOT constrained by
        // mod_security's small non-file request-body limit, so large files (PDFs,
        // brochures) go through where a base64 JSON body would be blocked.
        if ($r->hasFile('file')) {
            $file = $r->file('file');
            if (!$file->isValid()) abort(422, 'Upload failed — please try again.');
            if ($file->getSize() > 12 * 1024 * 1024) abort(422, 'File too large (max 12 MB)');
            $orig = $file->getClientOriginalName() ?: 'file';
            $ext = preg_replace('/[^A-Za-z0-9]/', '', $file->getClientOriginalExtension() ?: pathinfo($orig, PATHINFO_EXTENSION));
            $safe = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
            $file->move($dir, $safe);
            return response()->json(['url'=>'/uploads/'.$safe, 'name'=>$orig, 'size'=>filesize($dir.'/'.$safe)]);
        }

        // Legacy path: base64 data URL inside a JSON body (kept for existing callers;
        // only reliable for small files below the server's non-file body limit).
        $data = $r->validate(['data'=>'required|string','name'=>'nullable|string']);
        $raw = $data['data'];
        if (preg_match('/^data:[^;]+;base64,(.*)$/s', $raw, $m)) { $raw = $m[1]; }
        $bytes = base64_decode($raw, true);
        if ($bytes === false) abort(422, 'Invalid file data');
        if (strlen($bytes) > 12 * 1024 * 1024) abort(422, 'File too large (max 12 MB)');
        $orig = $data['name'] ?? 'file';
        $ext = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($orig, PATHINFO_EXTENSION));
        $safe = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        file_put_contents($dir . '/' . $safe, $bytes);
        return response()->json(['url'=>'/uploads/'.$safe, 'name'=>$orig, 'size'=>strlen($bytes)]);
    }
}
