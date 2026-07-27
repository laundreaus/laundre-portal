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
    public function store(Request $r) {
        $data = $this->rules($r, true);
        $onboarding = in_array($data['role'], ['potential_franchisee','investor']);
        if ($onboarding) {
            $data['invite_token'] = bin2hex(random_bytes(24));
            $data['password'] = Hash::make(bin2hex(random_bytes(16))); // placeholder until they set their own
        } else {
            abort_if(empty($data['password']), 422, 'Password is required for this role.');
            $data['password'] = Hash::make($data['password']);
        }
        $user = User::create($data);
        if ($onboarding) {
            Onboarding::firstOrCreate(['user_id'=>$user->id], ['type'=>$data['role']==='investor'?'investor':'franchisee','crm_stage'=>'invited']);
            if ($data['role']==='potential_franchisee') {
                \App\Models\PipelineCard::create(['name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,'stage'=>'nda_sent','user_id'=>$user->id]);
            }
            $user->makeVisible('invite_token');
            $arr = $user->toArray();
            $arr['invite_url'] = url('/welcome/'.$user->invite_token);
            return response()->json($arr, 201);
        }
        return $user;
    }
    public function update(Request $r, User $user) {
        $data = $this->rules($r, false);
        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }
        $user->update($data);
        return $user;
    }
    public function reinvite(Request $r, User $user) {
        abort_unless($user->isOnboarding(), 422, 'Only potential franchisees or investors have invite links.');
        $user->invite_token = bin2hex(random_bytes(24));
        $user->save();
        return response()->json(['ok'=>true,'invite_url'=>url('/welcome/'.$user->invite_token)]);
    }
    public function destroy(User $user) { $user->delete(); return response()->noContent(); }
    private function rules(Request $r, bool $creating): array {
        return $r->validate([
            'name'=>'required|string',
            'email'=>'required|string|unique:users,email'.($creating?'':(','.$r->route('user')->id)),
            'phone'=>'nullable|string',
            'password'=>'nullable|string|min:8',
            'role'=>'required|in:admin,franchisee,cleaner,maintenance,potential_franchisee,investor,user',
            'location_id'=>'nullable|exists:locations,id',
            'sections'=>'nullable|array',
            'sections.*'=>'string',
        ]);
    }
}
