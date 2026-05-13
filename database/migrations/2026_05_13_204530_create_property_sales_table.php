<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_id', 40)->unique();
            $table->unsignedInteger('price');
            $table->date('sale_date')->index();
            $table->string('postcode', 8)->index();
            $table->char('property_type', 1)->index();  // D/S/T/F/O
            $table->boolean('new_build')->default(false);
            $table->char('estate_type', 1);             // F=freehold, L=leasehold
            $table->string('paon', 100);                // house number/name
            $table->string('saon', 100)->nullable();    // flat/unit number
            $table->string('street', 100)->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('town_city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->foreignId('epc_certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('epc_match_confidence', 10)->nullable(); // exact/fuzzy/none

            $table->index(['postcode', 'property_type', 'sale_date']);
        });

        DB::statement('ALTER TABLE property_sales ADD COLUMN location geography(Point, 4326)');
        DB::statement('CREATE INDEX property_sales_location_idx ON property_sales USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_sales');
    }
};
