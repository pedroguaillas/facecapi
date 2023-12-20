<div style="width: 100%;">
    <table class="table-collapse" style="width: 100%; margin-top: .5em;">
        <tbody>
            <tr>
                <td style=" width:160px;" class="relleno">SUBTOTAL IVA 12%</td>
                <td style=" width:85px; padding-right: .5em; text-align: right;">{{ number_format($movement->base12, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">SUBTOTAL IVA 0%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->base0, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">SUBTOTAL NO OBJETO IVA</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->no_iva, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">SUBTOTAL SIN IMPUESTOS</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->sub_total, 2) }}</td>
            </tr>
            @if($movement->ice > 0)
            <tr>
                <td class="relleno">MONTO ICE</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->ice, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td class="relleno">IVA 12%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->iva, 2) }}</td>
            </tr>
            <tr>
                <th class="relleno">TOTAL</th>
                <th style="padding-right: .5em; text-align: right;">{{ number_format($movement->total, 2) }}</th>
            </tr>
        </tbody>
    </table>
</div>