<?php

namespace Database\Factories;

use App\Models\PropertySale;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertySaleFactory extends Factory
{
    protected $model = PropertySale::class;

    public function definition(): array
    {
        return [
            'transaction_id'       => $this->faker->unique()->uuid(),
            'price'                => $this->faker->numberBetween(50000, 2000000),
            'sale_date'            => $this->faker->dateTimeBetween('-5 years')->format('Y-m-d'),
            'postcode'             => strtoupper($this->faker->bothify('??# #??')),
            'property_type'        => $this->faker->randomElement(['D', 'S', 'T', 'F']),
            'new_build'            => false,
            'estate_type'          => $this->faker->randomElement(['F', 'L']),
            'paon'                 => $this->faker->buildingNumber(),
            'saon'                 => null,
            'street'               => $this->faker->streetName(),
            'locality'             => null,
            'town_city'            => $this->faker->city(),
            'district'             => $this->faker->city(),
            'county'               => $this->faker->city(),
            'epc_certificate_id'   => null,
            'epc_match_confidence' => null,
        ];
    }

    public function forPostcode(string $postcode, float $lat, float $lng): static
    {
        return $this->state(['postcode' => $postcode])->afterCreating(function (PropertySale $sale) use ($lat, $lng) {
            \DB::statement(
                'UPDATE property_sales SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$lng, $lat, $sale->id]
            );
        });
    }
}
