<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('ruc', 13)->unique(); //Constraint
            $table->string('company', 300);
            $table->string('economic_activity', 300);
            $table->boolean('accounting')->default(false);
            $table->boolean('micro_business')->default(false);
            $table->integer('retention_agent')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('logo_dir', 30)->nullable();
            $table->string('cert_dir', 30)->nullable();
            $table->string('pass_cert')->nullable();
            $table->date('sign_valid_from')->nullable();
            $table->date('sign_valid_to')->nullable();
            $table->integer('enviroment_type')->default(1); //Por defecto se crea en ambiente de prueba
            $table->boolean('active')->default(true); //Activo para ingresar a la aplicacion
            $table->boolean('active_voucher')->default(true); //Activo para emitir comprobantes
            $table->integer('decimal')->default(2); //Permitir calcular valores hasta con 2 decimales

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('companies');
    }
}
