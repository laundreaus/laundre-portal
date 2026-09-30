<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class UserController extends Controller {
    public function index() {
        return User::with(['location:id,name','onboarding'])->orderBy('name')->get()
            ->makeVisible('invite_token')
            ->map(function ($u) {
                $arr = $u->toArray();
                $arr['invite_url'] = $u->invite_token ? url('/welcome/'.$u->invite_token) : null;
                return $arr;
            });
    }
    private const INVITE_ROLES = ['potential_franchisee','potential_investor','cleaner','maintenance'];
    public function store(Request $r) {
        $data = $this->normalizeLocations($this->rules($r, true));
        $role = $data['role'];
        $onboarding = in_array($role, ['potential_franchisee','potential_investor']);
        // Password is optional for every non-admin account. If the admin leaves it blank we
        // email (and surface) a one-time "set your password" link instead — so they never have
        // to set a password by hand. Prospects always go through the invite/NDA flow regardless.
        $hasPassword = !empty($data['password']) && !$onboarding;
        $sendInvite = ($role !== 'admin') && !$hasPassword;
        if ($hasPassword) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make(bin2hex(random_bytes(16))); // placeholder until they set their own
        }
        if ($sendInvite) { $data['invite_token'] = bin2hex(random_bytes(24)); }
        $user = User::create($data);
        $user->assignMemberNo(); // issues LDR-000000 for card-eligible roles (investor/franchisee/user/admin)
        if ($onboarding) {
            Onboarding::firstOrCreate(['user_id'=>$user->id], ['type'=>in_array($role,['investor','potential_investor'])?'investor':'franchisee','crm_stage'=>'invited']);
            if ($role==='potential_franchisee') {
                \App\Models\PipelineCard::create(['name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,'stage'=>'nda_sent','user_id'=>$user->id]);
            }
        }
        // Welcome email: asks the new user to set their password and log in.
        if ($sendInvite && $user->email) {
            try { $this->sendWelcome($user); } catch (\Throwable $e) { /* email must never block account creation */ }
        }
        if ($user->invite_token) {
            $user->makeVisible('invite_token');
            $arr = $user->toArray();
            $arr['invite_url'] = url('/welcome/'.$user->invite_token);
            return response()->json($arr, 201);
        }
        return $user;
    }
    /** Email a new user a one-time link to set their password and log in. */
    private function sendWelcome(User $u): void {
        $first = trim(strtok((string)$u->name, ' ') ?: '');
        $url   = url('/welcome/'.$u->invite_token);
        \App\Services\AdminNotifier::sendPlain(
            [$u->email],
            'Welcome to Laundré — set your password',
            'Welcome to Laundré'.($first ? ', '.$first : ''),
            'An account has been created for you on the Laundré portal. Use the link below to choose your password and log in.',
            [['Set your password & log in', $url]],
            'This link is unique to you and can be used once. If you did not expect this email, you can ignore it.'
        );
    }
    public function update(Request $r, User $user) {
        $data = $this->normalizeLocations($this->rules($r, false));
        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }
        // Only persist investor location assignments for the investor role.
        if (($data['role'] ?? $user->role) !== 'investor') { $data['investor_location_ids'] = null; }
        $wasCard = $user->hasCard();
        $user->update($data);
        // Upgrading someone into a card-eligible role (e.g. investor) issues their member number.
        if (!$wasCard && $user->hasCard() && !$user->member_no) { $user->assignMemberNo(); }
        // Ensure any onboarding record follows the audience if the role changed.
        if (in_array($user->role, ['potential_franchisee','potential_investor','investor'])) {
            $type = in_array($user->role, ['investor','potential_investor']) ? 'investor' : 'franchisee';
            \App\Models\Onboarding::where('user_id', $user->id)->update(['type' => $type]);
        }
        return $user->fresh();
    }
    public function reinvite(Request $r, User $user) {
        abort_unless(in_array($user->role, self::INVITE_ROLES), 422, 'Only invited accounts (prospects, investors, cleaners, maintenance) have invite links.');
        $user->invite_token = bin2hex(random_bytes(24));
        $user->save();
        return response()->json(['ok'=>true,'invite_url'=>url('/welcome/'.$user->invite_token)]);
    }
    public function destroy(User $user) { $user->delete(); return response()->noContent(); }
    private function rules(Request $r, bool $creating): array {
        return $r->validate([
            'name'=>'required|string',
            // Uniqueness ignores soft-deleted users so a removed account's email can be reused.
            'email'=>'required|string|unique:users,email,'.($creating?'NULL':$r->route('user')->id).',id,deleted_at,NULL',
            'phone'=>'nullable|string',
            'password'=>'nullable|string|min:8',
            'role'=>'required|in:admin,franchisee,cleaner,maintenance,potential_franchisee,potential_investor,investor,user',
            'location_id'=>'nullable|exists:locations,id',
            'location_ids'=>'nullable|array',
            'location_ids.*'=>'integer|exists:locations,id',
            'sections'=>'nullable|array',
            'sections.*'=>'string',
            'investor_location_ids'=>'nullable|array',
            'investor_location_ids.*'=>'integer|exists:locations,id',
        ]);
    }
    // Keep the primary location_id in sync with the multi-site location_ids list (first = primary).
    private function normalizeLocations(array $data): array {
        if (array_key_exists('location_ids', $data)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$data['location_ids']))));
            $data['location_ids'] = $ids ?: null;
            if ($ids) $data['location_id'] = $ids[0];
        }
        return $data;
    }
}
