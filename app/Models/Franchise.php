<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Franchise extends Model {
    protected $fillable = ['name','location','contact','source_location_id','sections'];
    protected $casts = ['sections'=>'array'];
}
