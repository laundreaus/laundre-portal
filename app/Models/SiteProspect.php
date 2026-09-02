<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SiteProspect extends Model {
    protected $fillable = ['name','stage','position','target','amount','notes',
        'agent_name','agent_email','agent_phone','centre_name','centre_email','centre_phone','lat','lng'];
    protected $casts = ['lat'=>'float','lng'=>'float','position'=>'integer'];
}
