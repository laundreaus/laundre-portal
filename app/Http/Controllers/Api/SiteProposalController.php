<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Setting;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public site-proposal page. A prospective site packaged in Sites Acquisition gets a
 * share token; this renders that proposal (site summary + the Cost Calculator quote link)
 * with a signature pad. On signing we store the signature against the site record, create
 * the Live Laundromat, seed a Project Delivery timeline and notify admins — the same
 * outcome as the admin "Mark signed" action, but driven by the recipient.
 */
class SiteProposalController extends Controller
{
    private const KEY = 'site_acq_v1';
    private const PD  = 'project_delivery_v1';

    private function findSite(string $token): ?array
    {
        $state = Setting::get(self::KEY, null);
        if (!is_array($state) || empty($state['sites'])) return null;
        foreach ($state['sites'] as $i => $s) {
            if (($s['shareToken'] ?? null) === $token) return ['i' => $i, 'site' => $s, 'state' => $state];
        }
        return null;
    }

    public function publicShow(string $token)
    {
        $found = $this->findSite($token);
        if (!$found) return response('Proposal not found.', 404);
        return response($this->render($found['site'], true))->header('Content-Type', 'text/html');
    }

    public function publicSign(Request $r, string $token)
    {
        $found = $this->findSite($token);
        if (!$found) return response('Proposal not found.', 404);
        $site  = $found['site'];
        $f     = $site['f'] ?? [];

        if (!empty($f['signed_at'])) return redirect('/site-proposal/'.$token);

        $d = $r->validate(['signer_name' => 'required|string', 'signature' => 'required|string']);
        $f['signer_name'] = $d['signer_name'];
        $f['signature']   = $d['signature'];
        $f['signed_at']   = now()->toIso8601String();
        $f['signed_ip']   = $r->ip();

        // Create the Live Laundromat (guarded so it only happens once).
        if (empty($f['live_created'])) {
            $loc = Location::create([
                'name'         => $site['name'] ?: ('Laundré ' . ($f['suburb'] ?? 'site')),
                'address'      => $f['address'] ?? ($f['suburb'] ?? ''),
                'lat'          => is_numeric($f['lat'] ?? null) ? (float) $f['lat'] : null,
                'lng'          => is_numeric($f['lng'] ?? null) ? (float) $f['lng'] : null,
                'radius'       => is_numeric($f['radius'] ?? null) ? (float) $f['radius'] : 3,
                'unit'         => 'km',
                'status'       => 'active',
                'date_approved'=> now()->toDateString(),
                'keys_date'    => $f['keys_date'] ?? null,
                'opening_date' => $f['opening_date'] ?? null,
                'notes'        => 'Created from a signed site proposal. ' . ($f['notes'] ?? ''),
                'about'        => ['acquisition' => $f, 'assigned' => ['name' => $f['assigned_name'] ?? '', 'email' => $f['assigned_email'] ?? '']],
            ]);
            $f['live_created'] = true;
            $f['location_id']  = $loc->id;

            // Seed the Project Delivery timeline.
            $pd = Setting::get(self::PD, null);
            if (!is_array($pd) || !isset($pd['projects'])) $pd = ['projects' => []];
            $pd['projects'][] = [
                'id'           => 'p' . Str::random(8),
                'location_id'  => $loc->id,
                'name'         => $loc->name,
                'keys_date'    => $f['keys_date'] ?? '',
                'fitout_date'  => $f['fitout_date'] ?? '',
                'opening_date' => $f['opening_date'] ?? '',
                'created'      => now()->toIso8601String(),
                'milestones'   => $this->defaultMilestones($f['keys_date'] ?? '', $f['fitout_date'] ?? '', $f['opening_date'] ?? ''),
            ];
            Setting::put(self::PD, $pd);
        }

        $site['f']     = $f;
        $site['stage'] = 'accepted';
        $state = $found['state'];
        $state['sites'][$found['i']] = $site;
        Setting::put(self::KEY, $state);

        AdminNotifier::notify('site_approved', 'Site approved: ' . ($site['name'] ?? ''), [
            'summary' => ($site['name'] ?? 'A site') . ' has been signed by ' . $d['signer_name'] . '.',
            'name'    => $site['name'] ?? '',
            'note'    => 'A Live Laundromat and Project Delivery timeline have been created.',
        ]);

        return redirect('/site-proposal/'.$token);
    }

    private function defaultMilestones($keys, $fitout, $open): array
    {
        $ms = [];
        $add = fn ($t, $d) => ['t' => $t, 'd' => $d, 'done' => false];
        if ($keys)   $ms[] = $add('Keys handover', substr($keys, 0, 10));
        if ($fitout) {
            $ms[] = $add('Fit-out start', substr($fitout, 0, 10));
            $ms[] = $add('Services & rough-in', date('Y-m-d', strtotime($fitout . ' +21 days')));
        }
        if ($open) {
            $ms[] = $add('Machine install', date('Y-m-d', strtotime($open . ' -14 days')));
            $ms[] = $add('Commissioning & test', date('Y-m-d', strtotime($open . ' -3 days')));
            $ms[] = $add('Open for trade', substr($open, 0, 10));
        }
        return $ms;
    }

    private function render(array $site, bool $publicForm = false): string
    {
        $f = $site['f'] ?? [];
        $e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES);
        $name = $e($site['name'] ?? 'Laundré site');
        $signed = !empty($f['signed_at']);

        $rows = '';
        $row = function ($label, $val) use ($e, &$rows) {
            if (($val ?? '') === '') return;
            $rows .= '<tr><td class="l">' . $e($label) . '</td><td>' . nl2br($e($val)) . '</td></tr>';
        };
        $row('Suburb', $f['suburb'] ?? '');
        $row('Address', $f['address'] ?? '');
        $row('Protected radius', ($f['radius'] ?? '') !== '' ? $f['radius'] . ' km' : '');
        $row('Turnkey price', ($f['turnkey_price'] ?? '') !== '' ? '$' . number_format((float) $f['turnkey_price']) : '');
        $row('Terms', $f['terms_suggested'] ?? '');
        $row('Growth outlook', $f['growth'] ?? '');
        $row('Notes', $f['notes'] ?? '');

        $quote = $f['quote_link'] ?? '';
        $quoteBtn = $quote ? '<a href="' . $e($quote) . '" style="display:inline-block;margin-top:6px;background:#C4703F;color:#fff;text-decoration:none;border-radius:8px;padding:11px 20px;font-weight:700;font-size:14px">View &amp; sign the full cost quote →</a>' : '';

        $block = '';
        if ($signed) {
            $sig = (is_string($f['signature'] ?? '') && strpos($f['signature'], 'data:image/') === 0)
                ? '<img src="' . $f['signature'] . '" alt="Signature" style="max-height:84px;display:block;margin-top:10px;background:#fff;border:1px solid #cfe0d4;border-radius:8px;padding:6px">'
                : '';
            $when = $e(date('j M Y, g:ia', strtotime($f['signed_at'])));
            $block = '<div class="sbox ok"><div style="font-weight:800;font-size:16px">✓ Accepted by ' . $e($f['signer_name'] ?? '') . '</div>'
                . '<div style="font-size:13px;margin-top:4px">' . $when . (!empty($f['signed_ip']) ? ' · IP ' . $e($f['signed_ip']) : '') . '</div>' . $sig . '</div>';
        } elseif ($publicForm) {
            $csrf = csrf_token();
            $block = '<div class="sbox"><div style="font-weight:800;color:#33473D;font-size:16px;margin-bottom:4px">Accept &amp; sign this proposal</div>'
                . '<div style="font-size:12.5px;color:#7a8b84;margin-bottom:14px">Sign in the box below with your mouse or finger. Your acceptance is timestamped.</div>'
                . '<form method="post" action="/site-proposal/' . $e($site['shareToken'] ?? '') . '/sign" id="signForm">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<div style="font-size:12px;color:#5f7469;font-weight:600;margin-bottom:5px">Full name</div>'
                . '<input name="signer_name" required value="' . $e($f['assigned_name'] ?? '') . '" style="width:100%;font-size:14px;border:1px solid #E4DBCB;border-radius:8px;padding:10px 12px;margin-bottom:14px">'
                . '<div style="font-size:12px;color:#5f7469;font-weight:600;margin-bottom:5px">Signature</div>'
                . '<div style="position:relative;border:1px solid #E4DBCB;border-radius:10px;background:#FBF9F4;overflow:hidden"><canvas id="sig" style="display:block;width:100%;height:170px;touch-action:none;cursor:crosshair"></canvas><span id="sighint" style="position:absolute;left:14px;bottom:10px;color:#c3bbaa;font-size:12px;pointer-events:none">Sign here</span></div>'
                . '<div style="margin-top:8px"><button type="button" id="clearSig" style="background:transparent;border:1px solid #E4DBCB;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;color:#435E53;cursor:pointer">Clear</button></div>'
                . '<input type="hidden" name="signature" id="sigData">'
                . '<div id="sigErr" style="color:#B4472F;font-size:12.5px;min-height:16px;margin-top:8px"></div>'
                . '<button type="submit" style="background:#435E53;color:#fff;border:none;border-radius:8px;padding:12px 24px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px">✓ Accept &amp; sign</button>'
                . '</form></div>'
                . '<script>(function(){var canvas=document.getElementById("sig"),ctx,drawn=false,drawing=false,last=null;function sizeCanvas(){var r=canvas.getBoundingClientRect();canvas.width=r.width*2;canvas.height=r.height*2;ctx=canvas.getContext("2d");ctx.scale(2,2);ctx.lineWidth=2.2;ctx.lineCap="round";ctx.strokeStyle="#2E3D36";}function pos(e){var r=canvas.getBoundingClientRect();var t=e.touches?e.touches[0]:e;return{x:t.clientX-r.left,y:t.clientY-r.top};}function start(e){drawing=true;last=pos(e);e.preventDefault();}function move(e){if(!drawing)return;var p=pos(e);ctx.beginPath();ctx.moveTo(last.x,last.y);ctx.lineTo(p.x,p.y);ctx.stroke();last=p;drawn=true;document.getElementById("sighint").style.display="none";e.preventDefault();}function end(){drawing=false;}sizeCanvas();canvas.addEventListener("mousedown",start);canvas.addEventListener("mousemove",move);window.addEventListener("mouseup",end);canvas.addEventListener("touchstart",start);canvas.addEventListener("touchmove",move);canvas.addEventListener("touchend",end);document.getElementById("clearSig").onclick=function(){ctx.clearRect(0,0,canvas.width,canvas.height);drawn=false;document.getElementById("sighint").style.display="";};document.getElementById("signForm").addEventListener("submit",function(e){if(!drawn){e.preventDefault();document.getElementById("sigErr").textContent="Please draw your signature above.";return;}document.getElementById("sigData").value=canvas.toDataURL("image/png");});})();</script>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Laundré · Site Proposal</title><style>'
            . 'body{margin:0;background:#F4EFE6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:26px 14px;color:#2E3D36;}'
            . '.doc{max-width:800px;margin:0 auto 16px;background:#fff;border:1px solid #E4DBCB;border-radius:14px;overflow:hidden;box-shadow:0 2px 14px rgba(52,73,61,.08);}'
            . '.hd{background:#33473D;color:#EAF0EC;padding:26px 34px;border-top:5px solid #C4703F;} .hd .k{font-size:12px;color:#b9c8bf;text-transform:uppercase;letter-spacing:.05em;} .hd h1{margin:6px 0 0;font-size:24px;}'
            . '.bd{padding:24px 34px 30px;} table{width:100%;border-collapse:collapse;} td{padding:11px 6px;border-bottom:1px solid #F1ECE1;font-size:14px;vertical-align:top;} td.l{color:#7a8b84;width:170px;font-weight:600;}'
            . '.sbox{max-width:800px;margin:14px auto 30px;background:#fff;border:1px solid #E4DBCB;border-radius:14px;padding:22px 24px;} .sbox.ok{background:#E1EDE4;border-color:#bcd8c4;color:#2f6b46;}'
            . '</style></head><body>'
            . '<div class="doc"><div class="hd"><div class="k">Site proposal</div><h1>' . $name . '</h1></div>'
            . '<div class="bd"><table>' . $rows . '</table>' . ($quoteBtn ? '<div style="margin-top:20px">' . $quoteBtn . '</div>' : '') . '</div></div>'
            . $block . '</body></html>';
    }
}
