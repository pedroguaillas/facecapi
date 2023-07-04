<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReferralGuidesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referral_guides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('carrier_id');
            $table->string('serie', 17); //Serie Establecimiento, punto de emisión y secuencia.

            $table->string('address_from', 300);
            $table->string('address_to', 300);
            $table->date('date_start');
            $table->date('date_end');
            $table->string('reason_transfer', 300);
            $table->string('customs_doc', 20)->nullable();
            $table->string('branch_destiny', 3)->nullable();
            $table->string('route', 300)->nullable();

            // Si es de una factura poner estos campos
            $table->string('serie_invoice', 17)->nullable();
            $table->string('authorization_invoice', 49)->nullable();
            $table->date('date_invoice')->nullable();

            //Comprobante Electronica Inicio +++++++++++++++
            //CREADO-ENVIADO-RECIBIDA-DEVUELTA-ACEPTADO-RECHAZADO-EN_PROCESO-AUTORIZADO-NO_AUTORIZADO-CANCELADO
            $table->char('state', 15)->default('CREADO');
            $table->timestamp('autorized')->nullable();
            $table->string('authorization', 49)->nullable();
            $table->string('xml')->nullable();
            $table->string('extra_detail')->nullable();
            //Comprobante Electronica Fin ++++++++++++++++++

            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('carrier_id')->references('id')->on('carriers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referral_guides');
    }
}
