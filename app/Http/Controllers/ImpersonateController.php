<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    // Admin starts a read-only "view as user" preview.
    public function start(Request $request, User $user)
    {
        $admin = $request->user();
        abort_unless($admin && $admin->isAdmin(), 403);
        abort_if($user->isAdmin(), 422, 'Cannot preview as another admin.');
        $request->session()->put('impersonate_id', $user->id);
        return response()->json(['ok' => true, 'name' => $user->name, 'role' => $user->role]);
    }

    // Exit the preview and return to the admin's own portal.
    public function stop(Request $request)
    {
        $request->session()->forget('impersonate_id');
        return redirect('/');
    }
}
