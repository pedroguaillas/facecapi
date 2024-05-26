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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('base8')->default(false)->after('base5');
            $table->date('tourism_from')->nullable()->after('base8');
            $table->date('tourism_to')->nullable()->after('tourism_from');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('tourism')->default(false)->after('stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('base8')->default(0)->after('base5');
            $table->decimal('iva8')->default(0)->after('iva5');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            //
        });
    }
};
