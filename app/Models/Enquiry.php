<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Enquiry extends Model {
    protected $fillable = ['name','email','phone','city','message','type','source','status','card_id'];
}
