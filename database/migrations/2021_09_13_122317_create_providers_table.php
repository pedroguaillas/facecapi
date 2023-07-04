<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->integer('state')->default(1);   //1-activo/2-inactivo
            $table->string('type_identification', 10);    //ruc/cedula/pasaporte
            $table->string('identication', 13);
            $table->string('name', 300);
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('accounting')->default(false);
            $table->integer('discount')->nullable();            //Invoice

            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->unique(['branch_id', 'identication'], 'provider_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('providers');
    }
}
