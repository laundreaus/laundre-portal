<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Task extends Model {
    protected $fillable = ['title','description','status','due_date','file_path','file_name','assignee_id','created_by'];
    protected $casts = ['due_date'=>'date'];
    public function assignee() { return $this->belongsTo(User::class,'assignee_id'); }
    public function creator()  { return $this->belongsTo(User::class,'created_by'); }
}
