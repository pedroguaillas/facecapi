<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountEntryItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_entry_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_entry_id'); //references table "account_entries"
            $table->unsignedBigInteger('chart_account_id'); //references table "chart_accounts"
            $table->decimal('debit', 14, 6)->nullable(); //mount debit
            $table->decimal('have', 14, 6)->nullable();  //mount have

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_entry_id')->references('id')->on('account_entries');
            $table->foreign('chart_account_id')->references('id')->on('chart_accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_entry_items');
    }
}
