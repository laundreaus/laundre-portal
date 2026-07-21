<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookkeepingEntry extends Model {
    protected $fillable = ['location_id','fy','q1','q2','q3','q4','annual','dates','files'];
    protected $casts = ['dates'=>'array','files'=>'array'];
    public function location() { return $this->belongsTo(Location::class); }
}
