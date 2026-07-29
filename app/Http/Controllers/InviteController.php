<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
class InviteController extends Controller {
    private function onboardType(User $u): string { return $u->role === 'investor' ? 'investor' : 'franchisee'; }

    public function show(string $token) {
        $u = User::where('invite_token', $token)->first();
        $path = public_path('legacy/laundre-welcome.html');
        $html = is_file($path) ? file_get_contents($path) : '<h1>Welcome</h1>';
        $valid = (bool) $u;
        $data = json_encode([
            'valid' => $valid,
            'token' => $token,
            'name'  => $u->name ?? '',
            'email' => $u->email ?? '',
            'kind'  => $u ? $this->onboardType($u) : '',
        ], JSON_UNESCAPED_SLASHES);
        $inject = '<script>window.LAUNDRE_INVITE='.$data.';window.LAUNDRE_CSRF='.json_encode(csrf_token()).';</script>';
        $html = str_ireplace('<head>', '<head>'.$inject, $html);
        return response($html, $valid ? 200 : 200)->header('Content-Type','text/html; charset=UTF-8');
    }

    public function store(Request $r, string $token) {
        $u = User::where('invite_token', $token)->first();
        if (!$u) return response()->json(['message'=>'This invite link is no longer valid. Please ask your administrator for a new one.'], 422);
        $data = $r->validate(['password'=>'required|string|min:8|confirmed']);
        $u->password = Hash::make($data['password']);
        $u->invite_token = null; // one-time use
        // Franchisee/investor prospects are on a 21-day access window from first access.
        if (in_array($u->role, ['potential_franchisee','investor']) && !$u->access_expires_at) {
            $u->access_expires_at = now()->addDays(21);
        }
        $u->save();
        if (in_array($u->role, ['potential_franchisee','investor'])) {
            $ob = Onboarding::firstOrCreate(['user_id'=>$u->id], ['type'=>$this->onboardType($u), 'crm_stage'=>'invited']);
            if (!$ob->first_login_at) { $ob->first_login_at = now(); $ob->save(); }
        }
        Auth::login($u);
        $r->session()->regenerate();
        return response()->json(['ok'=>true, 'redirect'=>'/']);
    }
}
