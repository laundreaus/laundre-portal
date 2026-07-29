<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, Notifiable;
    protected $fillable = ['name','email','phone','password','role','location_id','invite_token','sections',
        'nda_signed_at','nda_signer_name','nda_signature','nda_address','access_expires_at'];
    protected $hidden = ['password','remember_token','invite_token','nda_signature'];
    protected function casts(): array { return [
        'email_verified_at'=>'datetime','password'=>'hashed','sections'=>'array',
        'nda_signed_at'=>'datetime','access_expires_at'=>'datetime',
    ]; }
    public function location() { return $this->belongsTo(Location::class); }
    public function onboarding() { return $this->hasOne(Onboarding::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function hasRole(string $role): bool { return $this->role === $role; }
    public function isOnboarding(): bool { return in_array($this->role, ['potential_franchisee','investor']); }
    public function canSection(string $key): bool { return $this->isAdmin() || ($this->role === 'user' && in_array($key, (array)($this->sections ?? []))); }

    // Roles that must sign the NDA before they can use the portal.
    public function ndaRequiredRole(): bool { return in_array($this->role, ['potential_franchisee','investor','cleaner','maintenance']); }
    public function needsNda(): bool { return $this->ndaRequiredRole() && $this->nda_signed_at === null; }
    // Roles subject to the 21-day access window.
    public function timeLimitedRole(): bool { return in_array($this->role, ['potential_franchisee','investor']); }
    public function isAccessLocked(): bool {
        return $this->timeLimitedRole() && $this->access_expires_at !== null && now()->greaterThan($this->access_expires_at);
    }
    // Which onboarding-document audience this user should see.
    public function docAudience(): string { return $this->role === 'investor' ? 'investor' : 'franchisee'; }
}
