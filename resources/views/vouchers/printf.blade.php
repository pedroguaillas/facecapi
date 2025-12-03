<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura</title>
    <style>
        * {
            box-sizing: border-box;
            padding: 0;
            margin: 0;
        }

        body {
            font-size: .6em;
            font-family: Arial, Helvetica, sans-serif;
            text-align: center;
        }

        /* Este estable el margen de la hoja */
        html {
            margin: 1em;
        }
    </style>
</head>

<body>
    @if($company->branches[0]->name !== null)
    <strong>{{ $company->branches[0]->name }}</strong>
    @endif
    <div style="font-style: italic;">{{ $company->company }}</div>
    <div>RUC: {{ $company->ruc }}</div>
    <div>{{ $company->branches[0]->address }}</div>
    <div>------------------------------------------------------------------------------------</div>
    <strong>Factura: {{ $movement->serie }}</strong>
    <div>CLAVE DE ACCESO / AUTORIZACION</div>
    <div>{{ $movement->authorization }}</div>
    <div>Fecha: {{ $movement->date }}</div>
    <div>------------------------------------------------------------------------------------</div>
    <strong>CLIENTE</strong>
    <div>Identificación: {{ $movement->identication }}</div>
    <div>Nombre: {{ $movement->name }}</div>
    <div>------------------------------------------------------------------------------------</div>
    <table style="width: 100%;">
        <thead>
            <tr>
                <th>Descripción</th>
                <th style="text-align: center; width: 4em;">Cant.</th>
                <th style="text-align: right; width: 5em;">Precio</th>
                <th style="text-align: right; width: 5em;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movement_items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td style="text-align: center; width: 4em;">{{ floatval($item->quantity) }}</td>
                <td style="text-align: right; width: 5em;">{{ number_format($item->price, $company->decimal) }}</td>
                <td style="text-align: right; width: 5em;">{{ number_format($item->quantity * $item->price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div>------------------------------------------------------------------------------------</div>
    <table style="width: 100%;">
        <tbody>
            @if($movement->base5 > 0)
            <tr>
                <td>SUBTOTAL 5%</td>
                <td style="text-align: right;">{{ number_format($movement->base5, 2) }}</td>
            </tr>
            @endif
            @if(!$after)
            <tr>
                <td>SUBTOTAL 15%</td>
                <td style="text-align: right;">{{ number_format($movement->base15, 2) }}</td>
            </tr>
            @else
            <tr>
                <td>SUBTOTAL 12%</td>
                <td style="text-align: right;">{{ number_format($movement->base12, 2) }}</td>
            </tr>
            @endif
            @if($movement->base0 > 0)
            <tr>
                <td>SUBTOTAL 0%</td>
                <td style="text-align: right;">{{ number_format($movement->base0, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>SUBTOTAL SIN IMPUESTOS</td>
                <td style="text-align: right;">{{
                    number_format($movement->no_iva + $movement->base0 + $movement->base5 + $movement->base8 + $movement->base15, 2)
                }}</td>
            </tr>
            @if(!$after)
            <tr>
                <td>IVA 15%</td>
                <td style="text-align: right;">{{ number_format($movement->iva15, 2) }}</td>
            </tr>
            @else
            <tr>
                <td>IVA 12%</td>
                <td style="text-align: right;">{{ number_format($movement->iva, 2) }}</td>
            </tr>
            @endif
            <tr>
                <th>TOTAL</th>
                <th style="text-align: right;">
                    {{ number_format($movement->total, 2) }}
                </th>
            </tr>
        </tbody>
    </table>
</body>

</html>