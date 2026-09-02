<?php
namespace App\Services;

use App\Models\ActivityLog;
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
