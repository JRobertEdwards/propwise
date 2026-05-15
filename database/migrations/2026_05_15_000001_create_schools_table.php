<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('urn')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('phase')->nullable();
            $table->string('postcode', 10)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        DB::statement('ALTER TABLE schools ADD COLUMN location geometry(Point,4326)');
        DB::statement('CREATE INDEX schools_location_gist ON schools USING GIST(location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
