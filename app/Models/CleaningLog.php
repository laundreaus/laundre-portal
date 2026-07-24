<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CleaningLog extends Model {
    protected $fillable = ['location_id','user_id','by','date','items','labels','notes','issues','photos','geo'];
    protected $casts = ['date'=>'date','items'=>'array','labels'=>'array','photos'=>'array','geo'=>'array'];
    public function location() { return $this->belongsTo(Location::class); }
}
