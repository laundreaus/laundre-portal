<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Location extends Model {
    protected $fillable = ['name','address','lat','lng','radius','unit','status','date_approved','notes'];
    protected $casts = ['lat'=>'float','lng'=>'float','radius'=>'float','date_approved'=>'date'];
    public function sales() { return $this->hasMany(Sale::class); }
}
