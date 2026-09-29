<?php
namespace App\Console\Commands;

use App\Models\Task;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Every day at 7am (Brisbane): email each person their outstanding tasks —
 * grouped into To do / In progress / In review. Completed tasks are excluded.
 */
class SendDailyTasks extends Command
{
    protected $signature = 'reports:daily-tasks {--to= : send only this email a copy of their own tasks (test)}';
    protected $description = 'Email each assignee their open tasks (to do / in progress / in review)';

    public function handle(): int
    {
        $tz = 'Australia/Brisbane';
        $today = Carbon::now($tz)->format('l, j M');

        $tasks = Task::with('assignee:id,name,email')
            ->whereIn('status', ['todo', 'in_progress', 'to_review'])
            ->whereNotNull('assignee_id')
            ->get();

        $byUser = $tasks->groupBy('assignee_id');
        $sent = 0;

        foreach ($byUser as $userId => $list) {
            $user = $list->first()->assignee;
            if (!$user || !$user->email) continue;
            if ($this->option('to') && strcasecmp($user->email, $this->option('to')) !== 0) continue;

            $fmt = fn ($t) => ['title' => $t->title, 'due' => $t->due_date ? $t->due_date->format('j M') : ''];
            $todo     = $list->where('status', 'todo')->map($fmt)->values()->all();
            $progress = $list->where('status', 'in_progress')->map($fmt)->values()->all();
            $review   = $list->where('status', 'to_review')->map($fmt)->values()->all();
            $count = count($todo) + count($progress) + count($review);

            AdminNotifier::sendTemplate('daily_tasks', [$user->email], [
                'subject'  => "Your Laundré tasks — {$count} open",
                'heading'  => 'Your tasks for ' . $today,
                'summary'  => $count . ' open task' . ($count === 1 ? '' : 's') . ' — ' . count($todo) . ' to do, ' . count($progress) . ' in progress, ' . count($review) . ' in review.',
                'todo'     => $todo,
                'progress' => $progress,
                'review'   => $review,
            ]);
            $sent++;
        }

        $this->info("Daily task emails sent to {$sent} people.");
        return self::SUCCESS;
    }
}
