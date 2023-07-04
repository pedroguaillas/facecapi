    @if(count($orderaditionals) > 0)
    <table style="padding-bottom: .7em;" class="table-collapse">
        <tbody>
            <tr>
                <th style="padding: .5em 0em;" class="align-middle" colspan="2">INFORMACIÓN ADICIONAL</th>
            </tr>
            @foreach($orderaditionals as $orderaditional)
            <tr>
                <td style="padding: .3em .3em; width: 100px;">{{ $orderaditional->name }}</td>
                <td style="padding: .3em .3em; width: 354px">{{ $orderaditional->description }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <table class="table-collapse">
        <tbody>
            <tr>
                <th style="width: 375px;">Forma de pago</th>
                <th style="width: 70px;">Valor</th>
            </tr>
            <tr>
                <td>OTROS CON UTILIZACIÓN DEL SISTEMA FINANCIERO NACIONAL</td>
                <td style="padding-right: .5em; text-align: right;">{{ number_format($movement->total, 2) }}</td>
            </tr>
        </tbody>
    </table>