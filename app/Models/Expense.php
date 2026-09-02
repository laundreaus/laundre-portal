<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model {
    protected $fillable = ['location_id','month','category','amount','source','xero_id','note'];
    protected $casts = ['amount'=>'float'];
}
