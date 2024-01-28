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
            $table->integer('invoice')->default(1)->after('point');
            $table->integer('creditnote')->default(1)->after('invoice');
            $table->integer('retention')->default(1)->after('creditnote');
            $table->integer('referralguide')->default(1)->after('retention');
            $table->integer('settlementonpurchase')->default(1)->after('referralguide');
            $table->boolean('enabled')->default(true)->after('settlementonpurchase');
            $table->string('recognition')->nullable()->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emision_points', function (Blueprint $table) {
            //
        });
    }
};
