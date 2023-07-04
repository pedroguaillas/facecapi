<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de cuentas</title>
    <style>
        @page {
            margin: 5em 2.5em 3em;
        }

        table {
            border-collapse: collapse;
        }

        table,
        td,
        th {
            border: 1px solid #888;
        }
    </style>
</head>

<body>
    <main style="margin-top: 10px">
        <div style="text-align: center; margin-bottom: 1em">
            <strong style="text-align: center">PLAN DE CUENTAS</strong>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 570px">Cuenta</th>
                    <th style="width: 130px">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($charts as $chart)
                <tr>
                    <td>
                        @if( substr_count($chart['account'], '.') > 3 )
                        {{$chart['account']}} {{$chart['name']}}
                        @else
                        <strong>{{$chart['account']}} {{$chart['name']}}</strong>
                        @endif
                    </td>
                    <td style="text-align: right">
                        @if( substr_count($chart['account'], '.') > 3 )
                        {{number_format((float)$chart['amount'], 2, '.', ',')}}
                        @else
                        <strong>{{number_format((float)$chart['amount'], 2, '.', ',')}}</strong>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>

</html>