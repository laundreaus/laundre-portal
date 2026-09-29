<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Quote extends Model {
    protected $fillable = ['location_id','reference','client_name','client_email','notes','items','total','status',
        'token','signer_name','signature','signed_at','signed_ip','invoice_schedule','xero_status','created_by'];
    protected $casts = ['items'=>'array','invoice_schedule'=>'array','total'=>'float','signed_at'=>'datetime'];
}
