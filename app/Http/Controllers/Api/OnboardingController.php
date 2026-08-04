<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\OnboardingDocument;
use App\Models\DocumentView;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
class OnboardingController extends Controller {
    private function typeFor(User $u): string { return $u->role === 'investor' ? 'investor' : 'franchisee'; }
    private function ob(User $u): Onboarding {
        $o = Onboarding::firstOrCreate(['user_id'=>$u->id], ['type'=>$this->typeFor($u),'crm_stage'=>'invited']);
        if (!$o->first_login_at) { $o->first_login_at = now(); $o->save(); }
        return $o;
    }

    // current user's onboarding state + prefill
    public function state(Request $r) {
        $u = $r->user();
        $o = $u->isOnboarding() ? $this->ob($u) : null;
        return response()->json([
            'user' => ['name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone,'role'=>$u->role],
            'kind' => $u->docAudience(),
            'nda_signed_at' => optional($u->nda_signed_at)->toISOString(),
            'access_expires_at' => optional($u->access_expires_at)->toISOString(),
            'onboarding' => $o,
        ]);
    }

    public function sign(Request $r) {
        $u = $r->user();
        abort_unless($u->ndaRequiredRole(), 403);
        $d = $r->validate([
            'name'=>'required|string','email'=>'nullable|string','phone'=>'nullable|string',
            'address'=>'required|string','typed_name'=>'required|string','signature'=>'required|string',
        ]);
        // Store the signed NDA against the user (applies to franchisee, investor, cleaner, maintenance)
        $u->nda_signed_at   = now();
        $u->nda_signer_name = $d['typed_name'];
        $u->nda_signature   = $d['signature'];
        $u->nda_address     = $d['address'];
        if ($u->timeLimitedRole() && !$u->access_expires_at) { $u->access_expires_at = now()->addDays(21); }
        $u->save();

        // Onboarding-role extras (franchisee / investor tracker)
        if ($u->isOnboarding()) {
            $o = $this->ob($u);
            $o->fill([
                'nda_name'=>$d['name'],'nda_email'=>$d['email']??$u->email,'nda_phone'=>$d['phone']??$u->phone,
                'nda_address'=>$d['address'],'nda_typed_name'=>$d['typed_name'],'nda_signature'=>$d['signature'],
                'nda_ip'=>$r->ip(),'nda_signed_at'=>now(),'contact_due_at'=>now()->addDays(14),
            ]);
            if ($o->crm_stage === 'invited') $o->crm_stage = 'onboarding';
            $o->save();
            if ($u->role === 'potential_franchisee') {
                \App\Models\PipelineCard::where('user_id',$u->id)->where('stage','nda_sent')->update(['stage'=>'reviewing_documents']);
            }
        }
        return response()->json(['ok'=>true]);
    }

    public function track(Request $r) {
        $u = $r->user();
        $d = $r->validate(['event'=>'required|in:video,doc']);
        if (!$u->isOnboarding()) return response()->json(['ok'=>true]);
        $o = $this->ob($u);
        if ($d['event']==='video' && !$o->video_watched_at) { $o->video_watched_at = now(); $o->save(); }
        if ($d['event']==='doc' && !$o->first_doc_opened_at) { $o->first_doc_opened_at = now(); $o->save(); }
        return response()->json(['ok'=>true]);
    }

    public function interest(Request $r) {
        $u = $r->user();
        abort_unless($u->role==='investor', 403);
        $d = $r->validate(['min'=>'nullable|numeric','max'=>'nullable|numeric','note'=>'nullable|string']);
        $o = $this->ob($u);
        $o->fill(['interest_min'=>$d['min']??null,'interest_max'=>$d['max']??null,'interest_note'=>$d['note']??null,'interest_submitted_at'=>now()]);
        $o->save();
        return response()->json(['ok'=>true]);
    }

    // ---- Onboarding portal: video + document library (current user) ----
    public function content(Request $r) {
        $u = $r->user();
        $key = $u->docAudience()==='investor' ? 'onboarding_video_investor' : 'onboarding_video_franchisee';
        return response()->json(['kind'=>$u->docAudience(), 'video'=>(string)(Setting::get($key,''))]);
    }
    public function myDocuments(Request $r) {
        $u = $r->user();
        $aud = $u->docAudience();
        return OnboardingDocument::whereIn('audience', [$aud,'both'])->orderBy('position')->orderBy('id')->get()
            ->map(function ($d) {
                $ext = strtolower(pathinfo($d->file_name ?: $d->file_path, PATHINFO_EXTENSION));
                $inline = in_array(($d->mime ?: ''), ['application/pdf','image/png','image/jpeg','image/jpg','image/gif','image/webp'])
                          || $ext === 'pdf' || in_array($ext,['png','jpg','jpeg','gif','webp']);
                return ['id'=>$d->id,'title'=>$d->title,'file_name'=>$d->file_name,'ext'=>$ext,
                        'inline'=>$inline,'protected'=>(bool)$d->protected,'url'=>url('/onboarding-doc/'.$d->id)];
            });
    }
    private function resolvePath(string $fp): ?string {
        $p = public_path(ltrim($fp, '/'));
        return is_file($p) ? $p : null;
    }
    public function openDocument(Request $r, OnboardingDocument $doc) {
        $u = $r->user();
        $aud = $u->docAudience();
        $allowed = $u->isAdmin() || $u->canSection('laundre-onboarding-content') || in_array($doc->audience, [$aud,'both']);
        abort_unless($allowed, 403);
        if (!$u->isAdmin() && !$u->canSection('laundre-onboarding-content')) {
            DocumentView::create(['onboarding_document_id'=>$doc->id,'user_id'=>$u->id,'viewed_at'=>now()]);
            if ($u->isOnboarding()) { $o=$this->ob($u); if(!$o->first_doc_opened_at){$o->first_doc_opened_at=now();$o->save();} }
        }
        $path = $this->resolvePath($doc->file_path);
        abort_unless($path, 404);
        $mime = $doc->mime ?: (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream');
        $inline = ['application/pdf','image/png','image/jpeg','image/jpg','image/gif','image/webp','text/plain'];
        // Protected (view-only) documents are always served inline and never cached/downloaded.
        if ($doc->protected) {
            return response()->file($path, [
                'Content-Type'=>$mime ?: 'application/pdf',
                'Content-Disposition'=>'inline; filename="'.addslashes($doc->file_name ?: basename($path)).'"',
                'Cache-Control'=>'no-store, no-cache, must-revalidate, private',
                'Pragma'=>'no-cache',
                'X-Content-Type-Options'=>'nosniff',
            ]);
        }
        if (in_array($mime, $inline)) {
            return response()->file($path, ['Content-Type'=>$mime, 'Content-Disposition'=>'inline; filename="'.addslashes($doc->file_name ?: basename($path)).'"']);
        }
        return response()->download($path, $doc->file_name ?: basename($path));
    }

    // ---- admin (or staff user granted the section) ----
    public function adminIndex(Request $r) {
        abort_unless($r->user()->canSection('laundre-onboarding'), 403);
        return Onboarding::with('user:id,name,email,phone,role,location_id,nda_signed_at,access_expires_at')->orderByDesc('created_at')->get();
    }
    public function moveStage(Request $r, Onboarding $onboarding) {
        $d = $r->validate(['crm_stage'=>'required|in:invited,onboarding,pending_approval,approved']);
        $onboarding->crm_stage = $d['crm_stage']; $onboarding->save();
        return response()->json(['ok'=>true,'onboarding'=>$onboarding]);
    }
    public function investors(Request $r) {
        abort_unless($r->user()->canSection('laundre-investors'), 403);
        return Onboarding::with('user:id,name,email,phone,role,nda_signed_at')->where('type','investor')->orderByDesc('interest_submitted_at')->get();
    }

    // ---- admin content manager: videos + documents + view analytics ----
    private function gateContent(Request $r) { abort_unless($r->user()->canSection('laundre-onboarding-content'), 403); }
    public function adminContent(Request $r) {
        $this->gateContent($r);
        $docs = OnboardingDocument::withCount('views')->orderBy('audience')->orderBy('position')->orderBy('id')->get()
            ->map(function ($d) {
                return ['id'=>$d->id,'title'=>$d->title,'file_name'=>$d->file_name,'audience'=>$d->audience,
                        'protected'=>(bool)$d->protected,
                        'ext'=>strtolower(pathinfo($d->file_name ?: $d->file_path, PATHINFO_EXTENSION)),
                        'views_count'=>$d->views_count,
                        'unique_viewers'=>$d->views()->distinct('user_id')->count('user_id'),
                        'url'=>url('/onboarding-doc/'.$d->id)];
            });
        return response()->json([
            'videos'=>['franchisee'=>(string)Setting::get('onboarding_video_franchisee',''),'investor'=>(string)Setting::get('onboarding_video_investor',''),'cleaner'=>(string)Setting::get('intro_video_cleaner',''),'maintenance'=>(string)Setting::get('intro_video_maintenance','')],
            'documents'=>$docs,
        ]);
    }
    public function setVideos(Request $r) {
        $this->gateContent($r);
        $d = $r->validate(['franchisee'=>'nullable|string','investor'=>'nullable|string','cleaner'=>'nullable|string','maintenance'=>'nullable|string']);
        if (array_key_exists('franchisee',$d))  Setting::put('onboarding_video_franchisee', (string)($d['franchisee']??''));
        if (array_key_exists('investor',$d))    Setting::put('onboarding_video_investor',   (string)($d['investor']??''));
        if (array_key_exists('cleaner',$d))     Setting::put('intro_video_cleaner',         (string)($d['cleaner']??''));
        if (array_key_exists('maintenance',$d)) Setting::put('intro_video_maintenance',     (string)($d['maintenance']??''));
        return response()->json(['ok'=>true]);
    }
    public function storeDoc(Request $r) {
        $this->gateContent($r);
        $d = $r->validate([
            'title'=>'required|string','file_path'=>'required|string','file_name'=>'nullable|string',
            'mime'=>'nullable|string','audience'=>'required|in:franchisee,investor,both','protected'=>'nullable|boolean',
        ]);
        $d['protected'] = (bool)($d['protected'] ?? false);
        $d['position'] = (int) (OnboardingDocument::where('audience',$d['audience'])->max('position') + 1);
        $doc = OnboardingDocument::create($d);
        return response()->json($doc, 201);
    }
    public function destroyDoc(Request $r, OnboardingDocument $doc) {
        $this->gateContent($r);
        $doc->delete();
        return response()->noContent();
    }
    public function docViews(Request $r, OnboardingDocument $doc) {
        $this->gateContent($r);
        return DocumentView::with('user:id,name,email,role')->where('onboarding_document_id',$doc->id)
            ->orderByDesc('viewed_at')->limit(200)->get()
            ->map(fn($v)=>['user'=>optional($v->user)->name,'email'=>optional($v->user)->email,'role'=>optional($v->user)->role,'viewed_at'=>optional($v->viewed_at)->toISOString()]);
    }
}
