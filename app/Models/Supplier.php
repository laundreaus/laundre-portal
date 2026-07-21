<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model {
    protected $fillable = ['name','category','contact','phone','email','website','notes','locations'];
    protected $casts = ['locations'=>'array'];
}
