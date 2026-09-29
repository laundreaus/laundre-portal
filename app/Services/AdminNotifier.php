<?php
namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Admin notifications for: laundromat cleaned, maintenance completed, monthly
 * sales report, NDA signed, BAS/accounts uploaded, support ticket created.
 *
 * Records every event to the activity log, then sends via SendGrid dynamic
 * templates to the configured admin recipients (luke@ / adam@laundre.com.au).
 */
class AdminNotifier
{
    /** Human labels for known data keys, in a sensible display order. */
    private const LABELS = [
        'location'   => 'Laundromat',
        'month'      => 'Month',
        'date'       => 'Date',
        'by'         => 'By',
        'from_name'  => 'From',
        'from_email' => 'Email',
        'type'       => 'Type',
        'subject'    => 'Subject',
        'role'       => 'Role',
        'name'       => 'Name',
        'email'      => 'Email',
        'fy'         => 'Financial year',
        'ip'         => 'IP address',
    ];

    public static function notify(string $event, string $subject, array $data = []): void
    {
        try {
            ActivityLog::record([
                'actor_name' => 'System',
                'actor_role' => 'system',
                'action'     => 'notify',
                'subject'    => $subject,
                'method'     => 'EVENT',
                'path'       => 'notify/' . $event,
                'meta'       => $data,
            ]);
        } catch (\Throwable $e) {
            // never let logging break the request
        }

        try {
            self::send($event, $subject, $data);
        } catch (\Throwable $e) {
            Log::warning('[AdminNotifier] send failed for ' . $event . ': ' . $e->getMessage());
        }

        try {
            $text = $subject;
            if (!empty($data['summary']) && $data['summary'] !== $subject) $text .= ' — ' . $data['summary'];
            self::sms($text);
        } catch (\Throwable $e) {
            Log::warning('[AdminNotifier] sms failed for ' . $event . ': ' . $e->getMessage());
        }
    }

    /**
     * Fire an admin alert that emails the admins (plain HTML, no dynamic template needed)
     * AND texts them, and records it to the activity log. Used for events without a
     * dedicated SendGrid template (investor sign-ups, laundromat status changes, etc.).
     */
    public static function adminAlert(string $event, string $subject, string $summary = '', array $lines = [], ?string $note = null): void
    {
        try {
            ActivityLog::record([
                'actor_name' => 'System', 'actor_role' => 'system', 'action' => 'notify',
                'subject' => $subject, 'method' => 'EVENT', 'path' => 'notify/' . $event,
                'meta' => ['summary' => $summary],
            ]);
        } catch (\Throwable $e) {}
        $cfg = config('laundre_notify');
        try { self::sendPlain($cfg['recipients'] ?? [], $subject, $subject, $summary, $lines, $note); }
        catch (\Throwable $e) { Log::warning('[AdminNotifier] adminAlert email failed: ' . $e->getMessage()); }
        try { self::sms($subject . ($summary ? ' — ' . $summary : '')); }
        catch (\Throwable $e) { Log::warning('[AdminNotifier] adminAlert sms failed: ' . $e->getMessage()); }
    }

    /**
     * Send a brief SMS of an admin notification via Twilio. Recipients come from the
     * Notifications setting `admin_sms_numbers` (comma/newline separated E.164 numbers).
     * No-ops safely when Twilio isn't configured or no numbers are set.
     */
    public static function sms(string $message): bool
    {
        $cfg = config('services.twilio');
        $sid = $cfg['sid'] ?? null; $token = $cfg['token'] ?? null; $from = $cfg['from'] ?? null;

        $raw = (string) Setting::get('admin_sms_numbers', '');
        $numbers = array_values(array_filter(array_map('trim', preg_split('/[,\n;]+/', $raw))));
        if (!$sid || !$token || !$from || empty($numbers)) {
            Log::info('[AdminNotifier] sms skipped (not configured)');
            return false;
        }

        $body = mb_substr('Laundré: ' . $message, 0, 300);
        $ok = true;
        foreach ($numbers as $to) {
            try {
                $resp = Http::asForm()->withBasicAuth($sid, $token)->timeout(15)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from, 'To' => $to, 'Body' => $body,
                    ]);
                if (!$resp->successful()) { $ok = false; Log::warning('[AdminNotifier] Twilio ' . $resp->status() . ' ' . $resp->body()); }
            } catch (\Throwable $e) { $ok = false; Log::warning('[AdminNotifier] Twilio exception: ' . $e->getMessage()); }
        }
        return $ok;
    }

    /** Send via SendGrid dynamic template. Returns true on 2xx. */
    public static function send(string $event, string $subject, array $data = []): bool
    {
        $cfg = config('laundre_notify');
        $key = $cfg['sendgrid_key'] ?? null;
        $templateId = $cfg['templates'][$event] ?? null;
        $recipients = $cfg['recipients'] ?? [];
        if (!$key || !$templateId || empty($recipients)) {
            Log::info('[AdminNotifier] skipped (not configured) ' . $event);
            return false;
        }

        $dtd = [
            'subject' => $subject,
            'heading' => $cfg['headings'][$event] ?? $subject,
            'summary' => $data['summary'] ?? $subject,
            'lines'   => self::buildLines($event, $data),
            'note'    => $data['note'] ?? ($data['issues'] ?? ($data['message'] ?? null)),
        ];

        $payload = [
            'from' => ['email' => $cfg['from_email'], 'name' => $cfg['from_name']],
            'personalizations' => [[
                'to' => array_map(fn ($e) => ['email' => $e], $recipients),
                'dynamic_template_data' => $dtd,
            ]],
            'template_id' => $templateId,
        ];

        $resp = Http::withToken($key)
            ->timeout(15)
            ->post('https://api.sendgrid.com/v3/mail/send', $payload);

        if (!$resp->successful()) {
            Log::warning('[AdminNotifier] SendGrid ' . $resp->status() . ' ' . $resp->body());
            return false;
        }
        return true;
    }

    /**
     * Send a template to an explicit recipient list with pre-built dynamic data.
     * Used for per-user emails (e.g. weekly revenue to each franchisee/investor).
     */
    public static function sendTemplate(string $event, array $recipients, array $dtd): bool
    {
        $cfg = config('laundre_notify');
        $key = $cfg['sendgrid_key'] ?? null;
        $templateId = $cfg['templates'][$event] ?? null;
        $recipients = array_values(array_filter(array_map('trim', $recipients)));
        if (!$key || !$templateId || empty($recipients)) return false;

        $resp = Http::withToken($key)->timeout(15)->post('https://api.sendgrid.com/v3/mail/send', [
            'from' => ['email' => $cfg['from_email'], 'name' => $cfg['from_name']],
            'personalizations' => [[
                'to' => array_map(fn ($e) => ['email' => $e], $recipients),
                'dynamic_template_data' => $dtd,
            ]],
            'template_id' => $templateId,
        ]);
        if (!$resp->successful()) {
            Log::warning('[AdminNotifier] sendTemplate ' . $event . ' ' . $resp->status() . ' ' . $resp->body());
            return false;
        }
        return true;
    }

    /**
     * Send a plain (non-template) HTML email via SendGrid. Used for ad-hoc portal emails
     * that don't have a dedicated dynamic template — site proposals, site-approved notices,
     * website-lead alerts. Returns true on 2xx. Recipients default to the configured admins.
     *
     * @param array $lines list of [label, value] pairs rendered as a simple table
     */
    public static function sendPlain(array $recipients, string $subject, string $heading = '', string $summary = '', array $lines = [], ?string $note = null): bool
    {
        $cfg = config('laundre_notify');
        $key = $cfg['sendgrid_key'] ?? null;
        $recipients = array_values(array_filter(array_map('trim', $recipients)));
        if (empty($recipients)) { $recipients = $cfg['recipients'] ?? []; }
        if (!$key || empty($recipients)) {
            Log::info('[AdminNotifier] sendPlain skipped (not configured)');
            return false;
        }

        $rows = '';
        foreach ($lines as $ln) {
            $label = htmlspecialchars((string)($ln[0] ?? ''), ENT_QUOTES);
            $value = htmlspecialchars((string)($ln[1] ?? ''), ENT_QUOTES);
            if ($value === '') continue;
            $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#6b7d74;font-size:13px;vertical-align:top">'
                   . $label . '</td><td style="padding:6px 0;color:#2E3D36;font-size:13px">' . nl2br($value) . '</td></tr>';
        }
        $noteHtml = $note ? '<p style="margin:16px 0 0;color:#2E3D36;font-size:13px;line-height:1.6">'
                   . nl2br(htmlspecialchars($note, ENT_QUOTES)) . '</p>' : '';
        $html = '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto">'
              . '<div style="background:#33473D;color:#EAF0EC;padding:18px 22px;border-radius:12px 12px 0 0;font-weight:800;font-size:16px">'
              . htmlspecialchars($heading ?: $subject, ENT_QUOTES) . '</div>'
              . '<div style="border:1px solid #E4DBCB;border-top:none;border-radius:0 0 12px 12px;padding:20px 22px;background:#fff">'
              . ($summary ? '<p style="margin:0 0 14px;color:#2E3D36;font-size:14px;line-height:1.6">' . htmlspecialchars($summary, ENT_QUOTES) . '</p>' : '')
              . ($rows ? '<table style="border-collapse:collapse;width:100%">' . $rows . '</table>' : '')
              . $noteHtml
              . '<p style="margin:20px 0 0;color:#8a9790;font-size:11px">Sent by the Laundré franchising portal.</p>'
              . '</div></div>';

        $resp = Http::withToken($key)->timeout(15)->post('https://api.sendgrid.com/v3/mail/send', [
            'from' => ['email' => $cfg['from_email'], 'name' => $cfg['from_name']],
            'personalizations' => [[ 'to' => array_map(fn ($e) => ['email' => $e], $recipients) ]],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $html]],
        ]);
        if (!$resp->successful()) {
            Log::warning('[AdminNotifier] sendPlain ' . $resp->status() . ' ' . $resp->body());
            return false;
        }
        return true;
    }

    /** Build the key/value rows shown in the email from the event data. */
    private static function buildLines(string $event, array $data): array
    {
        if ($event === 'monthly_sales_report') {
            $lines = [
                ['label' => 'Month',            'value' => $data['month'] ?? ''],
                ['label' => 'Total (ex GST)',   'value' => '$' . number_format((float)($data['total_ex_gst'] ?? 0), 2)],
                ['label' => 'Total (inc GST)',  'value' => '$' . number_format((float)($data['total_inc_gst'] ?? 0), 2)],
                ['label' => 'Laundromats',      'value' => (string) count($data['locations'] ?? [])],
            ];
            foreach (($data['locations'] ?? []) as $l) {
                $lines[] = ['label' => $l['location'] ?? '—', 'value' => '$' . number_format((float)($l['ex_gst'] ?? 0), 2) . ' ex GST'];
            }
            return $lines;
        }

        $lines = [];
        foreach (self::LABELS as $k => $label) {
            if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '' && !is_array($data[$k])) {
                $lines[] = ['label' => $label, 'value' => (string) $data[$k]];
            }
        }
        return $lines;
    }
}
