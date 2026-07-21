<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Guide extends Model {
    protected $fillable = ['title','category','visibility','file_path','file_name','link','note'];
}
