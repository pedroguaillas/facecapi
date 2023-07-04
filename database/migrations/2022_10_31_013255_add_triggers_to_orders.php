<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddTriggersToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_edit');
        DB::unprepared("CREATE TRIGGER stock_edit AFTER INSERT ON inventories
            FOR EACH ROW
            BEGIN
                IF NEW.type = 'Compra' OR NEW.type = 'Devolución en venta'
                    THEN
                        UPDATE products
                        SET stock = stock + NEW.quantity
                        WHERE stock IS NOT NULL AND products.id = NEW.product_id;
                    END IF;

                IF NEW.type = 'Venta' OR NEW.type = 'Devolución en compra'
                    THEN
                        UPDATE products
                        SET stock = stock - NEW.quantity
                        WHERE stock IS NOT NULL AND stock - NEW.quantity >= 0 AND products.id = NEW.product_id;
                    END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `stock_edit`');
    }
}
