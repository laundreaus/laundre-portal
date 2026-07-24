<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MaintenanceDoc extends Model {
    protected $table = 'maintenance_docs';
    protected $fillable = ['title','category','machine','note','file_path','file_name','file_size'];
}
