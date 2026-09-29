<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QuoteController extends Controller {
    // ---------- Admin API ----------
    public function index(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $q = Quote::query()->orderByDesc('id');
        if ($r->filled('loc')) $q->where('location_id', (int) $r->query('loc'));
        return $q->get();
    }
    public function store(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $this->rules($r);
        $d['token'] = Str::random(32);
        $d['created_by'] = $r->user()->name;
        $d['total'] = $this->sum($d['items'] ?? []);
        return Quote::create($d);
    }
    public function update(Request $r, Quote $quote) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $this->rules($r);
        $d['total'] = $this->sum($d['items'] ?? $quote->items ?? []);
        $quote->update($d);
        return $quote;
    }
    public function destroy(Request $r, Quote $quote) {
        abort_unless($r->user()->isAdmin(), 403);
        $quote->delete();
        return response()->noContent();
    }
    // Download the (signed) quote as a self-contained document for the admin's records.
    public function downloadSigned(Request $r, Quote $quote) {
        abort_unless($r->user()->isAdmin(), 403);
        $html = $this->renderPage($quote, true);
        $slug = $quote->reference ? Str::slug($quote->reference) : ('quote-' . $quote->id);
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="' . $slug . ($quote->status === 'signed' ? '-signed' : '') . '.html"');
    }

    // Build the 3-invoice Xero schedule (50/25/25, one month apart). Actual Xero creation is wired later.
    public function schedule(Request $r, Quote $quote) {
        abort_unless($r->user()->isAdmin(), 403);
        $quote->invoice_schedule = $this->buildSchedule($quote);
        $quote->xero_status = 'pending';
        $quote->save();
        return $quote;
    }

    private function buildSchedule(Quote $quote): array {
        $base = $quote->signed_at ? Carbon::parse($quote->signed_at) : Carbon::now('Australia/Brisbane');
        $total = (float) $quote->total;
        $pcts = [50, 25, 25];
        $out = [];
        foreach ($pcts as $i => $pct) {
            $out[] = [
                'n' => $i + 1,
                'pct' => $pct,
                'amount' => round($total * $pct / 100, 2),
                'due' => $base->copy()->addMonthsNoOverflow($i)->toDateString(),
                'adjustable' => $i === 2,   // third can be adjusted in Xero
                'xero_status' => 'pending',
                'xero_id' => null,
            ];
        }
        return $out;
    }

    private function sum(array $items): float {
        $s = 0.0; foreach ($items as $it) { $s += (float) ($it['amount'] ?? 0); } return round($s, 2);
    }
    private function rules(Request $r): array {
        return $r->validate([
            'location_id'=>'required|exists:locations,id',
            'reference'=>'nullable|string','client_name'=>'nullable|string','client_email'=>'nullable|string',
            'notes'=>'nullable|string','items'=>'nullable|array','items.*.description'=>'nullable|string','items.*.amount'=>'nullable|numeric',
            'status'=>'nullable|in:draft,sent,signed,invoiced',
        ]);
    }

    // ---------- Public signing (no auth) ----------
    public function publicShow(string $token) {
        $quote = Quote::where('token', $token)->first();
        if (!$quote) return response('Quote not found.', 404);
        return response($this->renderPage($quote))->header('Content-Type', 'text/html');
    }
    public function publicSign(Request $r, string $token) {
        $quote = Quote::where('token', $token)->first();
        if (!$quote) return response('Quote not found.', 404);
        if ($quote->status === 'signed') return redirect('/quote/' . $token);
        $d = $r->validate(['signer_name'=>'required|string', 'signature'=>'required|string']);
        $quote->signer_name = $d['signer_name'];
        $quote->signature = $d['signature'];
        $quote->signed_at = now();
        $quote->signed_ip = $r->ip();
        $quote->status = 'signed';
        $quote->invoice_schedule = $this->buildSchedule($quote); // schedule anchored to signing date
        $quote->save();
        return redirect('/quote/' . $token);
    }

    private function renderPage(Quote $quote, bool $download = false): string {
        $loc = Location::find($quote->location_id);
        $locName = $loc ? e($loc->name) : '';
        $ref = e($quote->reference ?: 'Quote');
        $money = fn ($n) => '$' . number_format((float) $n, 2);
        $rows = '';
        foreach (($quote->items ?? []) as $it) {
            $rows .= '<tr><td style="padding:9px 12px;border-bottom:1px solid #F1ECE1">' . e($it['description'] ?? '') . '</td>'
                . '<td style="padding:9px 12px;text-align:right;border-bottom:1px solid #F1ECE1;font-weight:700">' . $money($it['amount'] ?? 0) . '</td></tr>';
        }
        $signed = $quote->status === 'signed';
        $body = '';
        if ($signed) {
            $body = '<div style="background:#E1EDE4;border:1px solid #bcd8c4;color:#2f6b46;border-radius:12px;padding:16px 18px">'
                . '<div style="font-weight:800">✓ Signed by ' . e($quote->signer_name) . '</div>'
                . '<div style="font-size:13px;margin-top:4px">' . e(optional($quote->signed_at)->format('j M Y, g:ia')) . ($quote->signed_ip ? ' · IP ' . e($quote->signed_ip) : '') . '</div>'
                . '<div style="font-family:cursive;font-size:22px;color:#33473D;margin-top:8px">' . e($quote->signature) . '</div>'
                . '</div>';
        } elseif ($download) {
            $body = '<div style="background:#F5EEDA;border:1px solid #e4d3a5;color:#8a6d2b;border-radius:12px;padding:14px 16px;font-weight:600">Not yet signed.</div>';
        } else {
            $csrf = csrf_token();
            $body = '<form method="post" action="/quote/' . e($quote->token) . '/sign" style="margin-top:8px">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<div style="font-size:12px;color:#5f7469;font-weight:600;margin-bottom:5px">Full name</div>'
                . '<input name="signer_name" required value="' . e($quote->client_name) . '" style="width:100%;font-size:14px;border:1px solid #E4DBCB;border-radius:8px;padding:10px 12px;margin-bottom:12px">'
                . '<div style="font-size:12px;color:#5f7469;font-weight:600;margin-bottom:5px">Type your full name to sign</div>'
                . '<input name="signature" required placeholder="Your signature" style="width:100%;font-family:cursive;font-size:20px;color:#33473D;border:1px solid #E4DBCB;border-radius:8px;padding:10px 12px;margin-bottom:6px">'
                . '<div style="font-size:11px;color:#8a9790;margin-bottom:14px">By signing you accept this quote and the payment schedule below.</div>'
                . '<button type="submit" style="background:#435E53;color:#fff;border:none;border-radius:8px;padding:12px 22px;font-size:15px;font-weight:700;cursor:pointer">Sign &amp; accept</button>'
                . '</form>';
        }
        $sched = '<table style="width:100%;border-collapse:collapse;margin-top:8px"><tr><td style="padding:6px 0;color:#8a9790">1st payment · 50%</td><td style="text-align:right;font-weight:700">' . $money($quote->total * 0.5) . ' <span style="color:#8a9790;font-weight:400">on signing</span></td></tr>'
            . '<tr><td style="padding:6px 0;color:#8a9790">2nd payment · 25%</td><td style="text-align:right;font-weight:700">' . $money($quote->total * 0.25) . ' <span style="color:#8a9790;font-weight:400">+1 month</span></td></tr>'
            . '<tr><td style="padding:6px 0;color:#8a9790">3rd payment · 25%</td><td style="text-align:right;font-weight:700">' . $money($quote->total * 0.25) . ' <span style="color:#8a9790;font-weight:400">+2 months</span></td></tr></table>';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Laundré · Quote</title></head>'
            . '<body style="margin:0;background:#F4EFE6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#2E3D36;padding:28px 14px">'
            . '<div style="max-width:620px;margin:0 auto">'
            . '<div style="background:#33473D;color:#EAF0EC;border-radius:14px 14px 0 0;padding:20px 24px;font-size:19px;font-weight:800">Laundré</div>'
            . '<div style="background:#fff;border:1px solid #E4DBCB;border-top:none;border-radius:0 0 14px 14px;padding:24px">'
            . '<h1 style="margin:0 0 2px;font-size:21px;color:#33473D">' . $ref . '</h1>'
            . ($locName ? '<div style="color:#8a9790;font-size:13px;margin-bottom:16px">' . $locName . '</div>' : '')
            . ($quote->notes ? '<p style="font-size:14px;color:#4a5852;line-height:1.6">' . nl2br(e($quote->notes)) . '</p>' : '')
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #EEE7D9;border-radius:10px;overflow:hidden;margin:14px 0">' . $rows
            . '<tr><td style="padding:11px 12px;font-weight:800;color:#33473D">Total</td><td style="padding:11px 12px;text-align:right;font-weight:800;color:#33473D;font-size:16px">' . $money($quote->total) . '</td></tr></table>'
            . '<div style="background:#FBF9F4;border:1px solid #EEE7D9;border-radius:10px;padding:14px 16px;margin:14px 0"><div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#C4703F;margin-bottom:6px">Payment schedule</div>' . $sched . '</div>'
            . $body
            . '</div><div style="text-align:center;color:#a49b86;font-size:11px;padding:14px">Laundré Franchising</div>'
            . '</div></body></html>';
    }
}
