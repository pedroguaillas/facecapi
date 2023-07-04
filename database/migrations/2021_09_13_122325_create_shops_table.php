<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->date('date');
            $table->string('description')->nullable();
            $table->decimal('sub_total', 8, 2)->default(0);

            $table->string('serie', 17); //Serie Establecimiento, punto de emisión y secuencia.
            $table->unsignedBigInteger('provider_id')->unsigned(); //Cliente puede ser nulo cuando sea cliente final.
            $table->bigInteger('doc_realeted')->nullable();
            $table->integer('expiration_days')->default(0);
            $table->decimal('no_iva', 8, 2)->default(0);
            $table->decimal('base0', 8, 2)->default(0);
            $table->decimal('base12', 8, 2)->default(0);
            $table->decimal('iva', 8, 2)->default(0);    //value iva
            $table->decimal('discount', 8, 2)->default(0);
            $table->decimal('ice', 8, 2)->default(0);
            $table->decimal('total', 8, 2)->default(0);
            $table->smallInteger('voucher_type')->default(1); //Type voucher 01-F / 03-LC / 04-NC / 05-ND
            $table->decimal('paid', 8, 2)->default(0);   //Mount paid <= total ... parcial mount paid

            //Solo para liquidacion en compra que sea electronica Inicio +++++++++++++++
            //CREADO-ENVIADO-RECIBIDA-DEVUELTA-ACEPTADO-RECHAZADO-EN_PROCESO-AUTORIZADO-NO_AUTORIZADO-CANCELADO
            $table->char('state', 15)->nullable();
            $table->timestamp('autorized')->nullable();
            $table->string('authorization', 49)->nullable();
            $table->decimal('iva_retention', 8, 2)->default(0);
            $table->decimal('rent_retention', 8, 2)->default(0);
            $table->string('xml')->nullable();
            $table->string('extra_detail')->nullable();
            //Solo para liquidacion en compra que sea electronica Fin ++++++++++++++++++

            // Retencion
            $table->string('serie_retencion', 17)->nullable();  //serie retention
            $table->date('date_retention')->nullable();         //date retention <fechaEmision>

            // Retencion electronica
            $table->string('state_retencion', 15)->nullable();  //state retention
            $table->timestamp('autorized_retention')->nullable();
            $table->string('authorization_retention', 49)->nullable();
            $table->string('xml_retention')->nullable();
            $table->string('extra_detail_retention')->nullable();

            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('provider_id')->references('id')->on('providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shops');
    }
}
