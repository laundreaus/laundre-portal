<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Machine extends Model {
    protected $fillable = ['location_id','name','type','model','serial','status','source','fagor_id','meta'];
    protected $casts = ['meta'=>'array'];
}
