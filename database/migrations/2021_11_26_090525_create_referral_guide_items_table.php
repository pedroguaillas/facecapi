<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReferralGuideItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referral_guide_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('referral_guide_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 12, 6);

            $table->timestamps();

            $table->foreign('referral_guide_id')->references('id')->on('referral_guides');
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
        Schema::dropIfExists('referral_guide_items');
    }
}
