<div style="width: 100%;">
    <table class="table-collapse" style="width: 100%; margin-top: .5em;">
        <tbody>
            @if($movement->base5 > 0)
            <tr>
                <td class="relleno">SUBTOTAL 5%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->base5, 2) }}</td>
            </tr>
            @endif
            @if(!$after)
            <tr>
                <td style=" width:160px;" class="relleno">SUBTOTAL 15%</td>
                <td style=" width:85px; padding-right: .5em; text-align: right;">{{ number_format($movement->base15, 2) }}</td>
            </tr>
            @else
            <tr>
                <td style=" width:160px;" class="relleno">SUBTOTAL 12%</td>
                <td style=" width:85px; padding-right: .5em; text-align: right;">{{ number_format($movement->base12, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td class="relleno">SUBTOTAL 0%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->base0, 2) }}</td>
            </tr>
            <tr>
                <td class="relleno">SUBTOTAL SIN IMPUESTOS</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->sub_total, 2) }}</td>
            </tr>
            @if($movement->discount > 0)
            <tr>
                <td class="relleno">DESCUENTO</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->discount, 2) }}</td>
            </tr>
            @endif
            @if($movement->ice > 0)
            <tr>
                <td class="relleno">MONTO ICE</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->ice, 2) }}</td>
            </tr>
            @endif
            @if($movement->iva5 > 0)
            <tr>
                <td class="relleno">IVA 5%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->iva5, 2) }}</td>
            </tr>
            @endif
            @if(!$after)
            <tr>
                <td class="relleno">IVA 15%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->iva15, 2) }}</td>
            </tr>
            @else
            <tr>
                <td class="relleno">IVA 12%</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->iva, 2) }}</td>
            </tr>
            @endif
            <tr>
                <th class="relleno">TOTAL</th>
                <th style="padding-right: .5em; text-align: right;">{{ number_format($movement->total, 2) }}</th>
            </tr>
        </tbody>
    </table>
</div>