<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PipelineCard;
use App\Models\Setting;
use App\Models\User;
use App\Models\Onboarding;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class CrmController extends Controller {
    // The first three columns are fixed/auto-driven; the admin builds the rest.
    // Leads (incl. WordPress website enquiries) land in 'leads'; a qualified lead moves to
    // 'nda_sent'; once the NDA is signed it moves to 'reviewing_documents'.
    public const LOCKED = [
        ['id'=>'leads','name'=>'Leads','color'=>'#C4703F','locked'=>true],
        ['id'=>'nda_sent','name'=>'NDA sent','color'=>'#8a9790','locked'=>true],
        ['id'=>'reviewing_documents','name'=>'Reviewing Documents','color'=>'#6b8e9e','locked'=>true],
    ];
    private const LOCKED_IDS = ['leads','nda_sent','reviewing_documents'];
    private function columns(): array {
        $cols = Setting::get('pipeline_columns', null);
        if (!is_array($cols) || !count($cols)) { $cols = self::LOCKED; Setting::put('pipeline_columns', $cols); }
        // guarantee the locked columns are present and first, in order
        $out = [];
        foreach (self::LOCKED as $lc) { $out[] = $lc; }
        foreach ($cols as $c) { if (!in_array($c['id'], self::LOCKED_IDS)) { $c['locked'] = false; $out[] = $c; } }
        return $out;
    }
    public function index() {
        return response()->json(['columns'=>$this->columns(), 'cards'=>PipelineCard::orderBy('position')->orderBy('id')->get()]);
    }
    public function storeCard(Request $r) {
        $d = $this->rules($r);
        $d['position'] = (int) (PipelineCard::where('stage',$d['stage'])->max('position') + 1);
        return PipelineCard::create($d);
    }
    public function updateCard(Request $r, PipelineCard $card) {
        $card->update($this->rules($r));
        return $card;
    }
    public function moveCard(Request $r, PipelineCard $card) {
        $d = $r->validate(['stage'=>'required|string','position'=>'nullable|integer']);
        $prev = $card->stage;
        $card->stage = $d['stage'];
        if (isset($d['position'])) $card->position = $d['position'];
        if ($prev !== $d['stage']) $card->stage_changed_at = now();
        $card->save();
        // Automation: entering "NDA sent" issues login access + emails the prospect.
        if ($prev !== 'nda_sent' && $d['stage'] === 'nda_sent') {
            $this->onNdaSent($card);
        }
        return $card->fresh();
    }

    /**
     * Fired when a card lands in "NDA sent": the qualified lead becomes a prospect account
     * (potential franchisee / investor), gets a fresh invite link, and receives an email
     * with their login details. Idempotent — the automation flag stops it re-firing.
     */
    private function onNdaSent(PipelineCard $card): void
    {
        $auto = $card->automation ?? [];
        if (!empty($auto['nda_email_sent']) || empty($card->email)) return;

        $role = ($card->type === 'investor') ? 'potential_investor' : 'potential_franchisee';
        try {
            $user = User::where('email', $card->email)->first();
            if (!$user) {
                $user = User::create([
                    'name'         => $card->name ?: $card->contact ?: $card->email,
                    'email'        => $card->email,
                    'phone'        => $card->phone,
                    'role'         => $role,
                    'password'     => Hash::make(bin2hex(random_bytes(16))),
                    'invite_token' => bin2hex(random_bytes(24)),
                ]);
                if (method_exists($user, 'assignMemberNo')) $user->assignMemberNo();
            } else {
                $user->invite_token = bin2hex(random_bytes(24));
                if (in_array($user->role, ['user','potential_franchisee','potential_investor'])) $user->role = $role;
                $user->save();
            }
            Onboarding::firstOrCreate(
                ['user_id' => $user->id],
                ['type' => $role === 'potential_investor' ? 'investor' : 'franchisee', 'crm_stage' => 'nda_sent']
            );
            $card->user_id = $user->id;
            $card->type = $role === 'potential_investor' ? 'investor' : 'franchisee';

            AdminNotifier::sendPlain(
                [$user->email],
                'Your Laundré franchising login',
                'Welcome to the Laundré franchising portal',
                'Thanks for your interest in a Laundré franchise. Your account is ready — set your password and sign your NDA using the link below.',
                [
                    ['Name', $user->name],
                    ['Set up your login', url('/welcome/'.$user->invite_token)],
                ],
                'Once your NDA is signed we\'ll move you into document review. Reply to this email any time with questions.'
            );
            $auto['nda_email_sent'] = true;
            $card->automation = $auto;
            $card->save();
        } catch (\Throwable $e) {
            \Log::warning('[CRM] onNdaSent failed for card '.$card->id.': '.$e->getMessage());
        }
    }
    public function destroyCard(PipelineCard $card) { $card->delete(); return response()->noContent(); }
    public function saveColumns(Request $r) {
        $d = $r->validate(['columns'=>'required|array','columns.*.id'=>'required|string','columns.*.name'=>'required|string','columns.*.color'=>'nullable|string']);
        // always keep the two locked columns at the front
        $custom = array_values(array_filter($d['columns'], fn($c)=>!in_array($c['id'],self::LOCKED_IDS)));
        $cols = array_merge(self::LOCKED, array_map(fn($c)=>['id'=>$c['id'],'name'=>$c['name'],'color'=>$c['color']??'#6E8B7B','locked'=>false], $custom));
        Setting::put('pipeline_columns', $cols);
        return response()->json(['columns'=>$cols]);
    }
    private function rules(Request $r): array {
        return $r->validate([
            'name'=>'required|string','contact'=>'nullable|string','email'=>'nullable|string',
            'phone'=>'nullable|string','city'=>'nullable|string','notes'=>'nullable|string','stage'=>'required|string',
            'type'=>'nullable|string','source'=>'nullable|string',
        ]);
    }

    /**
     * Public WordPress lead intake webhook. The website POSTs an enquiry here and it lands
     * as a card in the "Leads" column, tagged by user type. Protected by an optional shared
     * secret stored in Setting('wp_lead_secret') — leave unset until the WordPress side is
     * connected, then set it and send it as ?secret= or the X-Laundre-Secret header.
     */
    public function wpLead(Request $r) {
        $secret = Setting::get('wp_lead_secret', null);
        if ($secret) {
            $given = $r->header('X-Laundre-Secret') ?: $r->input('secret');
            abort_unless(hash_equals((string)$secret, (string)$given), 403, 'Bad secret');
        }
        $d = $r->validate([
            'name'=>'required|string','email'=>'nullable|email','phone'=>'nullable|string',
            'city'=>'nullable|string','message'=>'nullable|string','type'=>'nullable|string',
        ]);
        $type = strtolower($d['type'] ?? '');
        $type = in_array($type, ['franchisee','investor','general']) ? $type : 'general';
        // Website enquiries land in the Contacts inbox first; an admin approves them into the CRM Leads column.
        $enq = \App\Models\Enquiry::create([
            'name'    => $d['name'],
            'email'   => $d['email'] ?? null,
            'phone'   => $d['phone'] ?? null,
            'city'    => $d['city'] ?? null,
            'message' => trim(($d['message'] ?? '')),
            'type'    => $type,
            'source'  => 'wordpress',
            'status'  => 'new',
        ]);
        AdminNotifier::notify('lead_received', 'New website enquiry: '.$enq->name, [
            'summary' => $enq->name.' enquired via the website ('.$type.').',
            'name'=>$enq->name, 'email'=>$enq->email, 'type'=>$type,
            'note'=> $enq->message ?: null,
        ]);
        return response()->json(['ok'=>true, 'id'=>$enq->id]);
    }
}
