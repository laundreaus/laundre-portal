<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Milestone extends Model {
    protected $fillable = ['location_id','title','detail','target_date','status','position'];
    protected $casts = ['target_date'=>'date:Y-m-d','position'=>'integer'];
}
