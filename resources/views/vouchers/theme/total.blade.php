<div style="width: 200px; margin-top: .5em;">
    <table class="table-collapse">
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
                <td class="relleno">SUBTOTAL EXENTO IVA</td>
                <td style="padding-right: .5em; text-align: right;">0.00</td>
            </tr>
            <tr>
                <td class="relleno">SUBTOTAL SIN IMPUESTOS</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->base12 + $movement->base0 + $movement->no_iva, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">DESCUENTO</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->discount, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">IVA 12%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->iva, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">PROPINA</td>
                <td style="padding-right: .5em; text-align: right;">0.00</td>
            </tr>
            <tr>
                <th class="relleno">TOTAL</th>
                <th style="padding-right: .5em; text-align: right;">{{ number_format($movement->total, 2) }}</th>
            </tr>
        </tbody>
    </table>
</div>