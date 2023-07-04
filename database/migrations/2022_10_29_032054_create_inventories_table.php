<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInventoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('model_id')->nullable(); //Hace referencia al id de la compra o venta
            $table->set('type', ['Inventario inicial', 'Compra', 'Venta', 'Devolución en compra', 'Devolución en venta', 'Ajuste ingreso', 'Ajuste salida']);
            $table->decimal('quantity', 10, 6);
            $table->decimal('price', 8, 6);
            $table->date('date');
            $table->string('code_provider', 25)->nullable();

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventories');
    }
}
