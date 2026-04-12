<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Address extends Model
{
    protected $fillable = ['user_id','label','recipient_name','phone','address_line1','address_line2','city','district','country','latitude','longitude','is_default'];
    protected $casts = ['is_default'=>'boolean','latitude'=>'float','longitude'=>'float'];
    public function user() { return $this->belongsTo(User::class); }
}
