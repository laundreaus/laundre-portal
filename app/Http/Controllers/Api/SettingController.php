<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
class SettingController extends Controller {
    public function show(string $key) { return response()->json(Setting::get($key)); }
    public function put(Request $r, string $key) { abort_unless($r->user()->isAdmin(),403); Setting::put($key, $r->input('value')); return response()->json(Setting::get($key)); }
}
