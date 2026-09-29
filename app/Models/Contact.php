<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model {
    protected $fillable = ['location_id','name','company','title','email','phone','category','notes','source','external_id','last_contact_at','created_by'];
    protected $casts = ['last_contact_at'=>'datetime'];
}
