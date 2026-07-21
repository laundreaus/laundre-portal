<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Sale extends Model {
    protected $fillable = ['location_id','date','revenue','txns'];
    protected $casts = ['date'=>'date','revenue'=>'float','txns'=>'integer'];
    public function location() { return $this->belongsTo(Location::class); }
}
