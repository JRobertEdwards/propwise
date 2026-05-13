<?php

namespace Database\Factories;

use App\Models\EpcCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EpcCertificateFactory extends Factory
{
    protected $model = EpcCertificate::class;

    public function definition(): array
    {
        return [
            'lmk_key'               => $this->faker->unique()->uuid(),
            'address1'              => $this->faker->buildingNumber() . ' ' . $this->faker->streetName(),
            'address2'              => null,
            'address3'              => null,
            'postcode'              => strtoupper($this->faker->bothify('??# #??')),
            'property_type'         => $this->faker->randomElement(['House', 'Flat', 'Maisonette', 'Bungalow']),
            'built_form'            => $this->faker->randomElement(['Detached', 'Semi-Detached', 'Terraced', 'End-Terrace']),
            'inspection_date'       => $this->faker->dateTimeBetween('-5 years')->format('Y-m-d'),
            'total_floor_area'      => $this->faker->randomFloat(1, 30, 300),
            'number_habitable_rooms'=> $this->faker->numberBetween(1, 8),
            'current_energy_rating' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G']),
            'construction_age_band' => $this->faker->randomElement(['pre-1900', '1900-1929', '1930-1949', '1950-1966', '1967-1975', '1976-1982', '1983-1990', '1991-1995', '1996-2002', '2003-2006', '2007-2011', '2012 onwards']),
            'address_normalized'    => null,
        ];
    }
}
