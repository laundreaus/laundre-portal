<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, Notifiable;
    protected $fillable = ['name','email','password','role','location_id'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed']; }
    public function location() { return $this->belongsTo(Location::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function is(string $role): bool { return $this->role === $role; }
}
