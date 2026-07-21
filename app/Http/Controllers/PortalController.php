<?php
namespace App\Http\Controllers;
class PortalController extends Controller {
    public function index() { return view('portal'); }
    // Serves the transitional static tool pages behind auth.
    public function legacy(string $file) {
        $path = public_path('legacy/'.basename($file));
        abort_unless(is_file($path) && str_ends_with($file, '.html'), 404);
        return response()->file($path);
    }
}
