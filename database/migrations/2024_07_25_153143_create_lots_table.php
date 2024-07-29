<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emision_points', function (Blueprint $table) {
            $table->integer('lot')->default(1)->after('settlementonpurchase');
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emision_point_id');
            $table->string('serie', 17);
            $table->string('authorization', 49);
            $table->timestamp('authorized_at')->nullable();
            $table->char('state', 15)->nullable();
            $table->string('extra_detail')->nullable();
            $table->timestamps();

            $table->foreign('emision_point_id')->references('id')->on('emision_points');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('lot_id')->after('serie')->nullable();

            $table->foreign('lot_id')->references('id')->on('lots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
