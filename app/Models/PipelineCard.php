<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PipelineCard extends Model {
    protected $fillable = ['name','contact','email','phone','city','notes','stage','user_id','position','type','source','stage_changed_at','automation'];
    protected $casts = ['automation'=>'array','stage_changed_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
