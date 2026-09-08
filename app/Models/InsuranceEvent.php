<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceEvent extends Model
{
    protected $fillable = ['insurance_case_id','actor_user_id','type','title','description','meta'];
    protected $casts = ['meta'=>'array'];
}
