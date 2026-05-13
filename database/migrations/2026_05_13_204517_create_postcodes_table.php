<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postcodes', function (Blueprint $table) {
            $table->string('postcode', 8)->primary();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
        });

        DB::statement('ALTER TABLE postcodes ADD COLUMN location geography(Point, 4326)');
        DB::statement('CREATE INDEX postcodes_location_idx ON postcodes USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('postcodes');
    }
};
