<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentView extends Model {
    protected $fillable = ['onboarding_document_id','user_id','viewed_at'];
    protected function casts(): array { return ['viewed_at'=>'datetime']; }
    public function document() { return $this->belongsTo(OnboardingDocument::class,'onboarding_document_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
