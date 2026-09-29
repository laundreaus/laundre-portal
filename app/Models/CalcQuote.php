<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CalcQuote extends Model {
    protected $fillable = ['token','name','sqm','reference','client_name','client_email','payload','html','total','status','signer_name','signature','signed_at','signed_ip','created_by'];
    protected $casts = ['payload'=>'array','signed_at'=>'datetime','total'=>'float'];
}
