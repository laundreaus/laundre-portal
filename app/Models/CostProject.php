<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CostProject extends Model {
    protected $fillable = ['name','location','sqm','margin_pct','gst_pct','items'];
    protected $casts = ['items'=>'array','margin_pct'=>'float','gst_pct'=>'float','sqm'=>'integer'];
}
