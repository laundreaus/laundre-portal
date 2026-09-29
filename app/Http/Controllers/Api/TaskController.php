<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
class TaskController extends Controller {
    private const STATUSES = ['todo','in_progress','to_review','completed'];
    public function index(Request $r) {
        $u = $r->user();
        $q = Task::with(['assignee:id,name,role','creator:id,name'])->orderByRaw('due_date is null, due_date asc')->orderByDesc('created_at');
        if (!$u->isAdmin()) { $q->where('assignee_id', $u->id); }
        return $q->get();
    }
    public function store(Request $r) {
        return Task::create($this->rules($r) + ['created_by'=>$r->user()->id]);
    }
    public function update(Request $r, Task $task) {
        $task->update($this->rules($r));
        return $task->load(['assignee:id,name,role','creator:id,name']);
    }
    public function setStatus(Request $r, Task $task) {
        $u = $r->user();
        abort_unless($u->isAdmin() || $task->assignee_id === $u->id, 403);
        $d = $r->validate(['status'=>'required|in:'.implode(',',self::STATUSES)]);
        $task->status = $d['status']; $task->save();
        return $task->load(['assignee:id,name,role','creator:id,name']);
    }
    public function destroy(Task $task) { $task->delete(); return response()->noContent(); }
    private function rules(Request $r): array {
        return $r->validate([
            'title'=>'required|string',
            'description'=>'nullable|string',
            'status'=>'required|in:'.implode(',',self::STATUSES),
            'due_date'=>'nullable|date',
            'file_path'=>'nullable|string',
            'file_name'=>'nullable|string',
            'assignee_id'=>'nullable|exists:users,id',
        ]);
    }
}
