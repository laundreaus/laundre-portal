<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model {
    protected $primaryKey = 'key'; public $incrementing = false; protected $keyType = 'string';
    protected $fillable = ['key','value']; protected $casts = ['value'=>'array'];
    public static function get(string $key, $default = null) { $r = static::find($key); return $r ? $r->value : $default; }
    public static function put(string $key, $value) { return static::updateOrCreate(['key'=>$key], ['value'=>$value]); }
}
