<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceQuoteResult extends Model
{
    protected $fillable = ['insurance_case_id','provider','company_name','premium','currency','status','payload','fetched_at'];
    protected $casts = ['premium'=>'decimal:2','payload'=>'array','fetched_at'=>'datetime'];
}
