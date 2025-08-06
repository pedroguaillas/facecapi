<?php

namespace Tests\Unit\Traits;

use Tests\TestCase;
use Carbon\Carbon;
use App\Traits\CanBeCanceled;
use Illuminate\Support\Facades\Date;

class CanBeCanceledTest extends TestCase
{
    use CanBeCanceled;

    protected function setUp(): void
    {
        parent::setUp();
        // Fijar la fecha actual como 2025-08-05 para pruebas consistentes
        Date::setTestNow(Carbon::create(2025, 8, 5));
    }

    /** @test */
    public function retorna_true_si_el_comprobante_es_del_mismo_mes()
    {
        $fechaComprobante = Carbon::create(2025, 8, 1); // mismo mes y año
        $this->assertTrue($this->isCancelable($fechaComprobante));
    }

    /** @test */
    public function retorna_true_si_el_comprobante_es_del_mes_anterior_y_hoy_es_7_o_menos()
    {
        $fechaComprobante = Carbon::create(2025, 7, 15); // mes anterior
        $this->assertTrue($this->isCancelable($fechaComprobante));
    }

    /** @test */
    public function retorna_false_si_el_comprobante_es_del_mes_anterior_y_hoy_es_mayor_a_7()
    {
        Date::setTestNow(Carbon::create(2025, 8, 8)); // cambiar fecha a 8
        $fechaComprobante = Carbon::create(2025, 7, 15);
        $this->assertFalse($this->isCancelable($fechaComprobante));
    }

    /** @test */
    public function retorna_false_si_el_comprobante_es_de_hace_dos_meses()
    {
        $fechaComprobante = Carbon::create(2025, 6, 30);
        $this->assertFalse($this->isCancelable($fechaComprobante));
    }

    /** @test */
    public function retorna_false_si_el_comprobante_es_de_un_mes_futuro()
    {
        $fechaComprobante = Carbon::create(2025, 9, 1); // mes siguiente
        $this->assertFalse($this->isCancelable($fechaComprobante));
    }
}
