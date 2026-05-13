<?php

namespace Database\Factories;

use App\Models\Postcode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostcodeFactory extends Factory
{
    protected $model = Postcode::class;

    public function definition(): array
    {
        $lat = $this->faker->latitude(49.9, 55.8);
        $lng = $this->faker->longitude(-5.7, 1.8);

        return [
            'postcode'  => strtoupper($this->faker->bothify('??# #??')),
            'latitude'  => $lat,
            'longitude' => $lng,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Postcode $postcode) {
            \DB::statement(
                'UPDATE postcodes SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE postcode = ?',
                [$postcode->longitude, $postcode->latitude, $postcode->postcode]
            );
        });
    }
}
