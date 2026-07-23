<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class UploadController extends Controller {
    public function store(Request $r) {
        $data = $r->validate(['data'=>'required|string','name'=>'nullable|string']);
        $raw = $data['data'];
        if (preg_match('/^data:[^;]+;base64,(.*)$/s', $raw, $m)) { $raw = $m[1]; }
        $bytes = base64_decode($raw, true);
        if ($bytes === false) abort(422, 'Invalid file data');
        if (strlen($bytes) > 12 * 1024 * 1024) abort(422, 'File too large (max 12 MB)');
        $orig = $data['name'] ?? 'file';
        $ext = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($orig, PATHINFO_EXTENSION));
        $safe = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        $dir = public_path('uploads');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $safe, $bytes);
        return response()->json(['url'=>'/uploads/'.$safe, 'name'=>$orig, 'size'=>strlen($bytes)]);
    }
}
