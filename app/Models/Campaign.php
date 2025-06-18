<?php

namespace App\Models;

use App\Models\Call;
use App\Models\Nasbah;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'campaign_name', 
        'product_type', 
        'dialing_type',
        'created_by',
        'keterangan',
        'is_active',
        'retry_count',
        
    ];

    public function calls()
    {
        return $this->hasMany(Call::class);
    }
    public function nasbahs() 
    {
        return $this->hasMany(Nasbah::class);
    }   
}
