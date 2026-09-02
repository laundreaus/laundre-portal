<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, Notifiable, SoftDeletes;
    protected $fillable = ['name','email','phone','password','role','location_id','location_ids','invite_token','sections',
        'nda_signed_at','nda_signer_name','nda_signature','nda_address','access_expires_at','member_no','investor_location_ids'];
    protected $hidden = ['password','remember_token','invite_token','nda_signature'];
    protected function casts(): array { return [
        'email_verified_at'=>'datetime','password'=>'hashed','sections'=>'array',
        'nda_signed_at'=>'datetime','access_expires_at'=>'datetime','investor_location_ids'=>'array','location_ids'=>'array',
    ]; }
    public function location() { return $this->belongsTo(Location::class); }
    public function onboarding() { return $this->hasOne(Onboarding::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function hasRole(string $role): bool { return $this->role === $role; }
    // Prospect roles that go through the onboarding portal (NDA, welcome video, documents).
    public function isOnboarding(): bool { return in_array($this->role, ['potential_franchisee','potential_investor']); }
    public function isInvestor(): bool { return $this->role === 'investor'; }
    public function canSection(string $key): bool { return $this->isAdmin() || ($this->role === 'user' && in_array($key, (array)($this->sections ?? []))); }

    // Roles that must sign the NDA before they can use the portal.
    public function ndaRequiredRole(): bool { return in_array($this->role, ['potential_franchisee','potential_investor','cleaner','maintenance']); }
    public function needsNda(): bool { return $this->ndaRequiredRole() && $this->nda_signed_at === null; }
    // Roles subject to the 21-day access window (prospects only).
    public function timeLimitedRole(): bool { return in_array($this->role, ['potential_franchisee','potential_investor']); }
    public function isAccessLocked(): bool {
        return $this->timeLimitedRole() && $this->access_expires_at !== null && now()->greaterThan($this->access_expires_at);
    }
    // Which onboarding-document audience this user should see.
    public function docAudience(): string { return in_array($this->role, ['investor','potential_investor']) ? 'investor' : 'franchisee'; }
    // All laundromats this user is assigned to (multi-site). Falls back to the single primary location.
    public function locationIds(): array {
        $ids = array_filter(array_map('intval', (array)($this->location_ids ?? [])));
        if (!$ids && $this->location_id) $ids = [(int)$this->location_id];
        return array_values(array_unique($ids));
    }
    // Laundromats an investor can see on their data dashboard (their assigned sites).
    public function investorLocationIds(): array {
        $ids = array_filter(array_map('intval', (array)($this->investor_location_ids ?? [])));
        if (!$ids) $ids = $this->locationIds();
        return array_values(array_unique($ids));
    }

    // ---- Digital membership card ----
    // Only these roles get a membership card.
    public const CARD_ROLES = ['investor','franchisee','user','admin'];
    public function hasCard(): bool { return in_array($this->role, self::CARD_ROLES); }
    // Visual theme: investor=black/white, franchisee=green/cream, user & admin=cream/green
    public function cardTheme(): string {
        if ($this->role === 'investor') return 'investor';
        if ($this->role === 'franchisee') return 'franchisee';
        return 'user';
    }
    public function cardTier(): string {
        return ['investor'=>'Investor','franchisee'=>'Franchisee','user'=>'Member','admin'=>'Team'][$this->role] ?? 'Member';
    }
    // Assign the next LDR-000000 member number if eligible and not already set.
    public function assignMemberNo(): void {
        if (!$this->hasCard() || $this->member_no) return;
        $max = 0;
        foreach (self::whereNotNull('member_no')->where('member_no','like','LDR-%')->pluck('member_no') as $mn) {
            $n = (int) substr($mn, 4); if ($n > $max) $max = $n;
        }
        $this->member_no = 'LDR-'.str_pad($max + 1, 6, '0', STR_PAD_LEFT);
        $this->save();
    }
}
