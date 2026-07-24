<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, Notifiable;
    protected $fillable = ['name','email','phone','password','role','location_id','invite_token'];
    protected $hidden = ['password','remember_token','invite_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed']; }
    public function location() { return $this->belongsTo(Location::class); }
    public function onboarding() { return $this->hasOne(Onboarding::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function hasRole(string $role): bool { return $this->role === $role; }
    public function isOnboarding(): bool { return in_array($this->role, ['potential_franchisee','investor']); }
}
