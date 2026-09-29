<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OnboardingDocument extends Model {
    protected $fillable = ['title','file_path','file_name','mime','audience','position','protected'];
    protected $casts = ['protected'=>'boolean'];
    public function views() { return $this->hasMany(DocumentView::class); }
}
