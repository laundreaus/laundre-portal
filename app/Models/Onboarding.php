<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Onboarding extends Model {
    protected $fillable = ['user_id','type','crm_stage','first_login_at','nda_signed_at','video_watched_at',
        'first_doc_opened_at','contact_due_at','nda_name','nda_email','nda_phone','nda_address','nda_typed_name',
        'nda_signature','nda_ip','interest_min','interest_max','interest_note','interest_submitted_at'];
    protected $casts = [
        'first_login_at'=>'datetime','nda_signed_at'=>'datetime','video_watched_at'=>'datetime',
        'first_doc_opened_at'=>'datetime','contact_due_at'=>'datetime','interest_submitted_at'=>'datetime',
        'interest_min'=>'decimal:2','interest_max'=>'decimal:2',
    ];
    public function user() { return $this->belongsTo(User::class); }
}
