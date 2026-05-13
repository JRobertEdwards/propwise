<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epc_certificates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('lmk_key', 64)->unique();
            $table->string('address1', 100)->nullable();
            $table->string('address2', 100)->nullable();
            $table->string('address3', 100)->nullable();
            $table->string('postcode', 8)->index();
            $table->string('property_type', 50)->nullable();
            $table->string('built_form', 50)->nullable();
            $table->date('inspection_date')->nullable();
            $table->decimal('total_floor_area', 8, 2)->nullable();
            $table->smallInteger('number_habitable_rooms')->nullable();
            $table->char('current_energy_rating', 1)->nullable();
            $table->string('construction_age_band', 50)->nullable();
            $table->string('address_normalized', 200)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epc_certificates');
    }
};
