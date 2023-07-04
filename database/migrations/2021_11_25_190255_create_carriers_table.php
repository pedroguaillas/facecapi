<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCarriersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->string('type_identification', 10);    //cedula/ruc/pasaporte/Identification exterior
            $table->string('identication', 13);
            $table->string('name', 300);    //nombre comercial
            $table->string('email')->nullable();
            $table->string('license_plate', 20);    //nombre comercial

            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->unique(['branch_id', 'identication'], 'carrier_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('carriers');
    }
}
