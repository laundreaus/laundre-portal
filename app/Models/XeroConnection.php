<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class XeroConnection extends Model {
    protected $fillable = ['location_id','org_name','tenant_id','status','access_token','refresh_token','expires_at','last_sync_at'];
    protected $casts = ['expires_at'=>'datetime','last_sync_at'=>'datetime'];
    protected $hidden = ['access_token','refresh_token'];
}
