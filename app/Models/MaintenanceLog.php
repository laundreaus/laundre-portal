<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MaintenanceLog extends Model {
    protected $fillable = ['location_id','user_id','by','date','items','notes','issues','photos','geo'];
    protected $casts = ['date'=>'date','items'=>'array','photos'=>'array','geo'=>'array'];
    public function location() { return $this->belongsTo(Location::class); }
}
