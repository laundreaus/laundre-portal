<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RoleMiddleware {
    public function handle(Request $request, Closure $next, string ...$roles) {
        $user = $request->user();
        if (!$user) { abort(401); }
        if (!empty($roles) && !in_array($user->role, $roles, true)) { abort(403, 'Insufficient role'); }
        return $next($request);
    }
}
