<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','actor_name','actor_role','action','subject','method','path','ip','meta','created_at'];
    protected $casts = ['meta'=>'array','created_at'=>'datetime'];

    // Convenience recorder used by the middleware and by app events.
    public static function record(array $data): void {
        try {
            $data['created_at'] = $data['created_at'] ?? now();
            static::create($data);
        } catch (\Throwable $e) { /* never let logging break a request */ }
    }
}
