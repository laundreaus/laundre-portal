<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Sale extends Model {
    protected $fillable = ['location_id','date','revenue','txns'];
    // Serialize as a plain calendar date (no time / timezone) so the API returns e.g. "2026-09-01"
    // rather than a UTC datetime, which otherwise shifts the day back in +10 (Brisbane) time.
    protected $casts = ['date'=>'date:Y-m-d','revenue'=>'float','txns'=>'integer'];
    public function location() { return $this->belongsTo(Location::class); }
}
