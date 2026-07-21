<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function showLogin() {
        if (Auth::check()) return redirect('/');
        return view('auth.login');
    }
    public function login(Request $request) {
        $data = $request->validate(['email' => 'required|string', 'password' => 'required|string']);
        // allow logging in with the "email" field even if it holds a username
        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }
        throw ValidationException::withMessages(['email' => 'Incorrect email or password.']);
    }
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
    public function me(Request $request) {
        $u = $request->user();
        return response()->json(['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'role'=>$u->role,'location_id'=>$u->location_id]);
    }
}
