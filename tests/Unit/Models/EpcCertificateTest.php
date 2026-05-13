<?php

namespace Tests\Unit\Models;

use App\Models\EpcCertificate;
use App\Models\PropertySale;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EpcCertificateTest extends TestCase
{
    #[Test]
    public function no_timestamps(): void
    {
        $this->assertFalse((new EpcCertificate())->usesTimestamps());
    }

    #[Test]
    public function floor_area_cast_to_float(): void
    {
        $model = new EpcCertificate(['total_floor_area' => '85.5']);
        $this->assertIsFloat($model->total_floor_area);
    }

    #[Test]
    public function habitable_rooms_cast_to_integer(): void
    {
        $model = new EpcCertificate(['number_habitable_rooms' => '4']);
        $this->assertIsInt($model->number_habitable_rooms);
    }

    #[Test]
    public function inspection_date_cast_to_date(): void
    {
        $model = new EpcCertificate(['inspection_date' => '2022-04-01']);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $model->inspection_date);
    }

    #[Test]
    public function has_many_sales(): void
    {
        $relation = (new EpcCertificate())->sales();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
        $this->assertInstanceOf(PropertySale::class, $relation->getRelated());
    }
}
