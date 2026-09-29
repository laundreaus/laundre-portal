<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "view as user" preview. When an admin has an impersonation target set in
 * their session, the effective authenticated user becomes that target for the
 * request — so the whole portal (pages + scoped read APIs) renders exactly as the
 * target sees it. The preview is strictly read-only: any state-changing request is
 * blocked. Impersonation control routes (start/stop) always run as the real admin.
 */
class Impersonate
{
    public function handle(Request $request, Closure $next)
    {
        $real = $request->user();
        $id = $request->session()->get('impersonate_id');
        $isControl = $request->is('impersonate/*') || $request->is('stop-impersonate');

        if ($real && $real->isAdmin() && $id && !$isControl) {
            $target = User::find($id);
            if ($target && !$target->isAdmin()) {
                // Read-only guard — never mutate data while previewing as someone else.
                if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                    abort(403, 'Read-only preview — exit “View as user” to make changes.');
                }
                $request->attributes->set('impersonator_id', $real->id);
                Auth::setUser($target);
            } else {
                // Stale or invalid target — clear it.
                $request->session()->forget('impersonate_id');
            }
        }

        return $next($request);
    }
}
