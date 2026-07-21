<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SiteScore extends Model {
    protected $fillable = ['name','address','suburb','status','sqm','rent','parking','pop','notes','scores','overall','attachments'];
    protected $casts = ['scores'=>'array','attachments'=>'array','overall'=>'float'];
}
