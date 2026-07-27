<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PipelineCard extends Model {
    protected $fillable = ['name','contact','email','phone','city','notes','stage','user_id','position'];
    public function user() { return $this->belongsTo(User::class); }
}
