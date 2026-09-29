<?php
namespace App\Console\Commands;

use App\Models\PipelineCard;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily: any prospect who has sat in "Reviewing Documents" for 14+ days is emailed an
 * invitation to book a meeting with Luke to confirm times. Fires once per card (guarded
 * by the automation flag), so a card that lingers is not emailed repeatedly.
 */
class CrmReviewingFollowup extends Command
{
    protected $signature = 'crm:reviewing-followup {--days=14 : days in Reviewing Documents before the follow-up} {--force : ignore the days threshold (test)}';
    protected $description = 'Email prospects a meeting-booking prompt after 14 days in Reviewing Documents';

    public function handle(): int
    {
        $days = (int) $this->option('days') ?: 14;
        $cutoff = Carbon::now()->subDays($days);
        $sent = 0;

        $cards = PipelineCard::where('stage', 'reviewing_documents')->get();
        foreach ($cards as $card) {
            $auto = $card->automation ?? [];
            if (!empty($auto['meeting_email_sent']) || empty($card->email)) continue;

            $entered = $card->stage_changed_at ?? $card->updated_at;
            if (!$this->option('force') && (!$entered || $entered->greaterThan($cutoff))) continue;

            $ok = AdminNotifier::sendPlain(
                [$card->email],
                'Let\'s book a time to chat — Laundré',
                'Ready for the next step',
                'You\'ve had a couple of weeks with the franchise documents — the next step is a quick meeting with Luke to walk through any questions and next steps.',
                [
                    ['Name', $card->name],
                    ['What to do', 'Reply to this email with a few times that suit you over the next week and Luke will confirm one.'],
                ],
                'Looking forward to speaking with you.'
            );
            if ($ok) {
                $auto['meeting_email_sent'] = true;
                $card->automation = $auto;
                $card->save();
                $sent++;
            }
        }

        $this->info("CRM reviewing follow-up: {$sent} email(s) sent.");
        return self::SUCCESS;
    }
}
