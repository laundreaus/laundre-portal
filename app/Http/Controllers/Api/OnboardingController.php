<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Onboarding;
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
        $o = $this->ob($u);
        return response()->json([
            'user' => ['name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone,'role'=>$u->role],
            'kind' => $this->typeFor($u),
            'onboarding' => $o,
        ]);
    }

    public function sign(Request $r) {
        $u = $r->user();
        abort_unless(in_array($u->role,['potential_franchisee','investor']), 403);
        $d = $r->validate([
            'name'=>'required|string','email'=>'nullable|string','phone'=>'nullable|string',
            'address'=>'required|string','typed_name'=>'required|string','signature'=>'required|string',
        ]);
        $o = $this->ob($u);
        $o->fill([
            'nda_name'=>$d['name'],'nda_email'=>$d['email']??$u->email,'nda_phone'=>$d['phone']??$u->phone,
            'nda_address'=>$d['address'],'nda_typed_name'=>$d['typed_name'],'nda_signature'=>$d['signature'],
            'nda_ip'=>$r->ip(),'nda_signed_at'=>now(),'contact_due_at'=>now()->addDays(14),
        ]);
        if ($o->crm_stage === 'invited') $o->crm_stage = 'onboarding';
        $o->save();
        return response()->json(['ok'=>true,'onboarding'=>$o]);
    }

    public function track(Request $r) {
        $u = $r->user();
        $d = $r->validate(['event'=>'required|in:video,doc']);
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

    // ---- admin (or staff user granted the section) ----
    public function adminIndex(Request $r) {
        abort_unless($r->user()->canSection('laundre-onboarding'), 403);
        return Onboarding::with('user:id,name,email,phone,role,location_id')->orderByDesc('created_at')->get();
    }
    public function moveStage(Request $r, Onboarding $onboarding) {
        $d = $r->validate(['crm_stage'=>'required|in:invited,onboarding,pending_approval,approved']);
        $onboarding->crm_stage = $d['crm_stage']; $onboarding->save();
        return response()->json(['ok'=>true,'onboarding'=>$onboarding]);
    }
    public function investors(Request $r) {
        abort_unless($r->user()->canSection('laundre-investors'), 403);
        return Onboarding::with('user:id,name,email,phone,role')->where('type','investor')->orderByDesc('interest_submitted_at')->get();
    }
}
