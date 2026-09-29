<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\CalcQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalcQuoteController extends Controller {
    // ---------- Admin API ----------
    public function index(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        return CalcQuote::orderByDesc('id')->get(['id','token','name','sqm','reference','client_name','signer_name','signed_at','status','total','created_at']);
    }
    public function store(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $r->validate([
            'name'=>'nullable|string','sqm'=>'nullable|integer','reference'=>'nullable|string',
            'client_name'=>'nullable|string','client_email'=>'nullable|string',
            'payload'=>'nullable|array','html'=>'required|string','total'=>'nullable|numeric',
        ]);
        $d['token'] = Str::random(40);
        $d['status'] = 'sent';
        $d['created_by'] = $r->user()->name;
        return CalcQuote::create($d);
    }
    public function show(Request $r, CalcQuote $calcQuote) {
        abort_unless($r->user()->isAdmin(), 403);
        return $calcQuote;
    }
    public function download(Request $r, CalcQuote $calcQuote) {
        abort_unless($r->user()->isAdmin(), 403);
        $slug = Str::slug(($calcQuote->name ?: 'laundre-quote').'-'.$calcQuote->id) . ($calcQuote->status==='approved' ? '-signed' : '');
        return response($this->render($calcQuote))
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="'.$slug.'.html"');
    }

    // ---------- Public signing (no auth) ----------
    public function publicShow(string $token) {
        $q = CalcQuote::where('token', $token)->first();
        if (!$q) return response('Quote not found.', 404);
        return response($this->render($q, true))->header('Content-Type', 'text/html');
    }
    public function publicSign(Request $r, string $token) {
        $q = CalcQuote::where('token', $token)->first();
        if (!$q) return response('Quote not found.', 404);
        if ($q->status === 'approved') return redirect('/calc-quote/'.$token);
        $d = $r->validate(['signer_name'=>'required|string', 'signature'=>'required|string']);
        $q->signer_name = $d['signer_name'];
        $q->signature   = $d['signature'];
        $q->signed_at   = now();
        $q->signed_ip   = $r->ip();
        $q->status      = 'approved';
        $q->save();
        return redirect('/calc-quote/'.$token);
    }

    // Wrap the stored client quote document in a standalone page with a sign form / signed block.
    private function render(CalcQuote $q, bool $publicForm = false): string {
        $doc = $q->html ?: '<div style="padding:30px">Quote content unavailable.</div>';
        $signed = $q->status === 'approved';
        $block = '';
        if ($signed) {
            $sig = (is_string($q->signature) && strpos($q->signature, 'data:image/') === 0)
                ? '<img src="' . $q->signature . '" alt="Signature" style="max-height:84px;display:block;margin-top:10px;background:#fff;border:1px solid #cfe0d4;border-radius:8px;padding:6px">'
                : '<div style="font-family:cursive;font-size:24px;color:#33473D;margin-top:8px">' . e($q->signature) . '</div>';
            $block = '<div style="max-width:800px;margin:14px auto 30px;background:#E1EDE4;border:1px solid #bcd8c4;color:#2f6b46;border-radius:14px;padding:18px 22px">'
                . '<div style="font-weight:800;font-size:16px">✓ Approved by ' . e($q->signer_name) . '</div>'
                . '<div style="font-size:13px;margin-top:4px">' . e(optional($q->signed_at)->format('j M Y, g:ia')) . ($q->signed_ip ? ' · IP ' . e($q->signed_ip) : '') . '</div>'
                . $sig
                . '</div>';
        } elseif ($publicForm) {
            $csrf = csrf_token();
            $block = '<div style="max-width:800px;margin:14px auto 30px;background:#fff;border:1px solid #E4DBCB;border-radius:14px;padding:22px 24px">'
                . '<div style="font-weight:800;color:#33473D;font-size:16px;margin-bottom:4px">Approve &amp; sign this quote</div>'
                . '<div style="font-size:12.5px;color:#7a8b84;margin-bottom:14px">Sign in the box below with your mouse or finger. Your approval is timestamped.</div>'
                . '<form method="post" action="/calc-quote/' . e($q->token) . '/sign" id="signForm">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<div style="font-size:12px;color:#5f7469;font-weight:600;margin-bottom:5px">Full name</div>'
                . '<input name="signer_name" required value="' . e($q->client_name) . '" style="width:100%;font-size:14px;border:1px solid #E4DBCB;border-radius:8px;padding:10px 12px;margin-bottom:14px">'
                . '<div style="font-size:12px;color:#5f7469;font-weight:600;margin-bottom:5px">Signature</div>'
                . '<div style="position:relative;border:1px solid #E4DBCB;border-radius:10px;background:#FBF9F4;overflow:hidden"><canvas id="sig" style="display:block;width:100%;height:170px;touch-action:none;cursor:crosshair"></canvas><span id="sighint" style="position:absolute;left:14px;bottom:10px;color:#c3bbaa;font-size:12px;pointer-events:none">Sign here</span></div>'
                . '<div style="margin-top:8px"><button type="button" id="clearSig" style="background:transparent;border:1px solid #E4DBCB;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;color:#435E53;cursor:pointer">Clear</button></div>'
                . '<input type="hidden" name="signature" id="sigData">'
                . '<div id="sigErr" style="color:#B4472F;font-size:12.5px;min-height:16px;margin-top:8px"></div>'
                . '<button type="submit" style="background:#435E53;color:#fff;border:none;border-radius:8px;padding:12px 24px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px">✓ Approve &amp; sign</button>'
                . '</form></div>'
                . '<script>(function(){var canvas=document.getElementById("sig"),ctx,drawn=false,drawing=false,last=null;function sizeCanvas(){var r=canvas.getBoundingClientRect();canvas.width=r.width*2;canvas.height=r.height*2;ctx=canvas.getContext("2d");ctx.scale(2,2);ctx.lineWidth=2.2;ctx.lineCap="round";ctx.strokeStyle="#2E3D36";}function pos(e){var r=canvas.getBoundingClientRect();var t=e.touches?e.touches[0]:e;return{x:t.clientX-r.left,y:t.clientY-r.top};}function start(e){drawing=true;last=pos(e);e.preventDefault();}function move(e){if(!drawing)return;var p=pos(e);ctx.beginPath();ctx.moveTo(last.x,last.y);ctx.lineTo(p.x,p.y);ctx.stroke();last=p;drawn=true;document.getElementById("sighint").style.display="none";e.preventDefault();}function end(){drawing=false;}sizeCanvas();canvas.addEventListener("mousedown",start);canvas.addEventListener("mousemove",move);window.addEventListener("mouseup",end);canvas.addEventListener("touchstart",start);canvas.addEventListener("touchmove",move);canvas.addEventListener("touchend",end);document.getElementById("clearSig").onclick=function(){ctx.clearRect(0,0,canvas.width,canvas.height);drawn=false;document.getElementById("sighint").style.display="";};document.getElementById("signForm").addEventListener("submit",function(e){if(!drawn){e.preventDefault();document.getElementById("sigErr").textContent="Please draw your signature above.";return;}document.getElementById("sigData").value=canvas.toDataURL("image/png");});})();</script>';
        }
        $css = '.qdoc,.qdoc *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            . '.qdoc{background:#fff;border:1px solid #E4DBCB;border-radius:14px;box-shadow:0 2px 14px rgba(52,73,61,.08);max-width:800px;margin:0 auto 16px;overflow:hidden;color:#2E3D36;}'
            . '.qdoc .qhd{background:#33473D;color:#EAF0EC;padding:26px 34px;display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-top:5px solid #C4703F;}'
            . '.qdoc .qhd .wmlogo{display:inline-block;} .qdoc .qhd .wmlogo svg{height:30px;width:auto;display:block;} .qdoc .qhd .tag{font-size:12px;color:#b9c8bf;margin-top:8px;}'
            . '.qdoc .qhd .qmeta{text-align:right;font-size:12px;color:#cdd9d1;line-height:1.6;}'
            . '.qdoc .qbody{padding:26px 34px 30px;} .qdoc h3{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#C4703F;margin:0 0 4px;font-weight:800;}'
            . '.qdoc .prop{font-size:22px;font-weight:800;color:#33473D;margin:0 0 18px;}'
            . '.qdoc table{width:100%;border-collapse:collapse;margin:6px 0 4px;} .qdoc td{padding:12px 4px;border-bottom:1px solid #F1ECE1;font-size:14.5px;} .qdoc td.r{text-align:right;font-weight:700;}'
            . '.qdoc tr.sub td{border-bottom:none;color:#7a8b84;padding-top:6px;padding-bottom:6px;} .qdoc tr.tot td{border-top:2px solid #33473D;border-bottom:none;font-size:19px;font-weight:900;color:#33473D;padding-top:14px;}'
            . '.qdoc .pay{background:#FBF9F4;border:1px solid #EEE7D9;border-radius:12px;padding:16px 20px;margin-top:22px;} .qdoc .pay .r{display:flex;justify-content:space-between;padding:8px 0;font-size:14px;border-bottom:1px solid #F1ECE1;} .qdoc .pay .r:last-child{border-bottom:none;} .qdoc .pay .r b{color:#33473D;}'
            . '.qdoc .disc{font-size:11px;color:#8a9790;line-height:1.6;margin-top:22px;border-top:1px solid #F1ECE1;padding-top:14px;} .qdoc .qfoot{background:#33473D;color:#cdd9d1;text-align:center;font-size:11px;padding:12px;}';
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Laundré · Quote</title><style>body{margin:0;background:#F4EFE6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:26px 14px;}' . $css . '</style></head>'
            . '<body><div class="qdoc">' . $doc . '</div>' . $block . '</body></html>';
    }
}
