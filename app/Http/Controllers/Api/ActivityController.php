<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
class ActivityController extends Controller {
    public function index(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $q = ActivityLog::query()->orderByDesc('id');
        if ($r->filled('role'))   $q->where('actor_role', $r->role);
        if ($r->filled('action')) $q->where('action', $r->action);
        if ($r->filled('q')) {
            $t = $r->q;
            $q->where(fn($w) => $w->where('subject', 'like', "%$t%")
                ->orWhere('actor_name', 'like', "%$t%")
                ->orWhere('path', 'like', "%$t%"));
        }
        return $q->limit((int)($r->limit ?: 500))
            ->get(['id','user_id','actor_name','actor_role','action','subject','method','path','ip','created_at']);
    }
}
