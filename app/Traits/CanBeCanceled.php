<?php

namespace App\Traits;

use Carbon\Carbon;

trait CanBeCanceled
{
    /**
     * Verifica si el comprobante puede ser anulado según normativa.
     *
     * @param string $dateField Nombre del campo de fecha a evaluar
     * @return bool
     */
    public function isCancelable(string $dateField = 'date'): bool
    {
        if (!isset($this->{$dateField})) {
            return false;
        }

        $fechaComprobante = Carbon::parse($this->{$dateField});
        $hoy = Carbon::now();
        $mesActual = $hoy->month;
        $anioActual = $hoy->year;

        $mesComprobante = $fechaComprobante->month;
        $anioComprobante = $fechaComprobante->year;

        // ✅ Si es del mismo mes
        if ($mesComprobante === $mesActual && $anioComprobante === $anioActual) {
            return true;
        }

        // ✅ Si es del mes anterior y hoy es 7 o menos
        $mesAnterior = $hoy->copy()->subMonth();
        if (
            $mesComprobante === $mesAnterior->month &&
            $anioComprobante === $mesAnterior->year &&
            $hoy->day <= 7
        ) {
            return true;
        }

        // ❌ En otros casos, no se puede anular
        return false;
    }
}
