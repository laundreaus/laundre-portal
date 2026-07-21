<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ticket extends Model {
    protected $fillable = ['type','location_id','user_id','user_name','user_email','subject','body','status'];
    public function messages() { return $this->hasMany(TicketMessage::class); }
    public function location() { return $this->belongsTo(Location::class); }
}
