<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            //Note for app movil requiere (code, name, price1, iva)
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            // Categoria es nullable porque un producto puede o no ser categorizado
            $table->unsignedBigInteger('category_id')->nullable();
            // Longitud codigo 25 segun manual FE
            $table->string('code', 25);           //Constraint below
            $table->integer('type_product');
            $table->string('name', 300);                    //Invoice
            // Unidad es nullable porque solo se utiliza en inventarios
            $table->unsignedBigInteger('unity_id')->nullable();
            $table->decimal('price1', 14, 6)->nullable();     //Invoice
            $table->decimal('price2', 14, 6)->nullable();
            $table->decimal('price3', 14, 6)->nullable();
            $table->integer('iva');                         //Invoice
            $table->integer('ice')->nullable();             //Invoice
            $table->integer('irbpnr')->nullable();          //Invoice
            $table->integer('entry_account_id')->nullable();    //Account
            $table->integer('active_account_id')->nullable();   //Account
            $table->integer('inventory_account_id')->nullable(); //Account
            $table->integer('stock')->nullable();               //Inventory
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unity_id')->references('id')->on('unities');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->unique(['branch_id', 'code'], 'product_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
