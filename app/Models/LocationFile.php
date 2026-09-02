<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LocationFile extends Model {
    protected $fillable = ['location_id','category','name','file_path','file_name','size','expiry','uploaded_by'];
    protected $casts = ['expiry'=>'date:Y-m-d','size'=>'integer'];
    public function location() { return $this->belongsTo(Location::class); }
}
