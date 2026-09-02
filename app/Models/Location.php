<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Location extends Model {
    protected $fillable = ['name','address','lat','lng','radius','unit','status','date_approved','notes','about','modules'];
    protected $casts = ['lat'=>'float','lng'=>'float','radius'=>'float','date_approved'=>'date','about'=>'array','modules'=>'array'];
    public function sales() { return $this->hasMany(Sale::class); }
    public function files() { return $this->hasMany(LocationFile::class); }
    // null / missing key => module is ON by default.
    public function moduleOn(string $key): bool {
        $m = $this->modules;
        if (!is_array($m) || !array_key_exists($key, $m)) return true;
        return (bool) $m[$key];
    }
}
