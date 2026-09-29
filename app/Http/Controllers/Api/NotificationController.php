<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;

/**
 * Admin reference: every email trigger the portal sends, when it fires, and who receives it.
 * Single source of truth for the Notifications module.
 */
class NotificationController extends Controller {
    public function index(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $cfg = config('laundre_notify');
        $admins = $cfg['recipients'] ?? [];
        $templates = $cfg['templates'] ?? [];
        $configured = !empty($cfg['sendgrid_key']);
        $adminAudience = 'Admins (' . (implode(', ', $admins) ?: 'none set') . ')';

        $live = fn ($key) => $configured && !empty($templates[$key] ?? null);

        $triggers = [
            // --- Event driven (fire the moment something happens) ---
            ['key'=>'laundromat_cleaned',   'name'=>'Laundromat cleaned',      'group'=>'Event',
             'when'=>'When a cleaner submits a daily clean',        'audience'=>$adminAudience, 'live'=>$live('laundromat_cleaned')],
            ['key'=>'maintenance_completed','name'=>'Maintenance completed',   'group'=>'Event',
             'when'=>'When a maintenance log is submitted',         'audience'=>$adminAudience, 'live'=>$live('maintenance_completed')],
            ['key'=>'nda_signed',           'name'=>'NDA signed',              'group'=>'Event',
             'when'=>'When someone signs their NDA',                'audience'=>$adminAudience, 'live'=>$live('nda_signed')],
            ['key'=>'accounts_uploaded',    'name'=>'BAS / accounts uploaded','group'=>'Event',
             'when'=>'When BAS or accounts files are uploaded',     'audience'=>$adminAudience, 'live'=>$live('accounts_uploaded')],
            ['key'=>'support_ticket',       'name'=>'Support ticket created',  'group'=>'Event',
             'when'=>'When a franchisee raises a support ticket',   'audience'=>$adminAudience, 'live'=>$live('support_ticket')],
            ['key'=>'lead_received',        'name'=>'Website enquiry received','group'=>'Event',
             'when'=>'When a website enquiry lands in Contacts',    'audience'=>$adminAudience, 'live'=>$configured],
            ['key'=>'investor_signup',      'name'=>'Investor signed up',      'group'=>'Event',
             'when'=>'When an investor activates their account',    'audience'=>$adminAudience, 'live'=>$configured],
            ['key'=>'laundromat_status',    'name'=>'Laundromat status change','group'=>'Event',
             'when'=>'Site signed → Project Delivery → Live, or active/inactive changes', 'audience'=>$adminAudience, 'live'=>$configured],
            ['key'=>'site_approved',        'name'=>'Site approved / signed',  'group'=>'Event',
             'when'=>'When a site proposal is signed',              'audience'=>$adminAudience, 'live'=>$configured],
            // --- Scheduled ---
            ['key'=>'daily_tasks',          'name'=>'Daily task summary',      'group'=>'Scheduled',
             'when'=>'Every day at 7:00am (Brisbane)',              'audience'=>'Each team member with open tasks — their own to-do / in-progress / in-review items', 'live'=>$live('daily_tasks')],
            ['key'=>'weekly_revenue',       'name'=>'Weekly revenue',          'group'=>'Scheduled',
             'when'=>'Every Monday at 3:00pm (Brisbane)',           'audience'=>$adminAudience.' get all laundromats; each franchisee & investor gets their assigned laundromats', 'live'=>$live('weekly_revenue')],
            ['key'=>'monthly_sales_report', 'name'=>'Monthly sales report',    'group'=>'Scheduled',
             'when'=>'3rd of each month at 7:00am (Brisbane)',
             'audience'=>$adminAudience.' get all laundromats; each franchisee & investor gets their assigned laundromats; plus custom single-site recipients ('
                 . (implode('; ', array_map(fn($c)=>($c['email']??'').' → '.($c['location']??''), $cfg['custom_monthly'] ?? [])) ?: 'none')
                 . ') — all figures ex GST',
             'live'=>$live('monthly_sales_report')],
        ];

        // Text (SMS) snapshots — sent to the admin mobile numbers via Twilio.
        $twOk = !empty($cfg2 = config('services.twilio')) && !empty($cfg2['sid']) && !empty($cfg2['token']) && !empty($cfg2['from']);
        $smsAudience = 'Admin mobile numbers (set on this page)';
        $triggers[] = ['key'=>'sales_sms_daily',   'name'=>'Daily sales snapshot',   'group'=>'SMS',
            'when'=>'Every day 7:05am (Brisbane) — yesterday’s trading', 'audience'=>$smsAudience, 'live'=>$twOk];
        $triggers[] = ['key'=>'sales_sms_weekly',  'name'=>'Weekly sales snapshot',  'group'=>'SMS',
            'when'=>'Every Monday 7:10am (Brisbane) — last 7 days',      'audience'=>$smsAudience, 'live'=>$twOk];
        $triggers[] = ['key'=>'sales_sms_monthly', 'name'=>'Monthly sales snapshot', 'group'=>'SMS',
            'when'=>'1st of each month 7:15am (Brisbane) — last month',  'audience'=>$smsAudience, 'live'=>$twOk];
        $triggers[] = ['key'=>'admin_alerts_sms',  'name'=>'Admin alerts (as text)', 'group'=>'SMS',
            'when'=>'Every admin email alert above is also sent as a text', 'audience'=>$smsAudience, 'live'=>$twOk];

        $tw = config('services.twilio');
        return response()->json([
            'sendgrid_configured' => $configured,
            'from_email' => $cfg['from_email'] ?? null,
            'admins' => $admins,
            'triggers' => $triggers,
            'twilio_configured' => !empty($tw['sid']) && !empty($tw['token']) && !empty($tw['from']),
            'sms_numbers' => (string) \App\Models\Setting::get('admin_sms_numbers', ''),
        ]);
    }

    /**
     * Ad-hoc portal email (site proposals, site-approved notices, etc.). Sends live via
     * SendGrid as a plain HTML email and records the event to the activity log. Admin only.
     */
    public function send(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $r->validate([
            'event'   => 'nullable|string',
            'to'      => 'nullable|array',
            'to.*'    => 'string|email',
            'subject' => 'required|string',
            'heading' => 'nullable|string',
            'summary' => 'nullable|string',
            'lines'   => 'nullable|array',
            'note'    => 'nullable|string',
        ]);
        $recipients = $d['to'] ?? [];
        $sent = AdminNotifier::sendPlain($recipients, $d['subject'], $d['heading'] ?? '', $d['summary'] ?? '', $d['lines'] ?? [], $d['note'] ?? null);
        try {
            ActivityLog::record([
                'actor_name' => $r->user()->name ?? 'Admin',
                'actor_role' => 'admin',
                'action'     => 'notify',
                'subject'    => $d['subject'],
                'method'     => 'EVENT',
                'path'       => 'notify/' . ($d['event'] ?? 'adhoc'),
                'meta'       => ['to' => $recipients, 'sent' => $sent],
            ]);
        } catch (\Throwable $e) { /* logging must never break the send */ }
        return response()->json(['sent' => $sent]);
    }
}
