<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EpcCertificate extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'lmk_key', 'address1', 'address2', 'address3', 'postcode',
        'property_type', 'built_form', 'inspection_date',
        'total_floor_area', 'number_habitable_rooms',
        'current_energy_rating', 'construction_age_band', 'address_normalized',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'total_floor_area' => 'float',
        'number_habitable_rooms' => 'integer',
    ];

    public function sales()
    {
        return $this->hasMany(PropertySale::class);
    }
}
