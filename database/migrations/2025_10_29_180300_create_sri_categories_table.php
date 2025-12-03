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
        Schema::create('sri_categories', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('description');
            $table->string('type');

            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('transport')->default(false)->after('ice');
            $table->boolean('repayment')->default(false)->after('transport');
        });

        Schema::create('repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->integer('type_id_prov');
            $table->string('identification');
            $table->integer('cod_country');
            $table->integer('type_prov');
            $table->integer('type_document');
            $table->string('sequential');
            $table->date('date');
            $table->string('authorization');

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
        });

        Schema::create('repayment_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repayment_id');
            $table->integer('iva_tax_code');
            $table->decimal('percentage',5,2);
            $table->decimal('base',12,2);
            $table->decimal('iva',12,2);

            $table->timestamps();

            $table->foreign('repayment_id')->references('id')->on('repayments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sri_categories');
    }
};
