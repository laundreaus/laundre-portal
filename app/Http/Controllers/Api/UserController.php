<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class UserController extends Controller {
    public function index() { return User::with('location:id,name')->orderBy('name')->get(); }
    public function store(Request $r) {
        $data = $this->rules($r, true);
        $data['password'] = Hash::make($data['password']);
        return User::create($data);
    }
    public function update(Request $r, User $user) {
        $data = $this->rules($r, false);
        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }
        $user->update($data);
        return $user;
    }
    public function destroy(User $user) { $user->delete(); return response()->noContent(); }
    private function rules(Request $r, bool $creating): array {
        return $r->validate([
            'name'=>'required|string',
            'email'=>'required|string|unique:users,email'.($creating?'':(','.$r->route('user')->id)),
            'password'=>($creating?'required':'nullable').'|string|min:6',
            'role'=>'required|in:admin,franchisee,cleaner,maintenance',
            'location_id'=>'nullable|exists:locations,id',
        ]);
    }
}
