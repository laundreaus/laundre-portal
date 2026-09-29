<?php
namespace App\Http\Middleware;
use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
// Records every authenticated request (admin actions, franchisee/investor browsing) for R&D reporting.
class LogActivity {
    public function handle(Request $request, Closure $next) {
        $response = $next($request);
        try { $this->log($request, $response); } catch (\Throwable $e) { /* logging must never break a request */ }
        return $response;
    }
    private function log(Request $request, $response): void {
        $u = $request->user();
        if (!$u) return; // only log signed-in activity
        $method = $request->method();
        $path = '/'.ltrim($request->path(), '/');
        // Skip noise: the log's own endpoints, static assets, high-frequency polls.
        if (str_starts_with($path, 'activity') || str_starts_with($path, '/activity')) return;
        if (preg_match('#\.(ico|png|jpg|jpeg|gif|svg|css|js|map|woff2?)$#i', $path)) return;
        if (in_array($path, ['/sales-group-series', '/onboarding-api/track'])) return;
        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
        ActivityLog::record([
            'user_id'    => $u->id,
            'actor_name' => $u->name,
            'actor_role' => $u->role,
            'action'     => $this->action($method, $path),
            'subject'    => $this->subject($method, $path),
            'method'     => $method,
            'path'       => mb_substr($path, 0, 490),
            'ip'         => $request->ip(),
            'meta'       => ['status' => $status],
        ]);
    }
    private function action(string $m, string $p): string {
        if (in_array($p, ['/login', '/logout']) || str_contains($p, 'impersonate')) return 'auth';
        return match ($m) { 'POST' => 'create', 'PUT', 'PATCH' => 'update', 'DELETE' => 'delete', default => 'view' };
    }
    private function subject(string $m, string $p): string {
        $verb = ['view'=>'Viewed','create'=>'Created','update'=>'Updated','delete'=>'Deleted','auth'=>'Auth'][$this->action($m, $p)] ?? 'Accessed';
        $name = trim(preg_replace('/\s+/', ' ', str_replace(['-api', 'laundre-', '/'], [' ', ' ', ' '], $p)));
        return $verb . ' ' . ($name ?: 'home');
    }
}
