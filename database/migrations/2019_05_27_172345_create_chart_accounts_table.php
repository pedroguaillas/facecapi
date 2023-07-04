<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChartAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('account', 20); //Not unique by two types
            $table->string('name');
            $table->string('economic_activity'); //Varios
            $table->string('sort_account', 15)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->timestamps();

            // Not require unique key because ['account', 'type'], can repeat of diferent users
            // Require delete constraint in production
            $table->unique(['account', 'economic_activity'], 'chart_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chart_accounts');
    }
}
