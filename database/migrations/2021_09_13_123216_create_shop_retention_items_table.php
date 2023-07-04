<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopRetentionItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_retention_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->smallInteger('code');   //1-Imp. Renta/2-IVA
            $table->string('tax_code');     //Foreign Key Tax
            $table->decimal('base', 8, 2);  //base to retention
            $table->decimal('porcentage', 5, 2); //Not all tax contain porcentage retention
            $table->decimal('value', 8, 2);      //Shuld modify value & porcentage
            $table->bigInteger('shop_id')->unsigned(); //Foreign Key Sale

            $table->timestamps();

            $table->foreign('tax_code')->references('code')->on('taxes');
            $table->foreign('shop_id')->references('id')->on('shops');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_retention_items');
    }
}
