<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\PipelineCard;
use App\Models\User;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Franchise enquiries inbox (the "Contacts" section above Franchise CRM). WordPress and
 * manual enquiries land here first; an admin reviews each and, when qualified, approves it —
 * which creates a card in the CRM "Leads" column tagged by user type.
 */
class EnquiryController extends Controller
{
    public function index(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        return Enquiry::orderByDesc('id')->get();
    }

    public function show(Request $r, Enquiry $enquiry) {
        abort_unless($r->user()->isAdmin(), 403);
        return $enquiry;
    }

    public function store(Request $r) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $r->validate([
            'name'=>'required|string','email'=>'nullable|email','phone'=>'nullable|string',
            'city'=>'nullable|string','message'=>'nullable|string','type'=>'nullable|string',
        ]);
        $d['type'] = in_array(strtolower($d['type'] ?? ''), ['franchisee','investor','general']) ? strtolower($d['type']) : 'general';
        $d['source'] = 'manual';
        $d['status'] = 'new';
        return Enquiry::create($d);
    }

    public function approve(Request $r, Enquiry $enquiry) {
        abort_unless($r->user()->isAdmin(), 403);
        if ($enquiry->status === 'approved' && $enquiry->card_id) {
            return response()->json($enquiry);
        }
        // Create the contact as a user in User Management (silently — no email at Lead stage).
        // The login + NDA email fires later when the card is moved to "NDA sent".
        $user = null;
        if ($enquiry->email) {
            $role = ($enquiry->type === 'investor') ? 'potential_investor' : 'potential_franchisee';
            $user = User::where('email', $enquiry->email)->first();
            if (!$user) {
                $user = User::create([
                    'name'         => $enquiry->name,
                    'email'        => $enquiry->email,
                    'phone'        => $enquiry->phone,
                    'role'         => $role,
                    'password'     => Hash::make(bin2hex(random_bytes(16))),
                    'invite_token' => bin2hex(random_bytes(24)),
                ]);
                if (method_exists($user, 'assignMemberNo')) $user->assignMemberNo();
                Onboarding::firstOrCreate(
                    ['user_id' => $user->id],
                    ['type' => $role === 'potential_investor' ? 'investor' : 'franchisee', 'crm_stage' => 'lead']
                );
            }
        }
        $card = PipelineCard::create([
            'name'    => $enquiry->name,
            'contact' => $enquiry->name,
            'email'   => $enquiry->email,
            'phone'   => $enquiry->phone,
            'city'    => $enquiry->city,
            'notes'   => $enquiry->message,
            'stage'   => 'leads',
            'type'    => in_array($enquiry->type, ['franchisee','investor']) ? $enquiry->type : 'general',
            'source'  => $enquiry->source,
            'user_id' => $user?->id,
            'position'=> (int)(PipelineCard::where('stage','leads')->max('position') + 1),
        ]);
        $enquiry->update(['status'=>'approved','card_id'=>$card->id]);
        return response()->json($enquiry);
    }

    public function update(Request $r, Enquiry $enquiry) {
        abort_unless($r->user()->isAdmin(), 403);
        $d = $r->validate(['status'=>'nullable|in:new,approved,dismissed','message'=>'nullable|string','type'=>'nullable|string']);
        $enquiry->update($d);
        return $enquiry;
    }

    public function destroy(Request $r, Enquiry $enquiry) {
        abort_unless($r->user()->isAdmin(), 403);
        $enquiry->delete();
        return response()->noContent();
    }
}
